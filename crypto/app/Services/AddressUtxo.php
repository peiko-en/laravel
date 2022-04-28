<?php
declare(strict_types=1);

namespace App\Services\Crypto;


class AddressUtxo
{
    private string $address;
    private int $balance;
    /** @var UtxOutput[] */
    private array $utxo = [];

    public function __construct(string $address, int $balance = 0)
    {
        $this->address = $address;
        $this->balance = $balance;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function addUtxo(string $txHash, int $index, int $value, int $blockId)
    {
        $this->utxo[] = new UtxOutput($txHash, $index, $value, $blockId);
        $this->balance += $value;
    }

    /**
     * @param int $amount
     * @return UtxOutput[]
     */
    public function suitableOutputs(int $amount): array
    {
        if ($amount > $this->balance) {
            return [];
        }

        usort($this->utxo, fn(UtxOutput $out, UtxOutput $out2) => $out2->value <=> $out->value);

        $collectAmount = 0;
        $outputs = [];

        foreach ($this->utxo as $utxo) {
            if (!$utxo->value) {
                continue;
            }

            if ($collectAmount >= $amount) {
                break;
            }

            $collectAmount += $utxo->value;
            $outputs[] = $utxo;
        }

        return $outputs;
    }
}
