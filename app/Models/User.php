<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserGender;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Services\AI\AiAccessService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'phone',
        'role',
        'status',
        'gender',
        'permissions',
        'is_superadmin',
        'ai_enabled',
        'telegram_enabled',
        'last_login_at',
        'banned_until',
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
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
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'gender' => UserGender::class,
            'permissions' => 'array',
            'is_superadmin' => 'boolean',
            'ai_enabled' => 'boolean',
            'telegram_enabled' => 'boolean',
            'last_login_at' => 'datetime',
            'banned_until' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_superadmin;
    }

    public function canUseAi(): bool
    {
        return app(AiAccessService::class)->canUseAi($this);
    }

    public function canUseTelegram(): bool
    {
        return app(AiAccessService::class)->canUseTelegram($this);
    }

    public function isBanned(): bool
    {
        if ($this->status === UserStatus::Banned) {
            return true;
        }
        if ($this->banned_until && $this->banned_until->isFuture()) {
            return true;
        }

        return false;
    }

    public function emailVerificationOtps()
    {
        return $this->hasMany(EmailVerificationOtp::class);
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }

    public function recurringTransactions()
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    public function aiInsights()
    {
        return $this->hasMany(AIInsight::class);
    }

    public function telegramAccount()
    {
        return $this->hasOne(TelegramAccount::class);
    }

    public function aiConversations()
    {
        return $this->hasMany(AiConversation::class);
    }

    public function aiActionDrafts()
    {
        return $this->hasMany(AiActionDraft::class);
    }
}
