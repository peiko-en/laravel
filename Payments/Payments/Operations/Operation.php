<?php


namespace App\Services\Payments\Operations;


use App\Models\Entity\Transaction;
use Illuminate\Support\Str;

abstract class Operation
{
    /**
     * @var Transaction
     */
    protected $transaction;
    protected $resultDescription;

    /**
     * @param string $slug
     * @param array $parameters
     * @return Operation
     */
    public static function getInstance($slug, $parameters = [])
    {
        return app(__NAMESPACE__ . '\\' . Str::studly($slug), $parameters);
    }

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    public function getResultHeader()
    {
        return trans('payment.header-' . $this->transaction->getStatus());
    }

    public function getResultDescription()
    {
        return $this->resultDescription;
    }

    abstract function process();

    public function success()
    {
    }

    public function fail()
    {
    }
}