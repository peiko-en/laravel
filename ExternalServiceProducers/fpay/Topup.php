<?php

namespace App\Services\Producers\fpay;


use App\Helpers\Debugger;
use App\Helpers\Tools;
use App\Models\Entity\Order;
use App\Models\Entity\TopupTransaction;
use App\Services\MailService;
use App\Services\OrderService;
use App\Services\Producers\TopupAbstract;
use Illuminate\Support\Str;

class Topup extends TopupAbstract
{
    /**
     * @var Api
     */
    private $api;

    public function __construct(Api $api)
    {
        $this->api = $api;
    }

    /**
     * @param TopupTransaction $transaction
     * @return array|mixed
     * @throws \Exception
     */
    public function topup(TopupTransaction $transaction)
    {
        $response = $this->api->topup($transaction);
        if (isset($response['txId'])) {
            $transaction->external_service_key = $response['txId'];
        }

        return $response;
    }

    public function monitor(Order $order)
    {
        if (!$order->topupTransaction || $order->service->isHandle()) {
            Debugger::topup('Fpay: no transaction or not automatic: topupToken=' . $order->topupTransaction->token);
            return;
        }

        try {
            $data = $this->api->checkTransaction($order->topupTransaction->external_service_key);

            Debugger::topup($data);

            if (!isset($data['sTransactionStatus'])) {
                return;
            }

            if ($data['sTransactionStatus'] == 'PENDING') {
                if (Str::endsWith(strtolower($order->service->code), 'pin')) {
                    $pinData = $this->api->reloadPin($order->topupTransaction->external_service_key);

                    Debugger::topup($pinData);

                    if (isset($pinData['sReloadPin'])) {
                        $data['pinCodeData'] = $pinData;

                        $this->completeTransaction($order, $data);

                        $message = 'Service: ' . $order->service->getName() . '<br>'
                            . 'Pin: ' . $pinData['sReloadPin'] . '<br>'
                            . 'Serial number: ' . $pinData['sSerialNumber'] . '<br>'
                            . 'Amount: ' . $pinData['sAmount'] . '<br>'
                            . 'Expiry Date: ' . $pinData['sExpiryDate'];

                        (new MailService())
                            ->common(
                                $order->email,
                                Tools::createNameFromEmail($order->email),
                                $message,
                                $order->service->getName() . ' pin'
                            );
                    } else {
                        $this->completeTransaction($order, $data);
                    }
                } else {
                    $this->completeTransaction($order, $data);
                }
            } elseif (in_array($data['sTransactionStatus'], ['REFUND', 'BANNED'])) {
                $order->topupTransaction->fail($data);
            } elseif ($data['sTransactionStatus'] == 'SUCCESS') {
                $this->completeTransaction($order, $data);
            }
        } catch (\Exception $e) {
            Debugger::topup($e);

            $data = json_decode($e->getMessage(), true);

            if (isset($data['response']['sResponseStatus']) && $data['response']['sResponseStatus'] == 'QUERY_FAIL') {
                $this->fail($order->topupTransaction, $data);
            }
        }
    }

    private function completeTransaction(Order $order, $data)
    {
        $order->topupTransaction->success($data);
        app(OrderService::class)->confirm($order);
    }
}
