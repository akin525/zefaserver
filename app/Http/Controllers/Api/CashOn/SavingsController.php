<?php

namespace App\Http\Controllers\Api\CashOn;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\BoostInterest;
use App\Models\Saving;
use App\Models\SavingInterest;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SavingsController extends Controller
{
    // Fetch all savings for the authenticated user, each with total interest accrued
    public function userSavings(Request $request)
    {
        $user = $request->user();
        $savings =Saving::where([['user_id', $user->id], ['status', 'active']])
            ->with(['interests', 'boost_interests'])
            ->get();

        $principal=0;
        $interest=0;
        $boost=0;
        $result = $savings->map(function($saving) use(&$principal, &$interest, &$boost) {
            $total_interest = SavingInterest::where('saving_id', $saving->id)->sum('amount');
            $total_boost = BoostInterest::where('saving_id', $saving->id)->sum('amount');
            $interest+=$total_interest;
            $boost+=$total_boost;
            $principal+=$saving->amount;
            return [
                'saving' => $saving
            ];
        });

        return response()->json([
            'status' => true,
            'principal' => $principal,
            'interest' => $interest,
            'boost' => $boost
        ]);
    }

    // Fetch all savings for the authenticated user
    public function savingsList(Request $request)
    {
        $user = $request->user();
        $savings = \App\Models\Saving::where('user_id', $user->id)
            ->select('id', 'name', 'amount', 'status')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'savings' => $savings,
        ]);
    }
    // Fetch all savings for the authenticated user
    public function savingsDetails(Request $request, $id)
    {
        $user = $request->user();
        $savings = Saving::where([['user_id', $user->id], ['id', $id]])
            ->with(['interests', 'boost_interests'])
            ->first();

        if($savings==null){
            return response()->json(['status' => false, 'message' => 'Saving not found'], 404);
        }

        $total_interest = SavingInterest::where('saving_id', $savings->id)->sum('amount');
        $total_boost = BoostInterest::where('saving_id', $savings->id)->sum('amount');

        return response()->json([
            'status' => true,
            'data' => [
                'saving' => $savings,
                'total_interest' => $total_interest,
                'total_boost' => $total_boost,
            ],
            'message'=>'fetched successfully'
        ]);
    }
    // Create a new saving
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|min:3',
            'amount'        => 'required|numeric|min:1',
            'frequency'     => 'required|string',
            'duration'      => 'required|integer|min:1',
            'auto_rollover' => 'required|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $amount = $request->amount;

        // Find user's pocket wallet
        $wallet = Wallet::where('user_id', $user->id)->where('type', 'pocket')->first();
        if (!$wallet || $wallet->balance < $amount) {
            return response()->json(['status' => false, 'message' => 'Insufficient funds in pocket wallet.'], 400);
        }

        DB::beginTransaction();
        try {
            $balanceBefore =$wallet->balance;

            // Debit pocket wallet
            $wallet->balance -= $amount;
            $wallet->available_balance =$wallet->balance;
            $wallet->save();

            // Create saving
            $saving = Saving::create([
                'user_id'       => $user->id,
                'name'          => $request->name,
                'amount'        => $amount,
                'frequency'     => $request->frequency,
                'duration'      => $request->duration,
                'auto_rollover' => $request->auto_rollover,
                'status'        => 'active',
                'starts_at'     => now(),
                'ends_at'       => now()->addDays($request->duration),
            ]);
            \Log::notice('Savings plan create successful ',['Savings'=>$saving]);

            Activity::create([
                'user_id' => $user->id,
                'type' => 'debit',
                'category' => 'savings_lock',
                'sub_category' => $request->frequency . '_savings',
                'amount' => $amount,
                'fee' => 0.00,
                'net_amount' => $amount,
                'currency' => 'NGN',
                'balance_before' => $balanceBefore,
                'balance_after' => $wallet->balance,
                'status' => 'completed',
                'title' => 'Savings Plan Created',
                'description' => "Locked ₦" . number_format($amount, 2) . " in '{$request->name}' savings plan",
                'metadata' => [
                    'saving_id' => $saving->id,
                    'saving_name' => $request->name,
                    'frequency' => $request->frequency,
                    'duration' => $request->duration,
                    'auto_rollover' => $request->auto_rollover,
                    'starts_at' => $saving->starts_at->toISOString(),
                    'ends_at' => $saving->ends_at->toISOString(),
                    'wallet_type' => 'pocket'
                ],
                'related_type' => 'App\Models\Saving',
                'related_id' => $saving->id,
                'initiated_by' => $user->id,
                'processed_at' => now(),
                'completed_at' => now(),
                'is_visible' => true,
                'is_reversible' => false,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_id' => $request->header('Device-ID')
            ]);
            DB::commit();
            return response()->json(['status' => true, 'data' => $saving, 'message'=>'Savings plan created successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                Activity::create([
                    'user_id' => $user->id,
                    'type' => 'debit',
                    'category' => 'savings_lock',
                    'amount' => $amount,
                    'status' => 'failed',
                    'title' => 'Savings Plan Creation Failed',
                    'description' => "Failed to create savings plan '{$request->name}'",
                    'metadata' => [
                        'error' => $e->getMessage(),
                        'attempted_amount' => $amount,
                        'saving_name' => $request->name
                    ],
                    'is_visible' => false
                ]);
            } catch (\Exception $activityException) {
                // Log activity creation failure but don't break the response
                \Log::error('Failed to create activity log: ' . $activityException->getMessage());
            }
            return response()->json(['status' => false, 'message' => 'Failed to create saving.', 'error' => $e->getMessage()], 500);
        }
    }

    // Fetch accrued interests with filters
    public function interests(Request $request)
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all');


        $query = SavingInterest::join('savings', 'saving_interests.saving_id', '=', 'savings.id')
            ->where('savings.user_id', $user->id)
            ->select('saving_interests.*');

        $now = now();
        switch ($filter) {
            case 'last_30_days':
                $query->where('saving_interests.accrued_at', '>=', $now->copy()->subDays(30));
                break;
            case 'last_month':
                $query->whereBetween('saving_interests.accrued_at', [
                    $now->copy()->subMonthNoOverflow()->startOfMonth(),
                    $now->copy()->subMonthNoOverflow()->endOfMonth()
                ]);
                break;
            case 'this_year':
                $query->whereYear('saving_interests.accrued_at', $now->year);
                break;
            case 'all':
            default:
                break;
        }

        $interests = $query->orderBy('saving_interests.accrued_at', 'desc')->get();

        $sumQuery = SavingInterest::join('savings', 'saving_interests.saving_id', '=', 'savings.id')
            ->where('savings.user_id', $user->id);

        switch ($filter) {
            case 'last_30_days':
                $sumQuery->where('saving_interests.accrued_at', '>=', $now->copy()->subDays(30));
                break;
            case 'last_month':
                $sumQuery->whereBetween('saving_interests.accrued_at', [
                    $now->copy()->subMonthNoOverflow()->startOfMonth(),
                    $now->copy()->subMonthNoOverflow()->endOfMonth()
                ]);
                break;
            case 'this_year':
                $sumQuery->whereYear('saving_interests.accrued_at', $now->year);
                break;
        }

        $sum = $sumQuery->sum('saving_interests.amount');

//        $activityQuery = Activity::where('user_id', $user->id)
//            ->where('category', 'savings_interest');
//
//        switch ($filter) {
//            case 'last_30_days':
//                $activityQuery->where('created_at', '>=', $now->copy()->subDays(30));
//                break;
//            case 'last_month':
//                $activityQuery->whereBetween('created_at', [
//                    $now->copy()->subMonthNoOverflow()->startOfMonth(),
//                    $now->copy()->subMonthNoOverflow()->endOfMonth()
//                ]);
//                break;
//            case 'this_year':
//                $activityQuery->whereYear('created_at', $now->year);
//                break;
//            case 'this_month':
//                $activityQuery->whereBetween('created_at', [
//                    $now->copy()->startOfMonth(),
//                    $now->copy()->endOfMonth()
//                ]);
//                break;
//            case 'last_7_days':
//                $activityQuery->where('created_at', '>=', $now->copy()->subDays(7));
//                break;
//        }
//
//        $activities = $activityQuery->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => true,
            'data' => $filter,
            'total_interest' => $sum,
            'interests' => $interests,
//            'activities' => $activities,
        ]);
    }


    private function getFilterPeriodDescription($filter)
    {
        switch ($filter) {
            case 'last_30_days':
                return 'Last 30 days';
            case 'last_month':
                return now()->copy()->subMonthNoOverflow()->format('F Y');
            case 'this_year':
                return now()->format('Y');
            case 'this_month':
                return now()->format('F Y');
            case 'last_7_days':
                return 'Last 7 days';
            case 'all':
            default:
                return 'All time';
        }
    }
}
