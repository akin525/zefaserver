<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AdminDepartmentAdmin extends Pivot
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'admin_department_admin';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'admin_id',
        'admin_department_id',
        'role',
        'status',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the admin that belongs to this pivot.
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Get the admin department that belongs to this pivot.
     */
    public function adminDepartment()
    {
        return $this->belongsTo(AdminDepartment::class);
    }

    /**
     * Scope a query to only include active relationships.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include inactive relationships.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope a query to filter by role.
     */
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Check if the relationship is active.
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Check if the relationship is inactive.
     */
    public function isInactive()
    {
        return $this->status === 'inactive';
    }

    /**
     * Get the role display name.
     */
    public function getRoleDisplayAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->role));
    }
}
