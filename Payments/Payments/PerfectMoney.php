<?php


namespace App\Services\Payments;


class PerfectMoney extends Payment
{
    protected $currency = 'USD';
    protected $payeeAccount;
    protected $secret;
    protected $transactionTokenParam = 'TRANSACTION_REF';

    public function actionUrl(): string
    {
        return 'https://perfectmoney.is/api/step1.asp';
    }

    public function formInputs(): array
    {
        return [
            'PAYEE_ACCOUNT' => $this->payeeAccount,
            'PAYMENT_AMOUNT' => round($this->transaction->cost, 2),
            'PAYMENT_ID' => $this->transaction->id,
            'PAYEE_NAME' => e($this->transaction->description),
            'PAYMENT_UNITS' => $this->transaction->currency,
            'SUGGESTED_MEMO' => e($this->transaction->description),
            'SUGGESTED_MEMO_NOCHANGE' => 1,
            'PAYMENT_URL' => $this->getSuccessUrl(),
            'PAYMENT_URL_METHOD' => 'POST',
            'STATUS_URL' => $this->getProcessUrl(),
            'NOPAYMENT_URL' => $this->getFailUrl(),
            'NOPAYMENT_URL_METHOD' => 'POST',
            'BAGGAGE_FIELDS' => $this->transactionTokenParam,
            $this->transactionTokenParam => $this->transaction->token
        ];
    }

    public function verify(string $case = null): bool
    {
        $request = request();

        if (!$this->isValidCost($request->get('PAYMENT_AMOUNT'))) {
            $this->debug('not valid cost: ' . $request->get('PAYMENT_AMOUNT'));
            return false;
        }

        if ($request->get('PAYEE_ACCOUNT') != $this->payeeAccount) {
            $this->debug('not valid PAYEE_ACCOUNT: ' . $request->get('PAYEE_ACCOUNT'));
            return false;
        }

        return $request->get('V2_HASH') === $this->createSignature();
    }

    private function createSignature()
    {
        $request = request();

        $token = $request->get('PAYMENT_ID') .':'. $request->get('PAYEE_ACCOUNT').':'.
            $request->get('PAYMENT_AMOUNT') .':'. $request->get('PAYMENT_UNITS').':'.
            $request->get('PAYMENT_BATCH_NUM') .':'.
            $request->get('PAYER_ACCOUNT') .':'. strtoupper(md5($this->secret)).':'.
            $request->get('TIMESTAMPGMT');

        return strtoupper(md5($token));
    }
}