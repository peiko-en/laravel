<?php


namespace App\Services\Payments;


class Coinpayments extends Payment
{
    protected $currency = 'BTC';
    protected $roundPrecision = 8;
    protected $merchant;
    protected $secret;
    protected $transactionTokenParam = 'custom';

    public function actionUrl(): string
    {
        return 'https://www.coinpayments.net/index.php';
    }

    public function formInputs(): array
    {
        return [
            'cmd' => '_pay',
            'reset' => 1,
            'merchant' => $this->merchant,
            'item_name' => e($this->transaction->description),
            'amountf' => $this->transaction->cost,
            'currency' => $this->transaction->currency,
            'success_url' => $this->getSuccessUrl(),
            'cancel_url' => $this->getFailUrl(),
            'ipn_url' => $this->getProcessUrl(),
            'want_shipping' => 0,
            $this->transactionTokenParam => $this->transaction->token
        ];
    }

    public function verify(string $case = null): bool
    {
        $request = request();

        $status = $request->get('status');

        if ($status < 100) {
            $this->debug('begin verify end with status ' . $status);
            exit;
        }

        $this->debug('begin coinpayment verify');

        if (!isset($_SERVER['HTTP_HMAC']) || empty($_SERVER['HTTP_HMAC'])) {
            $this->debug('no HTTP_HMAC');
            return false;
        }

        $merchant = $request->get('merchant');
        if (!$merchant || $merchant != $this->merchant) {
            $this->debug('wrong merchant');
            return false;
        }

        $requestRawBody = $request->getContent();

        if ($requestRawBody === false || empty($requestRawBody)) {
            $this->debug('request is empty');
            return false;
        }

        $hmac = hash_hmac("sha512", $requestRawBody, $this->secret);
        if ($hmac != $_SERVER['HTTP_HMAC']) {
            $this->debug('hmac == '.$hmac.' is wrong!');
            return false;
        }

        if (!$this->isValidCost($request->get('amount1'))) {
            $this->debug('amount1 != transaction amount');
            return false;
        }

        $this->debug('end coinpayment verified');
        return true;
    }

    public function getSuccessUrl()
    {
        return route('payment.case', [
            'slug' => $this->slug,
            'case' => self::CASE_SUCCESS,
            $this->transactionTokenParam => $this->transaction->token,
        ]);
    }

    public function getFailUrl()
    {
        return route('payment.case', [
            'slug' => $this->slug,
            'case' => self::CASE_FAIL,
            $this->transactionTokenParam => $this->transaction->token,
        ]);
    }
}