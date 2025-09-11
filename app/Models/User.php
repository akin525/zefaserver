<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable  implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
//    protected $fillable = [
//        'name',
//        'email',
//        'password',
//    ];
    protected $guarded = [];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'pin',
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function getJWTIdentifier()
    {
        return $this->getKey(); // usually the user id
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Get users referred by this user
     */
//    public function referrals()
//    {
//        return $this->hasMany(User::class, 'referred_by');
//    }

    /**
     * Get referral tracking records
     */
//    public function referralRecords()
//    {
//        return $this->hasMany(Referral::class, 'referrer_id');
//    }

    /**
     * Get referral link
     */
//    public function getReferralLinkAttribute()
//    {
//        if (!$this->referral_code) {
//            return null;
//        }
//
//        return config('app.url') . '/register?ref=' . $this->referral_code;
//    }

    /**
     * Get mobile referral link
     */
//    public function getMobileReferralLinkAttribute()
//    {
//        if (!$this->referral_code) {
//            return null;
//        }
//
//        return config('app.mobile_app_scheme', 'myapp') . '://register?ref=' . $this->referral_code;
//    }

    /**
     * Boot method to auto-generate referral code
     */
//    protected static function boot()
//    {
//        parent::boot();
//
//        static::creating(function ($user) {
//            if (!$user->referral_code) {
//                $user->referral_code = self::generateReferralCode();
//                $user->referral_code_generated_at = now();
//            }
//        });
//    }

}
