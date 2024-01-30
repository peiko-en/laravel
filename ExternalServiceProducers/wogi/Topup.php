<?php

namespace App\Services\Producers\wogi;

use App\Helpers\Debugger;
use App\Helpers\Tools;
use App\Models\Entity\Service;
use App\Models\Entity\TopupTransaction;
use App\Services\MailService;
use App\Services\Producers\TopupAbstract;

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
     * @return mixed
     * @throws \Exception
     */
    public function topup(TopupTransaction $transaction)
    {
        $this->api->initCredentials($transaction->getCountryIso());

        $response = $this->api->sendOrder($transaction);

        if (isset($response['providerReference'])) {
            $transaction->external_service_key = $response['providerReference'];
        }

        return $response;
    }

    public function callback()
    {
        /** @var TopupTransaction $transaction */
        $transaction = TopupTransaction::query()
            ->where('external_service_key', request()->get('provider_reference'))
            ->where('service', Service::EXT_WOGI)
            ->where('status', TopupTransaction::STATUS_PENDING)
            ->first();

        if (!$transaction) {
            Debugger::topup('TopupTransaction not found by ' . request()->get('provider_reference'));
            return;
        }

        $this->api->initCredentials($transaction->getCountryIso());

        if (request()->header('wogi-auth-token') != $this->api->getWebhookToken()) {
            Debugger::topup('wogi-auth-token is wrong!');
            return;
        }

        if (request()->get('status') != 'processed') {
            return;
        }

        $res = $this->api->getDetails(request()->get('api_signal_id'));

        if (!$res || !isset($res['data'])) {
            Debugger::topup('wogi details by '
                . request()->get('api_signal_id')
                . ' not found. '
                . json_encode($res));
            return;
        }

        if ($res['data']['status'] == 'successful') {
            $this->complete($transaction, $res['data']);
            $this->sendCode($transaction, $res['data']);
        } elseif ($res['data']['status'] == 'failed') {
            $this->fail($transaction, $res['data']);
        }
    }

    private function sendCode(TopupTransaction $transaction, array $data)
    {
        if (!isset($data['result'])) {
            Debugger::topup('the key result does not exist in response');
            return;
        }

        (new MailService())->common(
            $transaction->order->email,
            Tools::createNameFromEmail($transaction->order->email),
            view('mail.messages.wogi', [
                'serviceName' => $transaction->order->service->getName(),
                'redemptionOnlineInstructions' => $data['result']['brand']['redemptionOnlineInstructions'] ?? '',
                'redemptionInstoreInstructions' => $data['result']['brand']['redemptionInstoreInstructions'] ?? '',
                'cardTerms' => $data['result']['brand']['cardTerms'] ?? '',
                'activationTokenUrl' => $data['result']['activationTokenUrl'] ?? '',
                'expiryDate' => $data['result']['expiryDate'] ?? '',
            ])->render(),
            $transaction->order->service->getName()
        );
    }
}
