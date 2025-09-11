<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Activity extends Model
{
    protected $fillable = [
        'user_id',
        'reference',
        'batch_id',
        'type',
        'category',
        'sub_category',
        'amount',
        'fee',
        'net_amount',
        'currency',
        'balance_before',
        'balance_after',
        'status',
        'title',
        'description',
        'metadata',
        'external_reference',
        'provider',
        'provider_response',
        'related_type',
        'related_id',
        'initiated_by',
        'recipient_type',
        'recipient_id',
        'recipient_name',
        'recipient_account',
        'recipient_bank',
        'processed_at',
        'completed_at',
        'expires_at',
        'is_visible',
        'is_reversible',
        'is_fee_transaction',
        'ip_address',
        'user_agent',
        'device_id',
        'audit_trail'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
        'audit_trail' => 'array',
        'processed_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_visible' => 'boolean',
        'is_reversible' => 'boolean',
        'is_fee_transaction' => 'boolean',
    ];

    /**
     * Generate unique reference
     */
    public static function generateReference($prefix = 'TXN')
    {
        do {
            $reference = $prefix . '_' . strtoupper(Str::random(10)) . '_' . time();
        } while (self::where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Initiated by relationship
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * Polymorphic relationship to related entity
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for credits
     */
    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }

    /**
     * Scope for debits
     */
    public function scopeDebits($query)
    {
        return $query->where('type', 'debit');
    }

    /**
     * Scope for completed transactions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for visible activities
     */
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    /**
     * Scope for specific category
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Add audit trail entry
     */
    public function addAuditTrail($action, $details = [])
    {
        $trail = $this->audit_trail ?? [];
        $trail[] = [
            'action' => $action,
            'details' => $details,
            'timestamp' => now()->toISOString(),
            'user_id' => auth()->id()
        ];

        $this->update(['audit_trail' => $trail]);
    }

    /**
     * Check if transaction is credit
     */
    public function isCredit(): bool
    {
        return $this->type === 'credit';
    }

    /**
     * Check if transaction is debit
     */
    public function isDebit(): bool
    {
        return $this->type === 'debit';
    }

    /**
     * Check if transaction is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);

        $this->addAuditTrail('completed');
    }

    /**
     * Mark as failed
     */
    public function markAsFailed($reason = null)
    {
        $this->update(['status' => 'failed']);
        $this->addAuditTrail('failed', ['reason' => $reason]);
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($activity) {
            if (!$activity->reference) {
                $activity->reference = self::generateReference();
            }
        });
    }
}
