<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\Saving;
use App\Models\SavingInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AccrueInterestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $saving;

    /**
     * Create a new job instance.
     */
    public function __construct(Saving $saving)
    {
        $this->saving = $saving;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Example: 10% annual interest, prorated by frequency
        $rate = 0.10;
        $frequency = $this->saving->frequency; // daily, weekly, monthly, etc.
        $amount = $this->saving->amount;
        $interest = 0;
        switch ($frequency) {
            case 'daily':
                $interest = ($rate / 365) * $amount;
                break;
            case 'weekly':
                $interest = ($rate / 52) * $amount;
                break;
            case 'monthly':
                $interest = ($rate / 12) * $amount;
                break;
            default:
                $interest = 0;
        }
        Log::info('Accruing interest', [
            'saving_id' => $this->saving->id,
            'interest' => $interest,
            'frequency' => $frequency,
            'principal' => $amount
        ]);

        if ($interest > 0) {
            $user =$this->saving->user;

            $savingInterest = SavingInterest::create([
                'saving_id' => $this->saving->id,
                'amount'    => round($interest, 2),
                'accrued_at'=> now(),
            ]);
//            $balanceBefore =$savingsWallet->balance;


            $activity = Activity::create([
                'user_id' => $user->id,
                'type' => 'credit',
                'category' => 'savings_interest',
                'sub_category' => $frequency . '_interest',
                'amount' => $savingInterest->amount,
                'fee' => 0.00,
                'net_amount' => $savingInterest->amount,
                'currency' => 'NGN',
                'balance_before' => 0.00,
                'balance_after' => 0.00,
                'status' => 'completed',
                'title' => 'Savings Interest Earned',
                'description' => "Interest earned on '{$this->saving->name}' savings plan",
                'metadata' => [
                    'saving_id' => $this->saving->id,
                    'saving_name' => $this->saving->name,
                    'saving_interest_id' => $savingInterest->id,
                    'interest_rate' => $rate,
                    'annual_rate_percentage' => ($rate * 100) . '%',
                    'principal_amount' => $amount,
                    'frequency' => $frequency,
                    'calculation_method' => $frequency . '_compound',
                    'accrual_period' => now()->format('Y-m-d'),
                    'days_since_start' => now()->diffInDays($this->saving->starts_at),
                    'wallet_type' => 'savings'
                ],
                'related_type' => 'App\Models\SavingInterest',
                'related_id' => $savingInterest->id,
                'processed_at' => now(),
                'completed_at' => now(),
                'is_visible' => true,
                'is_reversible' => false,
                'batch_id' => 'INTEREST_' . now()->format('Ymd_His') // Group interest accruals
            ]);

            // Notify user
            $user = $this->saving->user;
            $desc = ($this->saving->name ?? 'Saving #'.$this->saving->id) . ' | Interest';
            $user->notify(new \App\Notifications\InterestAccruedNotification(
                $savingInterest->amount,
                $savingInterest->accrued_at->format('M d, Y, H:i'),
                $desc
            ));
        }
    }
}
