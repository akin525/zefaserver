<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdminAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_id',
        'admin_id',
        'admin_name',
        'admin_email',
        'action',
        'auditable_type',
        'auditable_id',
        'auditable_name',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'risk_level',
        'description',
        'reason',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($audit) {
            $audit->audit_id = Str::uuid();
        });
    }

    // Relationships
    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeByAdmin($query, $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByRiskLevel($query, $riskLevel)
    {
        return $query->where('risk_level', $riskLevel);
    }

    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', ['high', 'critical']);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Accessors
    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at->format('M d, Y H:i:s');
    }

    public function getRiskLevelColorAttribute()
    {
        return match($this->risk_level) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray'
        };
    }
}
