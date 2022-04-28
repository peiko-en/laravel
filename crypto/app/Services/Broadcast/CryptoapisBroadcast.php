<?php
declare(strict_types=1);

namespace App\Services\Crypto\Broadcast;


use App\Services\Crypto\Api\AbstractApi;
use App\Services\Crypto\Api\CryptoapisApi;
use App\Services\Crypto\Response\BroadcastResponse;

class CryptoapisBroadcast extends AbstractBroadcast
{
    public function send(string $hex, array $params = []): BroadcastResponse
    {
        /** @var CryptoapisApi $api */
        $api = AbstractApi::instance($this->crypto, ['testnet' => $this->testnet]);
        $res = $api->broadcastTx($hex);

        if (isset($res['error'])) {
            throw new \Exception(data_get($res, 'error.message') . ', code: ' . data_get($res, 'error.code'));
        }

        $response = new BroadcastResponse();
        $response->success();
        $response->setTxId(data_get($res, 'data.item.transactionId'));

        return $response;
    }

}
