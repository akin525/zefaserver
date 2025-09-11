<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\AuditHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminFundController extends Controller
{
    function fundUserWallet(Request $request): JsonResponse
    {
        try {
            // Validate incoming request
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'amount' => 'required|numeric|min:0.01',
                'type' => 'required|string|in:savings,pocket',
                'reason' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                Log::error('Wallet funding validation failed', [
                    'errors' => $validator->errors(),
                    'request_data' => $request->all(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Begin database transaction
            DB::beginTransaction();

            // Find the user
            $user = Wallet::where('user_id', $request->user_id)
                ->where('type', $request->type)
                ->lockForUpdate()
                ->first();

            if (!$user) {
                DB::rollBack();

                Log::warning('User not found for wallet funding', [
                    'user_id' => $request->user_id,
                    'type' => $request->type,
                    'ip_address' => $request->ip(),
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'User not found or invalid user type'
                ]);
            }

            // Store original balance for audit
            $originalBalance = $user->balance;
            $originalAvailableBalance = $user->available_balance;
            $fundingAmount = (float) $request->amount;

            // Update user balance
            $user->balance += $fundingAmount;
            $user->available_balance += $fundingAmount;
            $user->save();

            AuditHelper::logCreated($user, [
                'funding_amount' => $fundingAmount,
                'previous_balance' => $originalBalance,
                'new_balance' => $user->balance,
                'previous_available_balance' => $originalAvailableBalance,
                'new_available_balance' => $user->available_balance,
                'user_type' => $request->type,
                'reason' => $request->reason ?? 'Manual wallet funding',
                'performed_by' => auth()->id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ], 'User wallet funded successfully');

            DB::commit();

            Log::info('Wallet funded successfully', [
                'user_id' => $user->user_id,
                'amount' => $fundingAmount,
                'new_balance' => $user->balance,
                'performed_by' => auth()->id(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Wallet funded successfully',
                'data' => [
                    'user_id' => $user->user_id,
                    'previous_balance' => $originalBalance,
                    'amount_funded' => $fundingAmount,
                    'new_balance' => $user->balance,
                    'available_balance' => $user->available_balance,
                    'transaction_time' => now()->toISOString(),
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error funding user wallet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);
            AuditHelper::log([
                'action' => 'wallet_funding_failed',
                'description' => 'Failed to fund user wallet due to system error',
                'metadata' => [
                    'user_id' => $request->user_id,
                    'amount' => $request->amount,
                    'type' => $request->type,
                    'error' => $e->getMessage(),
                    'performed_by' => auth()->id(),
                    'ip_address' => $request->ip(),
                ],
                'risk_level' => 'high'
            ]);
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while processing the request'
            ], 500);
        }
    }

}
