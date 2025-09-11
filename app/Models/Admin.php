<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'image',
        'address',
        'city',
        'state',
        'role',
        'admin_roles',
        'status',
        'login_time',
        'password',
        'remember_token',
        'pass_changed',
        'has_temp_password',
        'invitation_token',
        'invitation_token_expiry',

        'view_user',
        'manage_user',
        'view_admin',
        'reporting',
        'view_transaction',
        'manage_transaction',
        'view_payout',
        'manage_payout',
        'manage_fees',
        'view_settlement',
        'view_refund',
        'manage_refund',
        'view_kyc',
        'manage_kyc',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function departments()
    {
        return $this->belongsToMany(AdminDepartment::class, 'admin_department_admin')
            ->using(AdminDepartmentAdmin::class)
            ->withPivot('role', 'status')
            ->withTimestamps();
    }


    public function getAdminPermissionsAttribute()
    {
        $permissions = [];

        foreach ($this->departments as $department) {
            foreach ($department->permissions as $category => $perms) {
                if (!isset($permissions[$category])) {
                    $permissions[$category] = [];
                }
                $permissions[$category] = array_unique(array_merge($permissions[$category], $perms));
            }
        }

        return $permissions;
    }

    /**
     * Check if admin has a specific permission.
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->admin_permissions;

        foreach ($permissions as $category => $perms) {
            if (in_array($permission, $perms)) {
                return true;
            }
        }

        return false;
    }

    public function getAuditName(): string
    {
        return $this->name . ' (' . $this->email . ')';
    }
}
