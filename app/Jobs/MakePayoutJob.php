<?php

namespace App\Jobs;

use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\CashonrailsService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class MakePayoutJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */

    public $withdrawal;
    public function __construct(Withdrawal $withdrawal)
    {
        $this->withdrawal = $withdrawal;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        Log::info('Processing withdrawal');

        $wallet=Wallet::find($this->withdrawal->wallet_id);

        if(!$wallet){
            $this->withdrawal->status = 'failed';
            $this->withdrawal->meta = json_encode(['message' => 'Wallet not found']);
            $this->withdrawal->save();
            return;
        }

        if($wallet->balance < $this->withdrawal->amount){
            $this->withdrawal->status = 'failed';
            $this->withdrawal->meta = json_encode(['message' => 'Insufficient funds']);
            $this->withdrawal->save();
            return;
        }

        WalletDebitCreditJob::dispatchSync($wallet, "withdrawal", "debit", $this->withdrawal->amount, $this->withdrawal->reference, "Transfer to ".$this->withdrawal->bankAccount->account_name);

        $cashonrailsService = new CashonrailsService();
        $result = $cashonrailsService->makePayout($this->withdrawal);
        Log::info($result);

        if($result['success']) {
            $this->withdrawal->status = 'completed';
            $this->withdrawal->meta = json_encode($result['data']);
            $this->withdrawal->save();

            $user = $this->withdrawal->user;
            $user->notify(new \App\Notifications\WalletCreditNotification(
                "Withdrawal Successful",
                $this->withdrawal->amount,
                Carbon::now(),
                "Withdraw, To ".$this->withdrawal->bankAccount->account_name
            ));
        }else{
            if(!str_contains($result['message'],'ERROR:')){
                $this->withdrawal->status = 'failed';
                $this->withdrawal->refunded = 'yes';
                $this->withdrawal->meta = json_encode($result);
                $this->withdrawal->save();

                $user = $this->withdrawal->user;
                $user->notify(new \App\Notifications\WalletCreditNotification(
                    "Withdrawal Failed",
                    $this->withdrawal->amount,
                    Carbon::now(),
                    "Refunded, To ".$this->withdrawal->bankAccount->account_name
                ));

                WalletDebitCreditJob::dispatchSync($wallet, "refund", "credit", $this->withdrawal->amount, 'refund_'.$this->withdrawal->reference, "Refund of failed Transfer to ".$this->withdrawal->bankAccount->account_name);
            }
        }

    }
}
