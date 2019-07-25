<?php


namespace App\Services\Payments\Operations;


use App\Models\Entity\Order;
use App\Models\Entity\Transaction;
use App\Models\Repository\OrdersRepository;
use App\Services\Builders\NotificationBuilder;
use App\Services\MailService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class PayService extends Operation
{
    /**
     * @var OrdersRepository
     */
    private $orders;

    public function __construct(Transaction $transaction, OrdersRepository $orders)
    {
        parent::__construct($transaction);

        $this->orders = $orders;
    }

    public function process()
    {
        DB::transaction(function() {
            $order = $this->getOrder();

            if ($order && $order->isPending()) {
                if ($this->transaction->payment == 'balance') {
                    $order->user->balance->decrease($this->transaction->cost, $this->transaction->description);
                }
                $order->changeStatus($order::STATUS_PAYED);

                (new NotificationService())->operatorOrder($order);
                (new NotificationService())->adminOrder($order);

                (new MailService())->payedOrder($order);
            }
        });
    }

    public function fail()
    {
        $order = $this->getOrder();

        if ($order && $order->isPending()) {
            $order->changeStatus($order::STATUS_FAIL);
        }

        $this->resultDescription = trans('payment.service-pay-fail');
    }

    public function success()
    {
        $this->resultDescription = trans('payment.service-payed', [
            'service' => $this->getOrder()->service->getName()
        ]);
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->orders->findByTransactionId($this->transaction->id);
    }
}