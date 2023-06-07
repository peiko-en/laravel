<?php

namespace App\Jobs;

use App\Actions\ConvertTrade\CollectConvertTradeHistoryAction;
use App\Models\Account;
use App\Models\Exchange;
use App\Services\Exchanges\Api\AbstractExchangeApi;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CollectConvertTradeHistoryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     *
     * @param Account $account
     */
    public function __construct(private Account $account)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (strtolower($this->account->exchange->name) == Exchange::BINANCE) {
            try {
                $exchangeApi = AbstractExchangeApi::instanceFromAccount($this->account);

                $action = app(CollectConvertTradeHistoryAction::class);
                $action($exchangeApi, $this->account);
            } catch (Exception $e) {
                logger('CollectConvertTrade job accountId ' . $this->account->id . ' error: ' . $e->getMessage());
            }
        }
    }
}
