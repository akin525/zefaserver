<?php
// app/Models/VerificationLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'verification_type',
        'verification_number',
        'identifier',
        'request_payload',
        'response_data',
        'verification_success',
        'status_message',
        'reference_id',
        'cost',
        'verified_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_data' => 'array',
        'verification_success' => 'boolean',
        'cost' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('verification_success', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('verification_type', $type);
    }
}
