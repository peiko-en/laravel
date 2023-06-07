<?php

declare(strict_types=1);

namespace App\DTO\Order;

use App\DTO\DtoObject;
use App\Models\Account;

/**
 * @property int $userId
 * @property int $account_id
 * @property Account $account
 * @property int $exchange_id
 * @property OrderDataDto $order
 *
 * Class OrderDto
 * @package App\DTO
 */
class OrderDto extends DtoObject
{
    public int $userId;
    public int $account_id;
    public Account $account;
    public int $exchange_id;
    public OrderDataDto $order;
}
