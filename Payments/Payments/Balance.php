<?php


namespace App\Services\Payments;


use App\Models\Entity\User;
use Illuminate\Contracts\Auth\Authenticatable;

class Balance extends Payment
{
    protected $currency = 'BTC';
    protected $roundPrecision = 8;
    /**
     * @var User|Authenticatable
     */
    protected $user;

    public function __construct(?Authenticatable $user, $config = [])
    {
        $this->user = $user;
        parent::__construct($config);
    }

    public function verify(string $case = null): bool
    {
        if (!$this->transaction->cost || !$this->user->balance->validate($this->transaction->cost)) {
            $this->debug('not valid cost: ' . $this->transaction->cost);
            return false;
        }

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