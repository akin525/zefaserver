<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PasswordReset extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'password_resets';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'email';

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'email',
        'token',
        'created_at',
        'expires_at'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Check if the token has expired
     */
    public function isExpired($minutes = 60)
    {
        return $this->created_at->addMinutes($minutes)->isPast();
    }

    /**
     * Scope to get non-expired tokens
     */
    public function scopeValid($query, $minutes = 60)
    {
        return $query->where('created_at', '>', Carbon::now()->subMinutes($minutes));
    }
}
