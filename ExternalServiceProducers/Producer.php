<?php

namespace App\Services\Producers;


use App\Models\Entity\Service;
use App\Models\Entity\TopupTransaction;
use App\Services\Topup\TopupAbstract;

class Producer
{
    protected $producer;
    protected static $availableList = [
        Service::EXT_FPAY,
        Service::EXT_ELOAD,
        Service::EXT_CSQ,
        Service::EXT_EMIDA,
        Service::EXT_BE_CHARGE,
        Service::EXT_PREPAID_FORGE,
        Service::EXT_SVS,
        Service::EXT_ESIMGO,
        Service::EXT_WOGI,
    ];

    public static function instance($producer)
    {
        return new static($producer);
    }

    public function __construct($producer)
    {
        $this->producer = $producer;
    }

    public function isAvailable()
    {
        return in_array($this->producer, static::$availableList);
    }

    public function import()
    {
        /** @var ImportAbstract $import */
        $import = app($this->makeNamespace('Import'), ['producer' => $this->producer]);
        $import->execute();
    }

    public function topup(TopupTransaction $transaction)
    {
        return $this->topupServiceInstance()->topup($transaction);
    }

    /**
     * @return TopupAbstract
     */
    public function topupServiceInstance()
    {
        if ($this->isAvailable()) {
            return app($this->makeNamespace('Topup'));
        } else {
            return TopupAbstract::getInstance($this->producer);
        }
    }

    public function apiInstance()
    {
        return app($this->makeNamespace('Api'));
    }

    protected function makeNamespace($className)
    {
        return __NAMESPACE__ . '\\' . $this->producer . '\\' . $className;
    }
}
