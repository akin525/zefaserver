<?php

namespace App\Http\Controllers\Api\CashOn;

use App\Http\Controllers\Controller;
use App\Jobs\MakePayoutJob;
use App\Models\BankAccount;
use App\Models\DebitCard;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentMethodController extends Controller
{
    // Add bank account
    public function withdraw(Request $request)
    {
        $request->validate([
            'bank_account' => 'required|numeric',
            'amount' => 'required|numeric',
        ]);

        $ba = BankAccount::where('id', $request->bank_account)
        ->where('user_id', Auth::id())->first();
        if (!$ba) {
            return response()->json(['status' => false, 'message' => 'Bank account not found'], 404);
        }

        $wallet=Wallet::where('user_id', Auth::id())
        ->where('type', 'pocket')->first();

        if(!$wallet){
            return response()->json(['status' => false, 'message' => 'Wallet not found'], 404);
        }

        if($request->amount < 100){
            return response()->json(['status' => false, 'message' => 'Minimum amount is 100'], 400);
        }

        if($request->amount > $wallet->balance){
            return response()->json(['status' => false, 'message' => 'Insufficient funds'], 400);
        }

        $ref=Auth::id()."_".uniqid();

        $withdrawal = Withdrawal::create([
            'user_id' => Auth::id(),
            'wallet_id' => $wallet->id,
            'bank_account_id' => $request->bank_account,
            'amount' => $request->amount,
            'reference' => $ref,
        ]);

        MakePayoutJob::dispatch($withdrawal);

        return response()->json(['status' => true, 'data' => $withdrawal], 201);
    }

    public function addBankAccount(Request $request)
    {
        $request->validate([
            'bank_name' => 'required|string',
            'bank_code' => 'required|string',
            'account_name' => 'required|string',
            'account_number' => 'required|string|digits_between:10,12',
            'account_type' => 'nullable|string',
        ]);

        $ba=BankAccount::where([['user_id', Auth::id()], ['account_number', $request->account_number],['bank_code', $request->bank_code]])->first();

        if ($ba) {
            return response()->json(['status' => false, 'message' => 'Bank account already exists'], 400);
        }

        $bank = BankAccount::create([
            'user_id' => Auth::id(),
            'bank_name' => $request->bank_name,
            'bank_code' => $request->bank_code,
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'account_type' => $request->account_type,
        ]);

        return response()->json(['status' => true, 'data' => $bank], 201);
    }

    //list Bank account

    public function listBankAccounts()
    {
        $data=BankAccount::where('user_id', Auth::id())->get();
        return response()->json(['status' => true, 'data' => $data], 200);

    }
    // Delete bank account
    public function deleteBankAccount($id)
    {
        $bank = BankAccount::where('id', $id)->where('user_id', Auth::id())->first();

        if (!$bank) {
            return response()->json(['status' => false, 'message' => 'Bank account not found'], 404);
        }

        $bank->delete();

        return response()->json(['status' => true, 'message' => 'Bank account deleted']);
    }

    // Link debit card
    public function linkCard(Request $request)
    {
        $request->validate([
            'card_number' => 'required|string|min:12|max:19',
            'card_holder' => 'required|string',
            'expiry_month' => 'required|string',
            'expiry_year' => 'required|string',
            'provider' => 'nullable|string',
        ]);

        $maskedCard = '**** **** **** ' . substr($request->card_number, -4);

        $card = DebitCard::create([
            'user_id' => Auth::id(),
            'card_number' => $maskedCard,
            'card_holder' => $request->card_holder,
            'expiry_month' => $request->expiry_month,
            'expiry_year' => $request->expiry_year,
            'provider' => $request->provider,
            'is_linked' => true,
        ]);

        return response()->json(['status' => true, 'data' => $card], 201);
    }

    //list debit card

    public function listCard()
    {
        $data=DebitCard::where('user_id', Auth::id())->get();
        return response()->json(['status' => true, 'data' => $data], 200);

    }
    // Unlink debit card
    public function unlinkCard($id)
    {
        $card = DebitCard::where('id', $id)->where('user_id', Auth::id())->first();

        if (!$card) {
            return response()->json(['status' => false, 'message' => 'Card not found'], 404);
        }

        $card->is_linked = false;
        $card->save();

        return response()->json(['status' => true, 'message' => 'Card unlinked successfully']);
    }

}
