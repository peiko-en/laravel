<?php


namespace App\Services\Payments;


class Test extends Payment
{
    protected $currency = 'USD';

    public function verify(string $case = null): bool
    {
        return true;
    }

    public function actionUrl(): string
    {
        return route('payment.case', ['slug' => $this->slug, self::CASE_PROCESS]);
    }

    public function formInputs(): array
    {
        return [
            $this->transactionTokenParam => $this->transaction->token
        ];
    }
}