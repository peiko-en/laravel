<?php


namespace App\Services\Crypto\Storage;


use App\Services\Crypto\CryptoAddress;

interface AddressStorageContract
{
    public function store(CryptoAddress $address, string $currency);
    public function export(AddressExporter $exporter, bool $isTest = false);
}
