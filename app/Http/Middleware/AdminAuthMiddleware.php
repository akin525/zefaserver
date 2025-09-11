<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\AuditHelper;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if admin is authenticated using sanctum guard
        if (!auth('sanctum')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Please login first.',
                'error_code' => 'AUTH_REQUIRED'
            ], 401);
        }

        $user = auth('sanctum')->user();

        // Check if the authenticated user is an admin
        if (!$user instanceof \App\Models\Admin) {
            AuditHelper::logAuth('invalid_admin_access', [
                'user_id' => $user->id,
                'user_type' => get_class($user),
                'attempted_route' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Access denied. Admin privileges required.',
                'error_code' => 'ADMIN_REQUIRED'
            ], 403);
        }

        // Check if admin account is active
        if ($user->status !== 'active') {
            AuditHelper::logAuth('inactive_admin_access', [
                'admin_id' => $user->id,
                'email' => $user->email,
                'status' => $user->status,
                'attempted_route' => $request->route()?->getName(),
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Account is inactive. Please contact administrator.',
                'error_code' => 'ACCOUNT_INACTIVE',
                'account_status' => $user->status
            ], 403);
        }

        // Check if admin has temporary password and is trying to access restricted routes
        if ($user->has_temp_password && !$this->isAllowedWithTempPassword($request)) {
            return response()->json([
                'status' => false,
                'message' => 'Please change your temporary password before accessing this resource.',
                'error_code' => 'TEMP_PASSWORD_CHANGE_REQUIRED',
                'change_password_url' => route('admin.password.change-temp')
            ], 423); // 423 Locked
        }

        // Check for suspicious activity (optional security layer)
        if ($this->detectSuspiciousActivity($user, $request)) {
            AuditHelper::logAuth('suspicious_activity', [
                'admin_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'attempted_route' => $request->route()?->getName(),
                'reason' => 'Multiple IP addresses or unusual access pattern'
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Suspicious activity detected. Please contact administrator.',
                'error_code' => 'SUSPICIOUS_ACTIVITY'
            ], 403);
        }

        // Set the admin guard for the request
        auth()->setUser($user);
        auth('admin')->setUser($user);

        return $next($request);
    }

    /**
     * Check if the route is allowed with temporary password
     */
    private function isAllowedWithTempPassword(Request $request): bool
    {
        $allowedRoutes = [
            'admin.logout',
            'admin.profile',
            'admin.password.change-temp',
        ];

        $currentRoute = $request->route()?->getName();

        // Allow if route name is in allowed list
        if (in_array($currentRoute, $allowedRoutes)) {
            return true;
        }

        // Allow password change related URLs
        $allowedPaths = [
            'admin/logout',
            'admin/profile',
            'admin/password/change-temp',
        ];

        $currentPath = trim($request->getPathInfo(), '/');

        foreach ($allowedPaths as $path) {
            if (str_starts_with($currentPath, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect suspicious activity (basic implementation)
     */
    private function detectSuspiciousActivity($admin, Request $request): bool
    {
        // Skip detection for super admins (optional)
        if ($admin->role === 'super_admin') {
            return false;
        }

        $currentIp = $request->ip();
        $userAgent = $request->userAgent();

        // Check if IP has changed recently (within last hour)
        $recentLogin = \App\Models\AdminAudit::where('admin_id', $admin->id)
            ->where('action', 'login_success')
            ->where('created_at', '>=', now()->subHour())
            ->where('ip_address', '!=', $currentIp)
            ->exists();

        if ($recentLogin) {
            return true;
        }

        // Check for too many requests from same IP in short time
        $recentRequests = \App\Models\AdminAudit::where('admin_id', $admin->id)
            ->where('ip_address', $currentIp)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        if ($recentRequests > 50) { // Adjust threshold as needed
            return true;
        }

        return false;
    }
}
