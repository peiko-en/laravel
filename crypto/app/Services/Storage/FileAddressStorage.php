<?php
declare(strict_types=1);

namespace App\Services\Crypto\Storage;


use App\Helpers\FileHelper;
use App\Services\Crypto\CryptoAddress;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class FileAddressStorage implements AddressStorageContract
{
    private Filesystem $disk;
    private string $path = 'address/';
    private string $extension = 'txt';

    public function __construct()
    {
        $this->disk = Storage::disk('private');
    }

    public function getFullPath(string $currency, bool $isTest = false): string
    {
        return $this->disk->path($this->relativePath($currency, $isTest));
    }

    public function store(CryptoAddress $address, string $currency)
    {
        $this->disk->append($this->relativePath($currency, $address->isTest()), (string) $address);
    }

    public function export(AddressExporter $exporter, bool $isTest = false)
    {
        if ($exporter->isDisabled()) {
            return;
        }

        $originPath = $this->relativePath($exporter->getCurrency(), $isTest);
        $processPath = $this->processPath($exporter->getCurrency(), $isTest);

        if (!$this->disk->exists($processPath)) {
            if (!$this->disk->exists($originPath)) {
                return;
            }

            if (!$this->disk->copy($originPath, $processPath)) {
                throw new \Exception(trans('sys.copy-addresses-failed'));
            }

            if (!FileHelper::isIdentical($this->disk->path($originPath), $this->disk->path($processPath))) {
                throw new \Exception(trans('sys.copied-files-not-equals'));
            }

            $this->disk->delete($originPath);
        }

        $lines = file($this->disk->path($processPath));

        $exporter->clear();
        foreach ($lines as $line) {
            $exporter->add(CryptoAddress::restoreFromString($line, $isTest));
        }

        try {
            $response = $exporter->export();

            if ($response->isSuccess()) {
                $this->disk->delete($processPath);
            } else {
                throw new \Exception($response->getMessage());
            }
        } catch (\Throwable $e) {
            logger($e->getMessage());

            throw $e;
        }
    }

    private function relativePath(string $currency, bool $isTest = false): string
    {
        return $this->buildPath($currency, $isTest);
    }

    private function processPath(string $currency, bool $isTest = false): string
    {
        return $this->buildPath($currency, $isTest, '_process');
    }

    private function buildPath(string $currency, bool $isTest = false, string $end = ''): string
    {
        $path = $this->path . $currency;
        if ($isTest) {
            $path .= '_test';
        }

        if ($end) {
            $path .= $end;
        }

        return $path . '.' . $this->extension;
    }
}
