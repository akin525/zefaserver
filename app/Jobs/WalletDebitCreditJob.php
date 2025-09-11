<?php

namespace App\Jobs;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WalletDebitCreditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public $wallet;
    public $transaction_type;
    public $type;
    public $amount;
    public $reference;
    public $note;

    public function __construct(Wallet $wallet,  $transaction_type, $type, $amount, $reference, $note)
    {
        $this->wallet = $wallet;
        $this->transaction_type = $transaction_type;
        $this->type = $type;
        $this->amount = $amount;
        $this->reference = $reference;
        $this->note = $note;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $w = $this->wallet;
        $note = $this->note;
        $transaction_type = $this->transaction_type;
        $type = $this->type;
        $amount = $this->amount;
        $reference = $this->reference;

        $w->refresh();

        Log::info("WalletDebitCreditJob: $type $transaction_type $amount $reference $note");

        $balance = $w->balance;

        if ($type == "credit") {
            $newBalance = $balance + round($amount, 2);
        } else {
            $newBalance = $balance - round($amount, 2);
        }

        WalletTransaction::create([
            'user_id' => $w->user_id,
            'wallet_id' => $w->id,
            'currency' => $w->currency,
            'type' => $type,
            'source' => $transaction_type,
            'amount' => round($amount, 2),
            'note' => $note,
            'previous_balance' => $balance,
            'new_balance' => $newBalance,
            'reference' => $reference,
        ]);

        $w->balance = $newBalance;
        $w->available_balance = $balance;

        $w->save();
    }
}
