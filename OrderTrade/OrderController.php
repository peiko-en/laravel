<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\OrderRequest;
use App\Infrastructure\Response\SuccessResponse;
use App\Models\Order;
use App\Services\Exchanges\Order\OrderService;

class OrderController extends ApiController
{
    public function store(OrderRequest $request)
    {
        return $this->withTry(function () use ($request) {
            $orderService = app(OrderService::class, ['account' => $request->getDto()->account]);
            $orderService->store($request->getDto()->order);
            return new SuccessResponse(trans('order.order-created-successfully'));
        });
    }

    public function destroy(Order $order)
    {
        $this->authorize('delete', $order);

        return $this->withTry(function () use ($order) {
            $orderService = app(OrderService::class, ['account' => $order->account]);
            $orderService->cancel($order);
            return new SuccessResponse(trans('order.order-canceled-successfully'));
        });
    }
}
