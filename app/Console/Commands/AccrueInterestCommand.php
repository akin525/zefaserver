<?php

namespace App\Console\Commands;

use App\Jobs\AccrueInterestJob;
use App\Models\Saving;
use Illuminate\Console\Command;

class AccrueInterestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'samji:aic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command generate interest for savings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();
        $savings = Saving::where('status', 'active')->get();
        foreach ($savings as $saving) {
            $this->info('Running Interest Accrual for '.$saving->name);
            AccrueInterestJob::dispatch($saving);
            // Check maturity
            if ($saving->ends_at && $now->greaterThanOrEqualTo($saving->ends_at)) {
                $this->info($saving->name.' is now matured');
                $saving->status = 'matured';
                $saving->save();
            }
        }

        $this->info('Interest accrued successfully.');
    }
}
