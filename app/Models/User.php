<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'token_version',
        'category'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
    ];

    /**
     * JWT identifier → pakai user_id, bukan id
     */
    public function getJWTIdentifier()
    {
        return $this->attributes[$this->primaryKey];
    }

    /**
     * Custom claims JWT → kosongkan, karena kita isi manual saat login
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Role checker manual (opsional masih dipakai)
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isBidan(): bool
    {
        return $this->hasRole('bidan');
    }

    public function isDinkes(): bool
    {
        return $this->hasRole('dinkes');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isIbuHamil(): bool
    {
        return $this->hasRole('ibu_hamil');
    }

    /**
     * Contoh relasi icon kalau memang masih dipakai
     */
    public function selectedIcon(): BelongsTo
    {
        return $this->belongsTo(Icons::class, 'selected_icon_id', 'id');
    }

    // ===== BIDAN SUBSCRIPTION RELATIONSHIPS =====

    /**
     * Get bidan profile (existing table)
     */
    public function bidanProfile(): HasOne
    {
        return $this->hasOne(BidanProfile::class, 'user_id', 'user_id');
    }

    /**
     * Get user profile (existing table)
     */
    public function userProfile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id', 'user_id');
    }

    /**
     * Get bidan's active subscription
     */
    public function bidanSubscription(): HasOne
    {
        return $this->hasOne(BidanSubscription::class, 'user_id', 'user_id')
                    ->where('status', 'active')
                    ->latest();
    }

    /**
     * Get all bidan subscriptions (including expired)
     */
    public function bidanSubscriptions(): HasMany
    {
        return $this->hasMany(BidanSubscription::class, 'user_id', 'user_id');
    }

    /**
     * Get bidan locations
     */
    public function bidanLocations(): HasMany
    {
        return $this->hasMany(BidanLocation::class, 'bidan_id', 'user_id');
    }

    /**
     * Get bidan's primary location
     */
    public function bidanPrimaryLocation(): HasOne
    {
        return $this->hasOne(BidanLocation::class, 'bidan_id', 'user_id')
                    ->where('is_primary', true)
                    ->where('is_active', true);
    }

    /**
     * Get appointments as user (ibu_hamil)
     */
    public function appointmentsAsUser(): HasMany
    {
        return $this->hasMany(Appointment::class, 'user_id', 'user_id');
    }

    /**
     * Get appointments as bidan
     */
    public function appointmentsAsBidan(): HasMany
    {
        return $this->hasMany(Appointment::class, 'bidan_id', 'user_id');
    }

    /**
     * Get pregnancy calculator for user
     */
    public function pregnancyCalculator(): HasOne
    {
        return $this->hasOne(PregnancyCalculator::class, 'user_id', 'user_id')
                    ->latest();
    }

    // ===== HELPER METHODS =====

    /**
     * Check if bidan has active subscription
     */
    public function hasActiveSubscription(): bool
    {
        if (!$this->isBidan()) {
            return false;
        }

        return $this->bidanSubscription()
                    ->whereDate('end_date', '>=', now())
                    ->exists();
    }

    /**
     * Get bidan's subscription status
     */
    public function getSubscriptionStatus(): ?string
    {
        $subscription = $this->bidanSubscription;
        
        if (!$subscription) {
            return null;
        }

        if ($subscription->isExpired()) {
            return 'expired';
        }

        return $subscription->status;
    }

    // ===== DAILY FEATURES RELATIONSHIPS =====

    /**
     * Get user streak records
     */
    public function streaks(): HasOne
    {
        return $this->hasOne(UserStreak::class, 'user_id', 'user_id');
    }

    /**
     * Get user daily task logs
     */
    public function dailyTaskLogs(): HasMany
    {
        return $this->hasMany(UserTaskLog::class, 'user_id', 'user_id');
    }
}
