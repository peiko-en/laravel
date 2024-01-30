<?php

namespace App\Services\Producers\esimgo;

use App\Helpers\Arr;
use App\Helpers\Debugger;
use App\Models\Entity\TopupTransaction;
use App\Services\BaseRequestAbstract;
use Illuminate\Support\Facades\Cache;

class Api extends BaseRequestAbstract
{
    private $baseUrl = 'https://api.esim-go.com/v2.2/';
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = config('private.esimgo.apiKey');
    }

    /**
     * @param TopupTransaction $transaction
     * @return array|mixed
     * @throws \Exception
     */
    public function sendOrder(TopupTransaction $transaction)
    {
        $service = $transaction->order->service;

        $params = [
            'type' => 'transaction', //transaction, validate
            'assign' => true,

            'Order' => [
                [
                    'type' => 'bundle',
                    'quantity' => 1,
                    'item' => $service->extraParam('name'),
                    //'iccids' => []
                ]
            ]
        ];

        $transaction->request_data = $params;

        $response = $this->post('orders', $params, true);

        Debugger::topup($response);

        return $response;
    }

    public function catalogue($page, $perPage)
    {
        return $this->get('catalogue', [
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    public function organisation()
    {
        return $this->get('organisation');
    }

    public function order(string $reference)
    {
        return $this->get('orders/' . $reference);
    }

    public function esimsAssignments(string $reference)
    {
        return $this->request('get', 'esims/assignments', [
            'query' => ['reference' => $reference],
            'headers' => [
                'Accept' => 'application/zip'
            ]
        ]);
    }

    /**
     * @return float
     */
    public function balance()
    {
        return Cache::remember('esimgo_available_balance', 300, function () {
            $data = $this->organisation();
            return [
                'Balance' => Arr::get($data, 'organisations.0.balance', 0) . ' ' . Arr::get($data, 'organisations.0.currency', 0)
            ];
        });
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $options
     * @return array|mixed|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function request(string $method, string $uri, array $options)
    {
        $headers = [
            'X-API-Key' => $this->apiKey,
            'Accept' => 'application/json'
        ];

        if (isset($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        $http = $this->getHttpClient($this->baseUrl, [
            'headers' => $headers
        ]);

        $response = $http->request($method, $uri, $options);

        try {
            $responseContent = $response->getBody()->getContents();

            if ($headers['Accept'] == 'application/json') {
                return json_decode($responseContent, true);
            }

            return $responseContent;
        } catch (\Exception $e) {
            Debugger::topup($e->getMessage());
            return [];
        }
    }
}
