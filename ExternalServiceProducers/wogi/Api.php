<?php

namespace App\Services\Producers\wogi;

use App\Helpers\Debugger;
use App\Models\Entity\TopupTransaction;
use App\Services\BaseRequestAbstract;

class Api extends BaseRequestAbstract
{
    private $hostPrime = 'https://api.wogi.dev/api/connect/';
    private $uid;
    private $secret;
    /**
     * @var null|string
     */
    private $webhookToken = null;

    public function __construct(string $countryIso = null)
    {
        if ($countryIso) {
            $this->initCredentials($countryIso);
        }
    }

    /**
     * @param string $countryIso (3 symbols. example: SGP, VNM)
     * @return void
     */
    public function initCredentials(string $countryIso)
    {
        $this->uid = config("private.wogi.$countryIso.uid");
        $this->secret = config("private.wogi.$countryIso.secret");
        $this->webhookToken = config("private.wogi.$countryIso.webhookToken");
    }

    /**
     * @return string|null
     */
    public function getWebhookToken()
    {
        return $this->webhookToken;
    }

    public function products(int $perPage = 10, int $page = 1)
    {
        return $this->get('products', [
            'limit' => $perPage,
            'page' => $page
        ]);
    }

    public function webhooks()
    {
        return $this->get('webhooks');
    }

    public function createWebhook(string $url, string $token)
    {
        return $this->post('webhooks', [
            'url' => $url,
            'authToken' => $token
        ]);
    }

    public function updateWebhook(int $id, string $url, string $token, bool $active = true)
    {
        return $this->request('put', 'webhooks/' . $id, [
            'json' => [
                'url' => $url,
                'authToken' => $token,
                'active' => $active ? 1 : 0,
            ]
        ]);
    }

    public function getDetails(string $signalId)
    {
        return $this->get('signals/' . $signalId);
    }

    public function getProduct(string $productId)
    {
        return $this->get('products/' . $productId);
    }

    /**
     * @throws \Exception
     */
    public function sendOrder(TopupTransaction $transaction)
    {
        $params = [
            'productId' => $transaction->order->face_value_id,
            'amount' => $transaction->order->amount,
            'transactionReferenceNumber' => $transaction->token,
        ];

        $transaction->request_data = $params;

        $data = $this->post('products/issue', $params)['data'] ?? [];

        if (isset($data['error']['message'])) {
            throw new \Exception($data['error']['message'] . ', code: ' . $data['error']['code']);
        }

        return $data;
    }

    public function retrieveBalance(): array
    {
        return $this->get('balance');
    }

    public function balance()
    {
        $result = [];

        foreach (config("private.wogi") as $countryIso => $credentials) {
            $this->initCredentials($countryIso);

            $data = $this->retrieveBalance()['data'] ?? [];
            if (isset($data['balance'])) {
                $result[] = [
                    'amount' => $data['balance']['amount'],
                    'currency' => $data['balance']['currency']['isoCode'],
                ];
            }
        }

        return $result;
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
        $headers = [];

        if (isset($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        $http = $this->getHttpClient($this->hostPrime, [
            'headers' => $headers,
            'auth' => [$this->uid, $this->secret]
        ]);

        try {
            $response = $http->request($method, $uri, $options);
            $responseContent = $response->getBody()->getContents();

            return json_decode($responseContent, true);
        } catch (\Exception $e) {
            Debugger::topup($e->getMessage());
            throw $e;
        }
    }
}
