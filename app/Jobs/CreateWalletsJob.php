<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Wallet;
use App\Services\CashonrailsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateWalletsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Create Savings Wallet if not exists
        Wallet::firstOrCreate([
            'user_id' => $this->user->id,
            'type'    => 'savings',
        ], [
            'balance' => 0,
            'currency' => 'NGN',
        ]);

        // Create Pocket Wallet if not exists
        $pocketWallet = Wallet::firstOrCreate([
            'user_id' => $this->user->id,
            'type'    => 'pocket',
        ], [
            'balance' => 0,
            'currency' => 'NGN',
        ]);

        $cashonrailsService = new CashonrailsService();
        $result = $cashonrailsService->createVirtualAccount($this->user);
        Log::info($result);

        if($result['success']) {
            // Generate a virtual account for the user linked to the pocket wallet
            \App\Models\VirtualAccount::create([
                'user_id'        => $this->user->id,
                'wallet_id'      => $pocketWallet->id,
                'account_number' => $result['data']['accountNumber'],
                'bank_name'      => $result['data']['bankName'],
                'account_name'   => $result['data']['accountName'],
                'status'         => 'active',
                'metadata'       => null,
            ]);
        }


    }
}
