<?php
declare(strict_types=1);

namespace App\Services\Matching;


use App\Events\GraphEvent;
use App\Helpers\DIHelper;
use App\Helpers\Format;
use App\Helpers\Log;
use App\Helpers\Math;
use App\Jobs\ExternalOrderJob;
use App\Models\Fee;
use App\Models\Order;
use App\Models\User;
use App\Services\Bonuses\RegisterChainProgram;
use Illuminate\Support\Facades\DB;

class BargainProcess
{
    private FeeManager $feeManager;

    private Order $takerOrder;
    private Order $makerOrder;
    private User $buyer;
    private User $seller;

    private float $dealQuantity = 0;
    private float $salePrice = 0;
    private float $buyerFee = 0;
    private float $sellerFee = 0;
    private int $mainCurrencyPrecision = 0;
    private int $baseCurrencyPrecision = 0;

    private bool $exchangeTrading;
    private int $buyerOrderId = 0;
    private int $sellerOrderId = 0;
    private RegisterChainProgram $registerProgram;

    public function __construct()
    {
        $this->feeManager = new FeeManager();
        $this->exchangeTrading = config('matching.exchange_trading');
        $this->registerProgram = DIHelper::registerProgram(new User);
    }

    public function reset(): self
    {
        $this->buyerOrderId = $this->sellerOrderId = 0;
        $this->mainCurrencyPrecision = $this->baseCurrencyPrecision = 0;
        $this->dealQuantity = $this->salePrice = $this->buyerFee = $this->sellerFee = 0;
        return $this;
    }

    public function setTakerOrder(Order $order): self
    {
        $this->takerOrder = $order;
        return $this;
    }

    public function setMakerOrder(Order $order): self
    {
        $this->makerOrder = $order;
        return $this;
    }

    public function getDealQuantity(): float
    {
        return $this->dealQuantity;
    }

    public function getBuyer(): User
    {
        return $this->buyer;
    }

    public function getSeller(): User
    {
        return $this->seller;
    }

    public function run(): bool
    {
        $this->defineSalePrice();
        $this->defineParticipants();
        $this->defineDealQuantity();

        if (!$this->dealQuantity) {
            return false;
        }

        $buyerProfit = $this->dealQuantity;
        $buyerFeeAmount = Math::calcFee($buyerProfit, $this->buyerFee, $this->mainCurrencyPrecision);

        $sellerProfit = (float) Math::mul($this->dealQuantity, $this->salePrice, $this->baseCurrencyPrecision);
        $sellerFeeAmount = Math::calcFee($sellerProfit, $this->sellerFee, $this->baseCurrencyPrecision);

        if ($this->takerOrder->isBuy()) {
            $this->takerOrder->increaseFeeAmount($buyerFeeAmount, $this->mainCurrencyPrecision);
            $this->makerOrder->increaseFeeAmount($sellerFeeAmount, $this->baseCurrencyPrecision);
        } else {
            $this->takerOrder->increaseFeeAmount($sellerFeeAmount, $this->baseCurrencyPrecision);
            $this->makerOrder->increaseFeeAmount($buyerFeeAmount, $this->mainCurrencyPrecision);
        }

        if ($this->takerOrder->isMarket() && $this->takerOrder->isBuy()) {
            $this->takerOrder->decreaseReservedAmount($sellerProfit, $this->baseCurrencyPrecision);
        }

        DB::beginTransaction();

        try {
            $this->takerOrder->createDeal($this->makerOrder, $this->dealQuantity, $buyerFeeAmount, $sellerFeeAmount);
            $this->makerOrder->decrease($this->dealQuantity);
            $this->takerOrder->decrease($this->dealQuantity);

            $buyerIncome = (float) Math::sub($buyerProfit, $buyerFeeAmount, $this->mainCurrencyPrecision);
            $this->buyer->wallet($this->mainCurrencyId())
                ->increase($buyerIncome, trans('app.buy-currency', ['currency' => $this->dealAmountCurrency()]))
                ->save();

            $sellerIncome = (float) Math::sub($sellerProfit, $sellerFeeAmount, $this->baseCurrencyPrecision);
            $this->seller->wallet($this->baseCurrencyId())
                ->increase($sellerIncome, trans('app.sell-currency', ['currency' => $this->dealAmountCurrency()]))
                ->save();

            $this->registerProgram->setUser($this->buyer)->trading($this->mainCurrencyId(), $buyerIncome);
            $this->registerProgram->setUser($this->seller)->trading($this->baseCurrencyId(), $sellerIncome);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::matching($e->getMessage());
            throw new MatchingException($e->getMessage());
        }

        try {
            if (!$this->buyer->isBot() && $buyerFeeAmount > 0) {
                Fee::add($this->mainCurrencyId(), $this->buyerOrderId, $buyerProfit, $this->buyerFee, $buyerFeeAmount);
            }

            if (!$this->seller->isBot() && $sellerFeeAmount > 0) {
                Fee::add($this->baseCurrencyId(), $this->sellerOrderId, $sellerProfit, $this->sellerFee, $sellerFeeAmount);
            }

            $this->dispatchOrderToExchange();

            if (!$this->buyer->isBot()) {
                DIHelper::voluumService($this->buyer)->trading($this->buyerOrderId);
            }

            if (!$this->seller->isBot()) {
                DIHelper::voluumService($this->seller)->trading($this->sellerOrderId);
            }
        } catch (\Exception $e) {
            Log::matching($e->getMessage());
        }

        return true;
    }

    private function defineDealQuantity()
    {
        $this->defineCurrencies();

        if ($this->makerOrder->quantity >= $this->takerOrder->quantity) {
            $this->dealQuantity = (float) $this->takerOrder->quantity;
        } else {
            $this->dealQuantity = (float) $this->makerOrder->quantity;
        }

        if ($this->makerOrder->isSell() && $this->takerOrder->isMarket()) {
            $maxQuantity = (float) Math::div($this->takerOrder->reserved_amount, $this->makerOrder->price, $this->mainCurrencyPrecision);
            if ($maxQuantity < $this->dealQuantity) {
                $this->dealQuantity = $maxQuantity;
            }
        }
    }

    private function defineParticipants()
    {
        if ($this->makerOrder->isBuy()) {
            $this->buyer = $this->makerOrder->owner;
            $this->seller = $this->takerOrder->owner;

            $this->buyerOrderId = $this->makerOrder->id;
            $this->sellerOrderId = $this->takerOrder->id;
        } else {
            $this->seller = $this->makerOrder->owner;
            $this->buyer = $this->takerOrder->owner;

            $this->sellerOrderId = $this->makerOrder->id;
            $this->buyerOrderId = $this->takerOrder->id;
        }

        $this->buyerFee = $this->getFee($this->buyer, $this->takerOrder->isBuy());
        $this->sellerFee = $this->getFee($this->seller, $this->takerOrder->isSell());
    }

    private function defineSalePrice()
    {
        if ($this->takerOrder->isLimit()) {
            $this->salePrice = $this->makerOrder->isSell() ? (float) $this->makerOrder->price  : (float) $this->takerOrder->price;
        } else {
            $this->salePrice =  (float) $this->makerOrder->price;
        }
    }

    private function defineCurrencies()
    {
        $this->mainCurrencyPrecision = $this->takerOrder->mainCurrencyPrecision();
        $this->baseCurrencyPrecision = $this->takerOrder->baseCurrencyPrecision();
    }

    private function getFee(User $user, bool $isTaker = false): float
    {
        try {
            return $this->feeManager->getFee($user, $this->takerOrder->pair, $isTaker);
        } catch (\Exception $e) {
            Log::matching($e->getMessage());
            return 0;
        }
    }

    private function dealAmountCurrency(): string
    {
        return Format::number($this->dealQuantity) . ' ' . $this->takerOrder->pairCode();
    }

    private function mainCurrencyId(): int
    {
        return $this->takerOrder->mainCurrencyId();
    }

    private function baseCurrencyId(): int
    {
        return $this->takerOrder->baseCurrencyId();
    }

    private function dispatchOrderToExchange()
    {
        if ($this->exchangeTrading) {
            if ($this->buyer->isBot() && !$this->seller->isBot() || !$this->buyer->isBot() && $this->seller->isBot()) {
                dispatch(new ExternalOrderJob(
                    $this->makerOrder->pair->getCode(),
                    $this->buyer->isBot() ? 'sell' : 'buy',
                    (float) $this->makerOrder->price,
                    $this->dealQuantity,
                    (int) $this->makerOrder->id,
                ));
            }
        }
    }
}
