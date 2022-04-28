<?php
declare(strict_types=1);

namespace App\Services\Crypto\Broadcast;


use App\Services\Crypto\Api\BlockchairApi;
use App\Services\Crypto\Response\BroadcastResponse;

class BlockchairBroadcast extends AbstractBroadcast
{
    public function send(string $hex, array $params = []): BroadcastResponse
    {
        $response = new BroadcastResponse();
        $res = BlockchairApi::make($this->crypto, $this->testnet)->pushTransaction($hex);
        $response->success($res);
        return $response;
    }
}
