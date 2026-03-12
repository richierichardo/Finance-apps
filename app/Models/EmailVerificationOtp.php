<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailVerificationOtp extends Model
{
    use HasFactory;

    protected $table = 'email_verification_otps';

    protected $fillable = [
        'user_id',
        'email',
        'otp_code',
        'attempts',
        'expires_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if OTP is still valid (not expired)
     */
    public function isValid(): bool
    {
        return $this->expires_at > now() && !$this->verified_at;
    }

    /**
     * Check if OTP attempt limit exceeded
     */
    public function isAttemptExceeded(): bool
    {
        return $this->attempts >= 5;
    }
}
