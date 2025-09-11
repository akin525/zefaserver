<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\AuditHelper;
use Symfony\Component\HttpFoundation\Response;

class AdminPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        // Check if admin is authenticated
        if (!auth('admin')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized. Please login first.'
            ], 401);
        }

        $admin = auth('admin')->user();

        // Check if admin account is active
        if ($admin->status !== 'active') {
            AuditHelper::logAuth('access_denied', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'reason' => 'Account not active',
                'status' => $admin->status,
                'requested_route' => $request->route()->getName(),
                'requested_permissions' => $permissions
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Account is not active. Please contact administrator.'
            ], 403);
        }

        // If no specific permissions required, just check authentication
        if (empty($permissions)) {
            return $next($request);
        }

        // Check if admin has required permissions
        $hasPermission = $this->checkPermissions($admin, $permissions);

        if (!$hasPermission) {
            // Log unauthorized access attempt
            AuditHelper::logAuth('access_denied', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'reason' => 'Insufficient permissions',
                'required_permissions' => $permissions,
                'admin_permissions' => $this->getAdminPermissions($admin),
                'requested_route' => $request->route()->getName(),
                'requested_url' => $request->fullUrl()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Access denied. You do not have the required permissions.',
                'required_permissions' => $permissions
            ], 403);
        }

        // Log successful access for high-risk operations
        if ($this->isHighRiskOperation($permissions)) {
            AuditHelper::log([
                'action' => 'high_risk_access',
                'description' => 'Admin accessed high-risk operation',
                'metadata' => [
                    'admin_id' => $admin->id,
                    'permissions' => $permissions,
                    'route' => $request->route()->getName(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method()
                ],
                'risk_level' => 'high'
            ]);
        }

        return $next($request);
    }

    /**
     * Check if admin has required permissions
     */
    private function checkPermissions($admin, array $requiredPermissions): bool
    {
        // Super admin bypass (if you have this role)
        if ($admin->role === 'super_admin') {
            return true;
        }

        foreach ($requiredPermissions as $permission) {
            if (!$this->hasPermission($admin, $permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if admin has a specific permission
     */
    private function hasPermission($admin, string $permission): bool
    {
        // Check direct permission fields on admin model
        if ($admin->hasAttribute($permission) && $admin->{$permission}) {
            return true;
        }

        // Check department-based permissions
        if (method_exists($admin, 'hasPermission') && $admin->hasPermission($permission)) {
            return true;
        }

        // Check role-based permissions
        return $this->checkRolePermission($admin, $permission);
    }

    /**
     * Check role-based permissions
     */
    private function checkRolePermission($admin, string $permission): bool
    {
        $rolePermissions = config('admin.role_permissions', []);

        if (isset($rolePermissions[$admin->role])) {
            return in_array($permission, $rolePermissions[$admin->role]);
        }

        return false;
    }

    /**
     * Get all admin permissions for logging
     */
    private function getAdminPermissions($admin): array
    {
        $permissions = [];

        // Get direct permissions
        $directPermissions = [
            'view_user', 'manage_user', 'view_admin', 'reporting',
            'view_transaction', 'manage_transaction', 'view_payout',
            'manage_payout', 'manage_fees', 'view_settlement',
            'view_refund', 'manage_refund', 'view_kyc', 'manage_kyc'
        ];

        foreach ($directPermissions as $perm) {
            if ($admin->{$perm}) {
                $permissions[] = $perm;
            }
        }

        // Get department permissions
        if (method_exists($admin, 'admin_permissions')) {
            $deptPermissions = $admin->admin_permissions;
            foreach ($deptPermissions as $category => $perms) {
                $permissions = array_merge($permissions, $perms);
            }
        }

        return array_unique($permissions);
    }

    /**
     * Determine if operation is high-risk
     */
    private function isHighRiskOperation(array $permissions): bool
    {
        $highRiskPermissions = [
            'manage_admin',
            'manage_user',
            'manage_transaction',
            'manage_payout',
            'manage_fees',
            'manage_refund',
            'manage_kyc',
            'system_config',
            'bulk_operations',
            'data_export'
        ];

        return !empty(array_intersect($permissions, $highRiskPermissions));
    }
}
