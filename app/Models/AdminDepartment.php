<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminDepartment extends Model
{
    use HasFactory;

     /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'permissions',
        'visibility',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'permissions' => 'array',
    ];

    protected $appends = [
        'department_members_count',
        'department_executive_count',
        'department_head_count',

        'permissions_count',
        'department_head',
    ];


    public function admins()
    {
        return $this->belongsToMany(Admin::class, 'admin_department_admin')
            ->using(AdminDepartmentAdmin::class)
            ->withPivot('role', 'status')
            ->withTimestamps()
            ->select('name', 'email', 'phone');
    }

    public function getPermissionsCountAttribute()
    {
        return count($this->permissions);
    }
    public function getDepartmentMembersCountAttribute()
    {
        return $this->admins()->wherePivot('role', config('admin.roles.member') )->count();
    }

    public function getDepartmentHeadCountAttribute()
    {
        $department_head = $this->admins()->wherePivot('role', config('admin.roles.head') )->count();
        return $department_head ?? 0;
    }

    public function getDepartmentExecutiveCountAttribute()
    {
        $department_head = $this->admins()->wherePivot('role', config('admin.roles.executive')   )->count();
        return $department_head ?? 0;
    }


    public function getDepartmentHeadAttribute()
    {
        $department_head = $this->admins()->wherePivot('role', config('admin.roles.head') )->count();
        return $department_head->name ?? "Not Assigned";
    }

}
