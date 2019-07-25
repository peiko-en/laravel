<?php


namespace App\Services\Payments;


class Advcash extends Payment
{
    protected $currency = 'USD';
    protected $accountEmail;
    protected $sciName;
    protected $secret;
    protected $transactionTokenParam = 'custom_field_token';

    public function actionUrl(): string
    {
        return 'https://wallet.advcash.com/sci/';
    }

    public function formInputs(): array
    {
        $inputs = [
            'ac_account_email' => $this->accountEmail,
            'ac_sci_name' => e($this->sciName),
            'ac_amount' => round($this->transaction->cost, 2),
            'ac_currency' => $this->transaction->currency,
            'ac_comments' => e($this->transaction->description),
            'ac_order_id' => $this->transaction->id,
            'ac_success_url' => $this->getSuccessUrl(),
            'ac_success_url_method' => 'POST',
            'ac_status_url' => $this->getProcessUrl(),
            'ac_status_url_method' => 'POST',
            'ac_fail_url' => $this->getFailUrl(),
            'ac_fail_url_method' => 'POST',
            'ac_sign' => 'POST',
            $this->transactionTokenParam => $this->transaction->token,
        ];

        $inputs['ac_sign'] = $this->createSign([
            $inputs['ac_account_email'],
            $inputs['ac_sci_name'],
            $inputs['ac_amount'],
            $inputs['ac_currency'],
            $this->secret,
            $inputs['ac_order_id'],
        ]);

        return $inputs;
    }

    public function verify(string $case = null): bool
    {
        if (!$this->isValidCost(request('PAYMENT_AMOUNT'))) {
            return false;
        }

        $request = request();

        $sign = $this->createSign([
            $request->get('ac_transfer'),
            $request->get('ac_start_date'),
            $request->get('ac_sci_name'),
            $request->get('ac_src_wallet'),
            $request->get('ac_dest_wallet'),
            $request->get('ac_order_id'),
            $request->get('ac_amount'),
            $request->get('ac_merchant_currency'),
            $this->secret,
        ]);

        return $request->get('ac_hash') === $sign;
    }

    private function createSign($values)
    {
        return hash('sha256', implode(':', $values));
    }
}