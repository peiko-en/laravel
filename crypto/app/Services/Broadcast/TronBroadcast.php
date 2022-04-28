<?php
declare(strict_types=1);

namespace App\Services\Crypto\Broadcast;


use App\Helpers\DIHelper;
use App\Services\Crypto\Response\BroadcastResponse;

class TronBroadcast extends AbstractBroadcast
{
    public function send(string $hex, array $params = []): BroadcastResponse
    {
        DIHelper::tronWallet()->broadcastTransaction($params['rawTransaction']);
        $response = new BroadcastResponse();
        $response->success();

        return $response;
    }
}
