<?php

namespace App\Http\Controllers\Api\CashOn;

use App\Http\Controllers\Controller;
use App\Models\NextOfKin;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserEducation;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileSettingController extends Controller
{
    //

    public function getNextOfKin()
    {
        $nok = NextOfKin::where('user_id', Auth::id())->first();

        return response()->json([
            'status' => true,
            'data' => $nok,
        ]);
    }
    public function getProfile()
    {
        $profile = UserProfile::where('user_id', Auth::id())->first();

        return response()->json([
            'status' => true,
            'data' => $profile,
        ]);
    }
    public function getAddress()
    {
        $address = UserAddress::where('user_id', Auth::id())->first();

        return response()->json([
            'status' => true,
            'data' => $address,
        ]);
    }
    public function getEducation()
    {
        $education = UserEducation::where('user_id', Auth::id())->first();

        return response()->json([
            'status' => true,
            'data' => $education,
        ]);
    }

    public function saveNextOfKin(Request $request)
    {
        $request->validate([
            'full_name'    => 'required|string',
            'relationship' => 'required|string',
            'email'        => 'nullable|email',
            'phone'        => 'nullable|string',
            'gender'       => 'nullable|string',
            'notify'       => 'required|boolean',
        ]);

        $user = auth()->user();

        $kin = $user->nextOfKin()->updateOrCreate([], [
            'full_name'    => $request->full_name,
            'relationship' => $request->relationship,
            'email'        => $request->email,
            'phone'        => $request->phone,
            'gender'       => $request->gender,
            'notify'       => $request->notify,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Next of kin saved successfully',
            'data'    => $kin,
        ]);
    }

    public function saveProfileInfo(Request $request)
    {
        $request->validate([
            'state_of_origin'     => 'nullable|string',
            'lga'                 => 'nullable|string',
            'religion'            => 'nullable|string',
            'relationship_status' => 'nullable|string',
        ]);

        $user = auth()->user();

        $profile = $user->profile()->updateOrCreate([], [
            'state_of_origin'     => $request->state_of_origin,
            'lga'                 => $request->lga,
            'religion'            => $request->religion,
            'relationship_status' => $request->relationship_status,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Profile info saved successfully',
            'data'    => $profile,
        ]);
    }

    public function saveAddress(Request $request)
    {
        $request->validate([
            'street_address'     => 'required|string',
            'state_of_residence' => 'required|string',
            'city'              => 'required|string',
        ]);

        $user = auth()->user();

        $address = $user->address()->updateOrCreate([], [
            'street_address'     => $request->street_address,
            'state_of_residence' => $request->state_of_residence,
            'city'              => $request->city,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Address saved successfully',
            'data'    => $address,
        ]);
    }

    public function saveEducation(Request $request)
    {
        $request->validate([
            'education_level'   => 'nullable|string',
            'employment_status' => 'nullable|string',
            'income_range'     => 'nullable|string',
            'industry'        => 'nullable|string',
        ]);

        $user = auth()->user();

        $education = $user->education()->updateOrCreate([], [
            'education_level'   => $request->education_level,
            'employment_status' => $request->employment_status,
            'income_range'      => $request->income_range,
            'industry'         => $request->industry,
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Education & income info saved successfully',
            'data'    => $education,
        ]);
    }

    public function getAllInfo()
    {
     $user =User::with('nextOfKin', 'profile','address','education')
     ->where('id', Auth::id())->first();

     return response()->json([
         'status' => true,
         'data' => $user,
     ]);
    }
}
