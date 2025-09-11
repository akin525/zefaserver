<?php

namespace App\Http\Controllers;

use App\Jobs\WalletDebitCreditJob;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CashonrailsWebhook extends Controller
{
    public function index(Request $request)
    {

        $input = $request->all();
        Log::info("Cashonrails webhook received. Details: ". json_encode($input));
        Log::info("Cashonrails webhook IP captured. Details: ". $request->ip());

        $validator = Validator::make($input, [
            'event' => 'required',
            'data' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => implode(",",$validator->errors()->all()), 'error'=>$validator->errors()]);
        }

        $event = $input['event'];
        $data = $input['data'];
        if ($event == 'transaction') {
            $ref=$data['reference'];
            Log::info("Cashonrails webhook transaction received. Details: " . $ref);

            $deposit=Deposit::where('reference', $ref)->first();
            if($deposit){
                Log::info("Cashonrails webhook transaction duplicate. Details: " . $ref);
                return response()->json(['success' => true, 'message' => 'duplicate']);
            }

            if($data['status'] == 'success') {
                Log::info("Cashonrails webhook transaction successful. Details: " . $ref);

                $amount=$data['amount'];
                $currency=$data['currency'];
                $description=$input['sender_details'];
                $channel=$data['channel'];

                if($channel == "banktransfer"){
                    $customer=$input['data']['customerinfo'];
                    $user = User::where('email', $customer['email'])->first();

                    if($user) {
                        $wallet=Wallet::where([['user_id', $user->id], ['type', 'pocket']])->first();

                        if($wallet){
                            Log::info("Cashonrails webhook transaction successful and wallet found. Details: " . $ref);
                            Deposit::create([
                                'user_id' => $user->id,
                                'wallet_id' => $wallet->id,
                                'amount' => $amount,
                                'currency' => $currency,
                                'meta' => json_encode($description),
                                'reference' => $ref,
                                'status' => "successful"
                            ]);

                            WalletDebitCreditJob::dispatch($wallet, "deposit", "credit", $amount, $ref, "Deposit successful from ".$description['sender_account_name']);

                            $user->notify(new \App\Notifications\WalletCreditNotification(
                                "Transfer Received",
                                $amount,
                                Carbon::now(),
                                "Add Money, From ".$description['sender_account_name']
                            ));
                        }

                    }
                }
            }
        }


        return response()->json(['success' => true, 'message' => 'success']);
    }
}
