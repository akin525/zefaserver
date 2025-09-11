<?php

namespace App\Http\Controllers\Api\CashOn;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\VirtualAccount;
use App\Services\CashonrailsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    protected CashonrailsService $cashonrailsService;

    public function __construct(CashonrailsService $cashonrailsService)
    {
        $this->cashonrailsService = $cashonrailsService;
    }
    // Fetch all wallets for authenticated user
    public function index(Request $request)
    {
        $wallets = Wallet::where('user_id', auth()->id())->get();
        return response()->json(['status' => true, 'wallets' => $wallets]);
    }

    // Fetch all virtual accounts for authenticated user
    public function virtualAccounts(Request $request)
    {
        $accounts = VirtualAccount::where('user_id', auth()->id())->get();
        return response()->json(['status' => true, 'virtual_accounts' => $accounts]);
    }

    public function getBankList(): JsonResponse
    {
        try {
            $result = $this->cashonrailsService->getBankList();
            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve bank list'
            ]);
        }
    }
    public function validateAccountName(Request $request): JsonResponse
    {
        $request->validate([
            'account_number' => 'required|string|digits:10',
            'bank_code' => 'required|string|digits:6',
            'currency' => 'sometimes|string|in:NGN'
        ]);

        try {
            $result = $this->cashonrailsService->validateAccountName(
                $request->account_number,
                $request->bank_code,
                $request->currency ?? 'NGN'
            );

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
