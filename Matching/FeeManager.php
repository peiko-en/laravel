<?php
declare(strict_types=1);

namespace App\Services\Matching;


use App\Models\{Pair, User};

class FeeManager
{
    private array $usersFees = [];

    public function getTakerFee(User $user, Pair $pair): float
    {
        return $this->getFee($user, $pair, true);
    }

    public function getMakerFee(User $user, Pair $pair): float
    {
        return $this->getFee($user, $pair);
    }

    public function getFee(User $user, Pair $pair, $isTaker = false): float
    {
        if (!isset($this->usersFees[$user->id])) {
            $feeService = $user->feeService();
            $this->usersFees[$user->id] = [
                'taker_fee' => $feeService->takerFee($pair),
                'maker_fee' => $feeService->makerFee($pair),
            ];
        }

        return $isTaker ? $this->usersFees[$user->id]['taker_fee'] : $this->usersFees[$user->id]['maker_fee'];
    }
}
