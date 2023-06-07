<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Accounts\CreateManualAccountFromTransactionAction;
use App\Models\Transaction;

class TransactionObserver
{
    public function created(Transaction $transaction)
    {
        $this->tryCreateManualAccountFromTransaction($transaction);
    }

    public function updated(Transaction $transaction)
    {
        $this->tryCreateManualAccountFromTransaction($transaction);
    }

    protected function tryCreateManualAccountFromTransaction(Transaction $transaction): void
    {
        if ($transaction->isCompletedExchangeWithdrawal()) {
            $action = app(CreateManualAccountFromTransactionAction::class);
            $action($transaction);
        }
    }
}
