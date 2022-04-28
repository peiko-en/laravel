<?php
declare(strict_types=1);

namespace App\Services\Crypto;


class UtxOutput
{
    public string $hash = '';
    public int $index = 0;
    public int $value = 0;
    public int $blockId = 0;

    public function __construct(string $hash, int $index, int $value, int $blockId)
    {
        $this->hash = $hash;
        $this->index = $index;
        $this->value = $value;
        $this->blockId = $blockId;
    }
}
