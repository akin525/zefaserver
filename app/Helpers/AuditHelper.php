<?php

namespace App\Helpers;

use App\Models\AdminAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditHelper
{
    /**
     * Log an audit entry
     */
    public static function log(array $data)
    {
        $admin = Auth::guard('admin')->user();

        $auditData = array_merge([
            'admin_id' => $admin?->id,
            'admin_name' => $admin?->name,
            'admin_email' => $admin?->email,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
            'risk_level' => 'low',
        ], $data);

        return AdminAudit::create($auditData);
    }

    /**
     * Log model creation
     */
    public static function logCreated($model, array $metadata = [], string $reason = null)
    {
        return self::log([
            'action' => 'created',
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'auditable_name' => self::getModelName($model),
            'new_values' => $model->toArray(),
            'metadata' => $metadata,
            'description' => "Created " . class_basename($model) . ": " . self::getModelName($model),
            'reason' => $reason,
            'risk_level' => self::determineRiskLevel('created', $model),
        ]);
    }

    /**
     * Log model updates
     */
    public static function logUpdated($model, array $oldValues, array $metadata = [], string $reason = null)
    {
        $changes = array_diff_assoc($model->toArray(), $oldValues);

        return self::log([
            'action' => 'updated',
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'auditable_name' => self::getModelName($model),
            'old_values' => $oldValues,
            'new_values' => $changes,
            'metadata' => $metadata,
            'description' => "Updated " . class_basename($model) . ": " . self::getModelName($model),
            'reason' => $reason,
            'risk_level' => self::determineRiskLevel('updated', $model, $changes),
        ]);
    }

    /**
     * Log model deletion
     */
    public static function logDeleted($model, array $metadata = [], string $reason = null)
    {
        return self::log([
            'action' => 'deleted',
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'auditable_name' => self::getModelName($model),
            'old_values' => $model->toArray(),
            'metadata' => $metadata,
            'description' => "Deleted " . class_basename($model) . ": " . self::getModelName($model),
            'reason' => $reason,
            'risk_level' => 'high', // Deletions are always high risk
        ]);
    }

    /**
     * Log authentication events
     */
    public static function logAuth(string $action, array $metadata = [])
    {
        $riskLevel = match($action) {
            'login_failed', 'password_reset_requested' => 'medium',
            'login_success', 'logout' => 'low',
            'account_locked', 'suspicious_activity' => 'high',
            default => 'low'
        };

        return self::log([
            'action' => $action,
            'auditable_type' => 'Authentication',
            'metadata' => $metadata,
            'description' => ucfirst(str_replace('_', ' ', $action)),
            'risk_level' => $riskLevel,
        ]);
    }

    /**
     * Log permission changes
     */
    public static function logPermissionChange($admin, array $oldPermissions, array $newPermissions, string $reason = null)
    {
        $changes = array_diff_assoc($newPermissions, $oldPermissions);

        return self::log([
            'action' => 'permission_changed',
            'auditable_type' => get_class($admin),
            'auditable_id' => $admin->id,
            'auditable_name' => $admin->name,
            'old_values' => $oldPermissions,
            'new_values' => $changes,
            'description' => "Changed permissions for admin: {$admin->name}",
            'reason' => $reason,
            'risk_level' => 'high', // Permission changes are high risk
        ]);
    }

    /**
     * Log bulk operations
     */
    public static function logBulkOperation(string $action, string $modelType, array $ids, array $metadata = [], string $reason = null)
    {
        return self::log([
            'action' => "bulk_{$action}",
            'auditable_type' => $modelType,
            'metadata' => array_merge(['affected_ids' => $ids, 'count' => count($ids)], $metadata),
            'description' => "Bulk {$action} on " . count($ids) . " " . class_basename($modelType) . " records",
            'reason' => $reason,
            'risk_level' => count($ids) > 10 ? 'high' : 'medium',
        ]);
    }

    /**
     * Log data export
     */
    public static function logDataExport(string $type, array $filters = [], string $reason = null)
    {
        return self::log([
            'action' => 'data_exported',
            'auditable_type' => 'DataExport',
            'metadata' => ['export_type' => $type, 'filters' => $filters],
            'description' => "Exported {$type} data",
            'reason' => $reason,
            'risk_level' => 'medium',
        ]);
    }

    /**
     * Log system configuration changes
     */
    public static function logConfigChange(string $key, $oldValue, $newValue, string $reason = null)
    {
        return self::log([
            'action' => 'config_changed',
            'auditable_type' => 'SystemConfig',
            'auditable_name' => $key,
            'old_values' => ['value' => $oldValue],
            'new_values' => ['value' => $newValue],
            'description' => "Changed system configuration: {$key}",
            'reason' => $reason,
            'risk_level' => 'high',
        ]);
    }

    /**
     * Get a human-readable name for the model
     */
    private static function getModelName($model): string
    {
        if (method_exists($model, 'getAuditName')) {
            return $model->getAuditName();
        }

        return $model->name ?? $model->title ?? $model->email ?? $model->id ?? 'Unknown';
    }

    /**
     * Determine risk level based on action and model
     */
    private static function determineRiskLevel(string $action, $model, array $changes = []): string
    {
        // High-risk models
        $highRiskModels = ['Admin', 'AdminDepartment', 'User'];

        if (in_array(class_basename($model), $highRiskModels)) {
            return 'high';
        }

        // High-risk fields
        $highRiskFields = ['password', 'permissions', 'role', 'status', 'admin_roles'];

        if ($action === 'updated' && !empty(array_intersect(array_keys($changes), $highRiskFields))) {
            return 'high';
        }

        // Medium-risk actions
        $mediumRiskActions = ['created', 'updated'];

        if (in_array($action, $mediumRiskActions)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get audit statistics
     */
    public static function getStats(int $days = 30): array
    {
        $query = AdminAudit::where('created_at', '>=', now()->subDays($days));

        return [
            'total_actions' => $query->count(),
            'unique_admins' => $query->distinct('admin_id')->count('admin_id'),
            'high_risk_actions' => $query->where('risk_level', 'high')->count(),
            'critical_actions' => $query->where('risk_level', 'critical')->count(),
            'top_actions' => $query->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(5)
                ->get(),
            'daily_activity' => $query->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ];
    }
}
