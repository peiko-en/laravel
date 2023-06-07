<?php

declare(strict_types=1);

namespace App\DTO\Order;

use App\DTO\DtoObject;

/**
 * @property string $type
 * @property string $side
 * @property string $pair
 * @property float $quantity
 * @property float|null $price
 *
 * Class OrderDataDto
 * @package App\DTO
 */
class OrderDataDto extends DtoObject
{
    public string $type;
    public string $side;
    public string $pair;
    public float $quantity;
    public ?float $price = null;
}
