<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\AuditHelper;
use App\Http\Controllers\Controller;
use App\Models\AdminDepartment;
use Illuminate\Http\Request;

class AdminConfigController extends Controller
{
    /**
     * Get all available roles
     */
    public function getRoles(Request $request)
    {
        try {
            $roles = config('admin.roles', []);

            // Format roles for better response
            $formattedRoles = [];
            foreach ($roles as $key => $label) {
                $formattedRoles[] = [
                    'key' => $key,
                    'label' => $label,
                    'permissions' => config("admin.role_permissions.{$key}", [])
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Roles retrieved successfully',
                'data' => $formattedRoles
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve roles',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all departments
     */
    public function getDepartments(Request $request)
    {
        try {
            $departments = AdminDepartment::orderBy('name')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Departments retrieved successfully',
                'data' => $departments
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve departments',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all available permissions
     */
    public function getPermissions(Request $request)
    {
        try {
            $permissions = config('admin.permissions', []);

            // Group permissions by category
            $groupedPermissions = [
                'user_management' => [],
                'admin_management' => [],
                'transaction_management' => [],
                'payout_management' => [],
                'financial' => [],
                'kyc_management' => [],
                'reports' => [],
                'system' => []
            ];

            foreach ($permissions as $key => $label) {
                $category = $this->categorizePermission($key);
                $groupedPermissions[$category][] = [
                    'key' => $key,
                    'label' => $label
                ];
            }

            return response()->json([
                'status' => true,
                'message' => 'Permissions retrieved successfully',
                'data' => $groupedPermissions
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve permissions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Create new department
     */
    public function createDepartment(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:admin_departments,name',
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|in:active,inactive'
        ]);

        try {
            $department = AdminDepartment::create([
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status ?? 'active',
            ]);

            // Log department creation
            AuditHelper::logCreated($department, [
                'created_by' => auth('admin')->id()
            ], 'New department created');

            return response()->json([
                'status' => true,
                'message' => 'Department created successfully',
                'data' => $department
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create department',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update department
     */
    public function updateDepartment(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:admin_departments,name,' . $id,
            'description' => 'nullable|string|max:500',
            'status' => 'nullable|in:active,inactive'
        ]);

        try {
            $department = AdminDepartment::findOrFail($id);
            $oldData = $department->toArray();

            $department->update([
                'name' => $request->name,
                'description' => $request->description,
                'status' => $request->status ?? $department->status,
            ]);

            // Log department update
            AuditHelper::logUpdated($department, $oldData, [
                'updated_by' => auth('admin')->id()
            ], 'Department updated');

            return response()->json([
                'status' => true,
                'message' => 'Department updated successfully',
                'data' => $department
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update department',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get department details with admins
     */
    public function getDepartmentDetails(Request $request, $id)
    {
        try {
            $department = AdminDepartment::with(['admins' => function ($query) {
                $query->select('admins.id', 'admins.name', 'admins.email', 'admins.role', 'admins.status')
                    ->withPivot('role', 'status');
            }])->findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'Department details retrieved successfully',
                'data' => $department
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve department details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get system configuration options
     */
    public function getSystemConfig(Request $request)
    {
        try {
            $config = [
                'roles' => config('admin.roles', []),
                'permissions' => config('admin.permissions', []),
                'role_permissions' => config('admin.role_permissions', []),
                'high_risk_actions' => config('admin.high_risk_actions', []),
                'app_settings' => [
                    'app_name' => config('app.name'),
                    'app_env' => config('app.env'),
                    'app_debug' => config('app.debug'),
                    'timezone' => config('app.timezone'),
                ]
            ];

            return response()->json([
                'status' => true,
                'message' => 'System configuration retrieved successfully',
                'data' => $config
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve system configuration',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Categorize permission for grouping
     */
    private function categorizePermission(string $permission): string
    {
        if (str_contains($permission, 'user')) {
            return 'user_management';
        } elseif (str_contains($permission, 'admin')) {
            return 'admin_management';
        } elseif (str_contains($permission, 'transaction')) {
            return 'transaction_management';
        } elseif (str_contains($permission, 'payout')) {
            return 'payout_management';
        } elseif (str_contains($permission, 'fee') || str_contains($permission, 'settlement')) {
            return 'financial';
        } elseif (str_contains($permission, 'kyc')) {
            return 'kyc_management';
        } elseif (str_contains($permission, 'report')) {
            return 'reports';
        } else {
            return 'system';
        }
    }
}
