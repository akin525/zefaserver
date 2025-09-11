<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\AuditHelper;
use Symfony\Component\HttpFoundation\Response;

class AdminRoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth('admin')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $admin = auth('admin')->user();

        if (!in_array($admin->role, $roles)) {
            AuditHelper::logAuth('role_access_denied', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'admin_role' => $admin->role,
                'required_roles' => $roles,
                'requested_route' => $request->route()->getName()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Access denied. Insufficient role privileges.',
                'required_roles' => $roles,
                'your_role' => $admin->role
            ], 403);
        }

        return $next($request);
    }
}
