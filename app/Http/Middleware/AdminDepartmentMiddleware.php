<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\AuditHelper;
use Symfony\Component\HttpFoundation\Response;

class AdminDepartmentMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$departments): Response
    {
        if (!auth('admin')->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $admin = auth('admin')->user();
        $adminDepartments = $admin->departments->pluck('name')->toArray();

        $hasAccess = !empty(array_intersect($adminDepartments, $departments));

        if (!$hasAccess) {
            AuditHelper::logAuth('department_access_denied', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
                'admin_departments' => $adminDepartments,
                'required_departments' => $departments,
                'requested_route' => $request->route()->getName()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Access denied. You do not belong to the required department.',
                'required_departments' => $departments,
                'your_departments' => $adminDepartments
            ], 403);
        }

        return $next($request);
    }
}
