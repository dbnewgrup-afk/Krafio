<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const STATUSES = ['pending_verification', 'pending_approval', 'request_revision', 'active', 'rejected', 'suspended', 'banned', 'deactivated'];

    protected $guarded = ['id', 'role', 'status', 'mfa_secret', 'mfa_enabled', 'referral_code'];

    protected $hidden = ['password', 'remember_token', 'mfa_secret'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_enabled' => 'boolean',
            'ai_consent_at' => 'datetime',
            'last_login_at' => 'datetime',
            'notification_prefs' => 'array',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'past_due'])
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest('starts_at')
            ->first();
    }

    public function latestSubscription(): ?Subscription
    {
        return $this->subscriptions()->whereNotIn('status', ['pending_payment'])->latest('starts_at')->first();
    }

    public function addons()
    {
        return $this->hasMany(UserAddon::class);
    }

    public function ledger()
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }
}
