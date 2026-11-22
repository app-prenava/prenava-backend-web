<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BidanApplication extends Model
{
    protected $table = 'bidan_applications';
    protected $primaryKey = 'bidan_application_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'subscription_plan_id',
        'full_name',
        'email',
        'phone',
        'bidan_name',
        'spesialisasi',
        'full_address',
        'province',
        'province_code',
        'city',
        'regency_code',
        'district',
        'district_code',
        'lat',
        'lng',
        'operating_hours',
        'str_number',
        'sip_number',
        'document_url',
        'status',
        'rejection_reason',
        'approved_by_admin_id',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'lat' => 'float',
        'lng' => 'float',
        'operating_hours' => 'array',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get the subscription plan
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id', 'subscription_plan_id');
    }

    /**
     * Get the admin who approved/rejected
     */
    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id', 'user_id');
    }

    /**
     * Get the subscription created from this application
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(BidanSubscription::class, 'bidan_application_id', 'bidan_application_id');
    }

    /**
     * Scope to get pending applications
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get approved applications
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope to get rejected applications
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Check if application is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if application is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if application is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
