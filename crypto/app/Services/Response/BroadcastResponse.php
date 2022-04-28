<?php
declare(strict_types=1);

namespace App\Services\Crypto\Response;


class BroadcastResponse
{
    private string $txid = '';
    private bool $success = false;
    private bool $repeat = false;
    private string $sequence = '';

    public function setTxId(string $txid)
    {
        $this->txid = $txid;
    }

    public function getTxId(): string
    {
        return $this->txid;
    }

    public function success(bool $success = true)
    {
        $this->success = $success;
    }

    public function repeat()
    {
        $this->repeat = true;
    }

    public function needRepeat(): bool
    {
        return $this->repeat;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSequence(string $sequence)
    {
        $this->sequence = $sequence;
    }

    public function getSequence(): string
    {
        return $this->sequence;
    }
}
