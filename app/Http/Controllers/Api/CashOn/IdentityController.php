<?php

namespace App\Http\Controllers\Api\CashOn;

use App\Http\Controllers\Controller;
use App\Models\VerificationLog;
use App\Services\SprintCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class IdentityController extends Controller
{
    protected $sprintCheck;

    public function __construct(SprintCheckService $sprintCheck)
    {
        $this->sprintCheck = $sprintCheck;
    }

    public function verifyIdentity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type'     => 'required|in:bvn,nin,voters,passport,driver',
            'number'   => 'required|digits:11',
        ]);

        if ($request->type=="passport" || $request->type =="driver"){
            $validator = Validator::make($request->all(), [
                'firstname'     => 'required|string',
                'lastname'     => 'required|string',
                'dob'     => 'required',
            ]);
        }

        if ($validator->fails()) {
            Log::error("validation_error ". $validator->errors());
            return response()->json([
                'status'  => false,
                'message' => 'Invalid input',
                'errors'  => $validator->errors(),
            ]);
        }

        $user = auth()->user();


        $check=VerificationLog::where('verification_number', $request->number)
            ->where('verification_success','!=', false)->first();
        if ($check){
            $data=$check->response_data;
            return response()->json([
                'status' => true,
                'message' => 'Verified number already exists',
                'data' => $data['data'],
            ]);
        }
        $identify = Str::uuid()->toString();
        if ($request->type === 'bvn') {
            $result = $this->sprintCheck->verifyBVN($request->number, $identify);
        } else if ($request->type==='nin') {
            $result = $this->sprintCheck->verifyNIN($request->number, $identify);
        }else if ($request->type==='voters'){
            $result = $this->sprintCheck->verifyVoters($request->number, $identify);
        } else if ($request->type==='passport'){
            $result = $this->sprintCheck->verifyPassport($request->firstname, $request->lastname, $request->dob,$request->number, $identify);

        }

        return $result;
    }
    public function verificationHistory(Request $request)
    {
        $user = auth()->user();
        $data=VerificationLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);

    }
}
