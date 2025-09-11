<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\AuditHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    /**
     * Handle the admin login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            AuditHelper::logAuth('login_failed', [
                'email' => $request->email,
                'errors' => $validator->errors(),
                'reason' => 'Validation failed'
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin) {
            AuditHelper::logAuth('login_failed', [
                'email' => $request->email,
                'reason' => 'Admin not found'
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if admin is active
        if ($admin->status !== 'active') {
            AuditHelper::logAuth('login_failed', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'reason' => 'Account inactive',
                'status' => $admin->status
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Account is inactive. Please contact administrator.'
            ], 403);
        }

        // Check hashed password
        if (!Hash::check($request->password, $admin->password)) {
            AuditHelper::logAuth('login_failed', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'reason' => 'Invalid password'
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        try {
            // Delete existing tokens
            $admin->tokens()->delete();

            // Create new token
            $token = $admin->createToken($request->email, ['admin'])->plainTextToken;

            // Update login time
            $admin->update(['login_time' => now()]);

            // Log successful login
            AuditHelper::logAuth('login_success', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'login_time' => now()->toDateTimeString()
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Login successful',
                'data' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'username' => $admin->username,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'status' => $admin->status,
                    'has_temp_password' => $admin->has_temp_password,
                    'token' => $token,
                ],
            ], 200);

        } catch (\Exception $e) {
            AuditHelper::logAuth('login_error', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Login failed due to server error'
            ], 500);
        }
    }

    /**
     * Create a new admin
     */
    public function createAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:admins,email',
            'name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255|unique:admins,username',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|string',
            'departments' => 'required|array|min:1',
            'departments.*' => 'exists:admin_departments,id',
            'password' => 'required|string|min:8|confirmed',
            'status' => 'nullable|in:active,inactive,suspended',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = Admin::create([
                'email' => $request->email,
                'name' => $request->name,
                'username' => $request->username ?? $request->email,
                'phone' => $request->phone,
                'role' => $request->role,
                'status' => $request->status ?? 'active',
                'password' => Hash::make($request->password),
                'has_temp_password' => $request->has('temp_password') ? true : false,
            ]);

            // Attach departments to the admin
            foreach ($request->departments as $departmentId) {
                $admin->departments()->attach($departmentId, [
                    'role' => $request->role,
                    'status' => 'active',
                ]);
            }

            // Log the admin creation
            AuditHelper::logCreated($admin, [
                'departments' => $request->departments,
                'created_by' => auth('admin')->id(),
            ], 'New admin user created');

            return response()->json([
                'status' => true,
                'message' => 'Admin created successfully',
                'data' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'username' => $admin->username,
                    'email' => $admin->email,
                    'role' => $admin->role,
                    'status' => $admin->status,
                    'departments' => $admin->departments->pluck('name'),
                ],
            ], 201);

        } catch (\Exception $e) {
            AuditHelper::log([
                'action' => 'admin_creation_failed',
                'description' => 'Failed to create admin user',
                'metadata' => [
                    'email' => $request->email,
                    'error' => $e->getMessage()
                ],
                'risk_level' => 'medium'
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to create admin',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        try {
            $admin = auth('admin')->user();

            if ($admin) {
                // Delete current token
                $request->user()->currentAccessToken()->delete();

                // Log logout
                AuditHelper::logAuth('logout', [
                    'admin_id' => $admin->id,
                    'email' => $admin->email,
                    'logout_time' => now()->toDateTimeString()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Logged out successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Logout failed',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Request password reset
     */
    public function requestPasswordReset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $admin = Admin::where('email', $request->email)->first();

        if (!$admin) {
            // Log failed password reset attempt
            AuditHelper::logAuth('password_reset_failed', [
                'email' => $request->email,
                'reason' => 'Email not found'
            ]);

            // Don't reveal if email exists or not for security
            return response()->json([
                'status' => true,
                'message' => 'If the email exists, a password reset link has been sent.'
            ], 200);
        }

        try {
            $token = Str::random(60);
            $expiry = now()->addMinutes(30);

            // Save token and expiration
            $admin->update([
                'invitation_token' => $token,
                'invitation_token_expiry' => $expiry,
            ]);

            // Send email with reset token
            Mail::send('emails.password-reset-code', [
                'token' => $token,
                'admin' => $admin,
                'expiry' => $expiry
            ], function ($message) use ($admin) {
                $message->to($admin->email);
                $message->subject('Password Reset Request');
            });

            // Log password reset request
            AuditHelper::logAuth('password_reset_requested', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'token_expiry' => $expiry->toDateTimeString()
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Password reset email sent successfully',
            ], 200);

        } catch (\Exception $e) {
            AuditHelper::logAuth('password_reset_error', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to send password reset email',
            ], 500);
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $admin = Admin::where('invitation_token', $request->token)->first();

        if (!$admin) {
            AuditHelper::logAuth('password_reset_failed', [
                'token' => $request->token,
                'reason' => 'Invalid token'
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired token'
            ], 400);
        }

        // Check if token has expired
        if (now()->greaterThan($admin->invitation_token_expiry)) {
            AuditHelper::logAuth('password_reset_failed', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'reason' => 'Token expired'
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Token has expired'
            ], 400);
        }

        try {
            $oldData = $admin->only(['password', 'invitation_token', 'invitation_token_expiry']);

            // Update the password and clear reset token
            $admin->update([
                'password' => Hash::make($request->password),
                'invitation_token' => null,
                'invitation_token_expiry' => null,
                'has_temp_password' => false,
                'pass_changed' => true,
            ]);

            // Log password reset success
            AuditHelper::logUpdated($admin, $oldData, [
                'action_type' => 'password_reset'
            ], 'Password reset via email token');

            return response()->json([
                'status' => true,
                'message' => 'Password reset successfully',
            ], 200);

        } catch (\Exception $e) {
            AuditHelper::logAuth('password_reset_error', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to reset password',
            ], 500);
        }
    }

    /**
     * Change the temporary password for an admin user.
     */
    public function changeTempPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = auth('admin')->user();

            if (!$admin) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            if (!Hash::check($request->current_password, $admin->password)) {
                AuditHelper::logAuth('password_change_failed', [
                    'admin_id' => $admin->id,
                    'email' => $admin->email,
                    'reason' => 'Current password incorrect'
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            $oldData = $admin->only(['password', 'has_temp_password']);

            // Update password
            $admin->update([
                'password' => Hash::make($request->new_password),
                'has_temp_password' => false,
                'pass_changed' => true,
            ]);

            // Log password change
            AuditHelper::logUpdated($admin, $oldData, [
                'action_type' => 'password_change'
            ], 'Temporary password changed by user');

            return response()->json([
                'status' => true,
                'message' => 'Password changed successfully'
            ], 200);

        } catch (\Exception $e) {
            AuditHelper::logAuth('password_change_error', [
                'admin_id' => auth('admin')->id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to change password'
            ], 500);
        }
    }

    /**
     * Get current admin profile
     */
    public function profile(Request $request)
    {
        try {
            $admin = auth('admin')->user();

            if (!$admin) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            return response()->json([
                'status' => true,
                'message' => 'Profile retrieved successfully',
                'data' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'username' => $admin->username,
                    'email' => $admin->email,
                    'phone' => $admin->phone,
                    'role' => $admin->role,
                    'status' => $admin->status,
                    'has_temp_password' => $admin->has_temp_password,
                    'login_time' => $admin->login_time,
                    'departments' => $admin->departments->map(function ($dept) {
                        return [
                            'id' => $dept->id,
                            'name' => $dept->name,
                            'role' => $dept->pivot->role,
                            'status' => $dept->pivot->status,
                        ];
                    }),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve profile'
            ], 500);
        }
    }
}
