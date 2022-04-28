<?php
declare(strict_types=1);

namespace App\Services\Crypto;


class Transaction
{
    private string $id;
    private string $hex;
    private int $fee;
    private array $params = [];

    public function __construct(string $id = '', string $hex = '', int $fee = 0)
    {
        $this->id = $id;
        $this->hex = $hex;
        $this->fee = $fee;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getHex(): string
    {
        return $this->hex;
    }

    public function getSize(): int
    {
        return (int) ceil(strlen($this->hex) / 2);
    }

    public function getFee(): int
    {
        return $this->fee;
    }

    public function setParams(array $params): self
    {
        $this->params = $params;
        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }
}
