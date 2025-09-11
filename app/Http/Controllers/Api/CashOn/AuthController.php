<?php

namespace App\Http\Controllers\Api\CashOn;

use App\Http\Controllers\Controller;
use App\Jobs\CreateWalletsJob;
use App\Models\FileUpload;
use App\Models\PasswordReset;
use App\Models\User;
use App\Models\VerificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    //
    // Login
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'password' => 'required|string',

        ]);

        if ($validator->fails()) {
            Log::error("validation_error " . $validator->errors());
            return response()->json(['status' => false, 'message' => 'Incomplete request', 'error' => $validator->errors()], 401);
        }
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid email or password'
            ], 401);
        }

        $user = auth()->user();


        Log::info("Login successful " . $user->email);


        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'user' => $user,
            ],
        ]);
    }


    function dashboard()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get verification statistics
            $totalVerifications = VerificationLog::where('user_id', $user->id)->count();
            $todayVerifications = VerificationLog::where('user_id', $user->id)
                ->whereDate('created_at', today())
                ->count();

            $pendingVerifications = VerificationLog::where('user_id', $user->id)
                ->where('status', 'pending')
                ->count();

            $approvedVerifications = VerificationLog::where('user_id', $user->id)
                ->where('status', 'approved')
                ->count();

            $rejectedVerifications = VerificationLog::where('user_id', $user->id)
                ->where('status', 'rejected')
                ->count();

            // Get recent verifications
            $recentVerifications = VerificationLog::where('user_id', $user->id)
//                ->with(['customer']) // Assuming you have a customer relationship
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            // Calculate performance metrics
            $thisWeekVerifications = VerificationLog::where('user_id', $user->id)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count();

            $lastWeekVerifications = VerificationLog::where('user_id', $user->id)
                ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
                ->count();

            // Calculate approval rate
            $approvalRate = $totalVerifications > 0
                ? round(($approvedVerifications / $totalVerifications) * 100, 1)
                : 0;

            // Priority items (high-value or urgent verifications)
            $priorityItems = VerificationLog::where('user_id', $user->id)
                ->where('status', 'pending')
                ->where(function($query) {
                    $query->where('priority', 'high')
                        ->orWhere('created_at', '<', now()->subHours(24)); // Older than 24 hours
                })
//                ->with(['customer'])
                ->orderBy('created_at', 'asc')
                ->limit(10)
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'user' => $user,
                    'stats' => [
                        'total' => $totalVerifications,
                        'totalToday' => $todayVerifications,
                        'pending' => $pendingVerifications,
                        'approved' => $approvedVerifications,
                        'rejected' => $rejectedVerifications,
                        'approvalRate' => $approvalRate,
                        'thisWeek' => $thisWeekVerifications,
                        'lastWeek' => $lastWeekVerifications,
                        'weeklyTrend' => $thisWeekVerifications - $lastWeekVerifications,
                    ],
                    'recentVerifications' => $recentVerifications,
                    'priorityItems' => $priorityItems,
                ],
                'message' => 'Dashboard data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Step 1: Verify OTP
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|unique:users,phone',
        ]);
        if ($validator->fails()) {
            Log::error("validation_error " . $validator->errors());
            return response()->json(['status' => false, 'message' => 'Incomplete request', 'error' => $validator->errors()], 401);
        }
        // Example: generate a 4-6 digit OTP
        $otp = rand(1000, 9999);

        // Save OTP in cache or DB; here simplified
        Cache::put('otp_' . $request->phone, $otp, now()->addMinutes(5));

        // TODO: Send OTP via SMS here

        return response()->json([
            'status' => true,
            'message' => 'OTP sent successfully',
            'otp' => $otp
        ]);
    }

    // Step 2: Verify OTP
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string',
        ]);

        // Check OTP from cache or DB
        $storedOtp = Cache::get('otp_' . $request->phone);
//        $storedOtp = "1234"; // Example

        if ($request->otp != $storedOtp) {
            Log::error("Invalid OTP " . $request->phone);
            return response()->json(['status' => false, 'message' => 'Invalid OTP']);
        }

        // Create or find user record
        $user = User::firstOrCreate(['phone' => $request->phone]);

        Log::info("creating user and wallets " . $user->phone);

        // Dispatch job to create wallets
        \App\Jobs\CreateWalletsJob::dispatch($user);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'status' => true,
            'message' => 'Phone number verified',
            'access_token' => $token,
            'user' => $user,
        ]);
    }


    public function verifyIdentity(Request $request)
    {
        $request->validate([
            'verification_type' => 'required|in:bvn,nin',
            'number' => 'required|string',
        ]);

        $user = auth()->user();
        $user->verification_type = $request->verification_type;
        $user->verification_number = $request->number;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Identity verified successfully',
            'user' => $user,
        ]);
    }

    // Step 4: Save basic info
    public function saveBasicInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            Log::error("validation_error " . $validator->errors());
            return response()->json(['status' => false, 'message' => 'Incomplete request', 'error' => $validator->errors()], 401);
        }

       $user= User::create([
            'fist_name'=>$request->first_name,
            'last_name'=>$request->last_name,
            'phone'=>$request->phone,
            'email'=>$request->email,
            'password'=>Hash::make($request->password),
        ]);



        // ✅ You can also send this token to user via SMS or email here
        Log::info("Registration Complete", ['user' => $user]);
        return response()->json([
            'status' => true,
            'message' => 'Basic information saved, device registered. Please verify your device.',
            'user' => $user,
        ]);
    }

    // Step 5: Upload ID document
    public function uploadId(Request $request)
    {
        $request->validate([
            'document_type' => 'required|in:nin,passport,driving_license',
            'document_image' => 'required',
        ]);

        $user = auth()->user();

//        $path = $request->file('document_image')->store('ids', 'public');

        $user->document_type = $request->document_type;
        $user->document_path = $request->document_image;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Document uploaded',
            'path' => $request->document_image,
        ]);
    }

    // Step 6: Upload scanned document (if separate)
    public function uploadScan(Request $request)
    {
        $request->validate([
            'scan_image' => 'required|image|max:2048',
        ]);

        $user = auth()->user();

        $path = $request->file('scan_image')->store('scans', 'public');

        $user->scan_path = $path;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Scan uploaded',
            'path' => $path,
        ]);
    }

    // Step 7: Setup PIN
    public function setupPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|digits:4',
        ]);

        $user = auth()->user();
        $user->pin = Hash::make($request->pin);
        $user->save();
        Log::info("Set PIN successfully", ['user' => $user]);

        return response()->json([
            'status' => true,
            'message' => 'PIN set successfully',
        ]);
    }


    // Get current user
    public function me()
    {
        return response()->json(auth()->user());
    }

    // Logout
    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    // Refresh token
    public function refresh(Request $request)
    {
        try {
            // Try to get token from request
            $token = JWTAuth::getToken();

            if (!$token) {
                return response()->json(['error' => 'Token not provided'], 401);
            }

            // Refresh the token
            $newToken = JWTAuth::refresh($token);

            return $this->respondWithToken($newToken);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['error' => 'Token has expired'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['error' => 'Token is invalid'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['error' => 'Token could not be parsed'], 401);
        }
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 600000,
        ]);
    }

    public function verifyDevice(Request $request)
    {
        $request->validate([
            'device_uuid' => 'required|string',
            'verification_token' => 'required|string',
        ]);

        $user = auth()->user();

        $device = $user->devices()
            ->where('device_uuid', $request->device_uuid)
            ->first();

        if (!$device) {
            return response()->json([
                'status' => false,
                'message' => 'Device not found',
            ], 404);
        }

        if ($device->status === 'verified') {
            return response()->json([
                'status' => false,
                'message' => 'Device already verified',
            ], 400);
        }

        if (
            $device->verification_token !== $request->verification_token ||
            ($device->token_expires_at && now()->isAfter($device->token_expires_at))
        ) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $device->status = 'verified';
        $device->verification_token = null;
        $device->token_expires_at = null;
        $device->save();

        return response()->json([
            'status' => true,
            'message' => 'Device verified successfully',
        ]);
    }

    public function uploadFile(Request $request)
    {
        // intercept if file_link exist first
        if (isset($request->file_link)) {
            $validator = Validator::make($request->all(), [
                'type' => 'required|string',
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Incomplete request', 'error' => $validator->errors()]);
            }

            $user = auth()->user();

            $data = FileUpload::create([
                'user_id' => $user->id,
                'type' => $request->type,
                'file' => $request->file_link,
            ]);

            return response()->json(['success' => true, 'message' => "Upload Successful", 'data' => $data], 200);
        }

        // normal file upload process from here

        $validator = Validator::make($request->all(), [
            'type' => 'required|string',
            'file' => 'required|file|max:51200',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Incomplete request', 'error' => $validator->errors()]);
        }


        $user = auth()->user();

//        return response()->json(['success' => true,'data' => $user], 200);

        if ($request->type == 'payout') {
            // $file = Storage::put($request->type, $request->file);
            $ff = $request->file;
            $file = Storage::putFileAs($request->type, $ff, $ff->getClientOriginalName());

            $data = FileUpload::create([
                'user_id' => $user->id,
                'type' => $request->type,
                'file' => Storage::url($file),
            ]);
        } else {
            $file = Storage::put($request->type, $request->file);
            // $ff = $request->file;
            // $file = Storage::putFileAs($request->type, $ff, $ff->getClientOriginalName());

            $data = FileUpload::create([
                'user_id' => $user->id,
                'type' => $request->type,
                'file' => Storage::url($file),
            ]);
        }

        return response()->json(['success' => true, 'message' => "Upload Successful", 'id' => $data->id, 'data' => $data], 200);
    }

    public function sendResetLinkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ]);
        }

        $otp = rand(1000, 9999);

        // Generate token
        $token = Str::random(60);

        // Store token in database
        PasswordReset::updateOrCreate(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => Hash::make($otp),
                'created_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addHours(1)
            ]
        );

        // Get user
        $user = User::where('email', $request->email)->first();

        try {

            $resetUrl = config('app.mobile_app_scheme') . '://reset-password?token=' . $token . '&email=' . urlencode($request->email);

            \Mail::send('emails.otp_verification_template', [
                'user' => $user,
                'resetUrl' => $resetUrl,
                'token' => $otp
            ], function ($message) use ($user) {
                $message->to($user->email);
                $message->subject('Cashon - Reset Your Password');
            });

            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent to your email'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not send reset link',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ]);
        }

        $tokenData = PasswordReset::where('email', $request->email)->first();

        // Check if token exists and is valid
        if (!$tokenData || !Hash::check($request->token, $tokenData->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 400);
        }

        // Check if token is expired (tokens valid for 60 minutes)
        $createdAt = Carbon::parse($tokenData->created_at);
        if (Carbon::now()->diffInMinutes($createdAt) > 60) {
            return response()->json([
                'success' => false,
                'message' => 'Token has expired'
            ], 400);
        }

        // Find user and reset password
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the token
        PasswordReset::where('email', $request->email)->delete();

        // Fire password reset event
//        event(new PasswordReset($user));

        // Return success response
        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully'
        ]);
    }




}
