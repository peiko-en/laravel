<?php

namespace App\Services\Producers\fpay;

use App\Helpers\Arr;
use App\Helpers\Debugger;
use App\Helpers\PhoneHelper;
use App\Models\Entity\TopupTransaction;
use App\Services\BaseRequestAbstract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Api extends BaseRequestAbstract
{
    const API_ENDPOINT = 'https://onlinereload.net/srsapi/connect.asmx';
    const QUERY_ENDPOINT = 'https://onlinereload.net/srsquery/connect.asmx';

    private $username;
    private $password;
    private $secretKey;
    private $agentId;
    private $isApiRequest = false;

    public function __construct()
    {
        $this->username = config('private.fpay.username');
        $this->password = config('private.fpay.password');
        $this->secretKey = config('private.fpay.secretKey');
        $this->agentId = config('private.fpay.agentId');
    }

    protected function defaultParams()
    {
        return [
            'sClientUserName' => $this->username,
            'sClientPassword' => $this->password,
        ];
    }

    /**
     * @param TopupTransaction $transaction
     * @return array|mixed
     * @throws \Exception
     */
    public function topup(TopupTransaction $transaction)
    {
        $this->useApiEndpoint();

        $service = $transaction->order->service;

        $phone = $serviceNumber = null;
        if ($service->activePhone()) {
            $phone = PhoneHelper::topupFormat($transaction->order->phone, '60');
        }

        $serviceNumber = $transaction->order->extraParamValue('service_number');

        if ($phone && !$serviceNumber) {
            $sCustomerAccountNumber = $phone;
            $sCustomerMobileNumber = $phone;
            $sDealerMobileNumber = $transaction->order->phone;
        } elseif (!$phone && $serviceNumber) {
            $sCustomerAccountNumber = $serviceNumber;
            $sCustomerMobileNumber = $serviceNumber;
            $sDealerMobileNumber = $serviceNumber;
        } else {
            $sCustomerAccountNumber = $serviceNumber;
            $sCustomerMobileNumber = $serviceNumber;
            $sDealerMobileNumber = $transaction->order->phone;
        }

        $sts = $this->getSTs();

        $params = [
            'sClientTxID' => $sts . $this->agentId,
            'sProductID' => $service->external_id,
            'dProductPrice' => (int) $transaction->order->amount,
            'sTS' => $sts,
            'sRemark' => 'topup',
            //'sOtherRemarks' => 'topup',
            'sOtherParameter' => 'CODE=COUPON',
        ];

        $params['sCustomerAccountNumber'] = $sCustomerAccountNumber;
        $params['sCustomerMobileNumber'] = $sCustomerMobileNumber;
        $params['sDealerMobileNumber'] = $sDealerMobileNumber;

        $params['sEncKey'] = $this->createToken($sts);

        Debugger::topup($params);

        $response = $this->call('RequestTopup', $params);

        if ($response['success']) {
            $response['txId'] = $params['sClientTxID'];
        }

        return $response;
    }

    /**
     * @return string Format: yyyyMMddHHmmssSSS
     */
    private function getSTs()
    {
        return date('YmdHis') . substr((string) explode('.', microtime(true))[1], 0, 3);
    }

    private function createToken($sTs)
    {
        return md5($this->username . $this->secretKey . $sTs);
    }

    public function checkBalance()
    {
        $this->useQueryEndpoint();

        return $this->call('CheckBalance');
    }

    public function checkTransaction($clientTxId)
    {
        $this->useQueryEndpoint();

        return $this->call('CheckTransactionStatus', ['sClientTxID' => $clientTxId]);
    }

    public function reloadPin($clientTxId)
    {
        $this->useApiEndpoint();

        return $this->call('GetReloadPINImmediate', ['sLocalMOID' => $clientTxId]);
    }

    public function getDiscount()
    {
        $this->useQueryEndpoint();

        return $this->call('GetAgentProductDiscount', [])['success']['AgentProductDiscount'] ?? [];
    }

    public function getDiscountByProduct($productId)
    {
        $discounts = Cache::remember('fpay_discount', 300, function () {
            return $this->getDiscount();
        });

        $discountKey = Collection::make($discounts)->search(function ($item) use ($productId) {
            return $item['ProductID'] == $productId;
        });

        return $discounts[$discountKey] ?? [];
    }

    private function getBaseUrl()
    {
        return $this->isApiRequest ? self::API_ENDPOINT : self::QUERY_ENDPOINT;
    }

    private function useApiEndpoint()
    {
        $this->isApiRequest = true;
    }

    private function useQueryEndpoint()
    {
        $this->isApiRequest = false;
    }

    private function call($tem, $params = [])
    {
        $params['tem'] = $tem;
        return $this->post('', $params);
    }

    protected function request(string $method, string $uri, array $options)
    {
        $http = $this->getHttpClient($this->getBaseUrl());
        $formParams = $options['form_params'];
        $tem = $options['form_params']['tem'];
        unset($options['form_params']);

        $options['body'] = $this->createBody($formParams);

        $options['headers'] = [
            'Content-type' => 'text/xml',
        ];

        $response = $http->request('POST', '', $options);

        $responseContent = $response->getBody()->getContents();

        $xml = simplexml_load_string($responseContent);
        $xml->registerXPathNamespace('fpay', 'http://tempuri.org/');
        $result = json_decode(json_encode($xml->xpath('//fpay:' . $tem . 'Response')), true)[0] ?? [];
        $result['success'] = $result[$tem . 'Result'] ?? false;

        if (isset($result['sResponseID']) && $result['sResponseID'] < 0 || !$result['success']) {
            throw new \Exception(json_encode(['request' => $formParams, 'response' => $result]));
        }

        return $result;
    }

    private function createBody($params)
    {
        $xml = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/">';
        $xml .= '<soapenv:Header/>';
        $xml .= '<soapenv:Body>';
        $xml .= '<tem:'. $params['tem'] .'>';

        foreach (Arr::except($params, 'tem') as $param => $value) {
            $xml .= strtr('<tem:{param}>{value}</tem:{param}>', [
                '{param}' => $param,
                '{value}' => $value
            ]);
        }

        $xml .= '</tem:'. $params['tem'] .'>';
        $xml .= '</soapenv:Body>';
        $xml .= '</soapenv:Envelope>';

        return $xml;
    }
}
