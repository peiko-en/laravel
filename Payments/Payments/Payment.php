<?php


namespace App\Services\Payments;


use App\Models\Entity\Transaction;
use App\Models\Repository\PaymentsRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class Payment
{
    const MODE_LIVE = 'live';
    const MODE_TEST = 'test';

    const CASE_PROCESS = 'process';
    const CASE_SUCCESS = 'success';
    const CASE_FAIL = 'fail';

    protected $logo;
    protected $name;
    protected $slug;
    protected $is_active;
    protected $mode;
    protected $currency;
    protected $roundPrecision = 2;

    protected $transactionTokenParam = 'payment_token';
    protected $configKey;
    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @param string $slug
     * @return Payment
     */
    public static function getInstance($slug)
    {
        /** @var PaymentsRepository $payments */
        $payments = app(PaymentsRepository::class);
        $payment = $payments->findBySlug($slug);

        return app(__NAMESPACE__ . '\\' . Str::studly($slug), [
            'config' => $payment->attributesToArray()
        ]);
    }

    public function __construct($config = [])
    {
        $this->configure($config);
        $this->configure(config('private.payments.' . ($this->configKey ?: $this->slug), []));
    }

    protected function configure($config)
    {
        foreach ($config as $attribute => $value) {
            if (property_exists($this, $attribute)) {
                $this->$attribute = $value;
            }
        }
    }

    public function setTransaction(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLogoUrl(): string
    {
        return asset($this->logo);
    }

    public function isActive()
    {
        return $this->is_active == 1;
    }

    public function isLive()
    {
        return $this->mode == self::MODE_LIVE;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function getPrecision()
    {
        return $this->roundPrecision;
    }

    public function extractTransactionToken(Request $request)
    {
        return $request->get($this->transactionTokenParam);
    }

    public function getTransactionTokenParam()
    {
        return $this->transactionTokenParam;
    }

    public function getSuccessUrl()
    {
        return route('payment.case', ['slug' => $this->slug, 'case' => self::CASE_SUCCESS]);
    }

    public function getFailUrl()
    {
        return route('payment.case', ['slug' => $this->slug, 'case' => self::CASE_FAIL]);
    }

    public function getProcessUrl()
    {
        return route('payment.case', ['slug' => $this->slug, 'case' => self::CASE_PROCESS]);
    }

    public function isValidCost($value)
    {
        $value = (float) $value;
        return ($this->transaction && $value > 0 && $value == (float) $this->transaction->cost);
    }

    public function debug($message)
    {
        Log::channel('payment')->debug('[' . $this->slug . '] => ' . print_r($message, true));
    }

    abstract public function actionUrl(): string;

    /**
     * Build payment form
     * @return string
     */
    abstract public function formInputs(): array;

    /**
     * Verifying payment data received from payment IPN for process case
     * @param string|null $case
     * @return bool
     */
    abstract public function verify(string $case = null): bool;
}