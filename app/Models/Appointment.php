<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    protected $table = 'appointments';
    protected $primaryKey = 'appointment_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'bidan_id',
        'bidan_location_id',
        'status',
        'preferred_date',
        'preferred_time',
        'confirmed_date',
        'confirmed_time',
        'notes',
        'bidan_notes',
        'rejection_reason',
        'cancellation_reason',
        'consultation_type',
        'rescheduled_date',
        'rescheduled_time',
        'rescheduled_by',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'preferred_time' => 'datetime:H:i',
        'confirmed_date' => 'date',
        'confirmed_time' => 'datetime:H:i',
        'rescheduled_date' => 'date',
        'rescheduled_time' => 'datetime:H:i',
    ];

    // Status constants
    const STATUS_REQUESTED = 'requested';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_RESCHEDULED = 'rescheduled';

    // Consultation type constants
    const TYPE_VISIT = 'visit';
    const TYPE_CONSULTATION = 'consultation';
    const TYPE_CHECKUP = 'checkup';

    /**
     * Get the user who made the appointment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get the bidan
     */
    public function bidan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bidan_id', 'user_id');
    }

    /**
     * Get the location
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(BidanLocation::class, 'bidan_location_id', 'bidan_location_id');
    }

    /**
     * Get the consent record
     */
    public function consent(): HasOne
    {
        return $this->hasOne(AppointmentConsent::class, 'appointment_id', 'appointment_id');
    }

    /**
     * Scope by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for requested appointments
     */
    public function scopeRequested($query)
    {
        return $query->where('status', self::STATUS_REQUESTED);
    }

    /**
     * Scope for accepted appointments
     */
    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * Scope for bidan's appointments
     */
    public function scopeForBidan($query, int $bidanId)
    {
        return $query->where('bidan_id', $bidanId);
    }

    /**
     * Scope for user's appointments
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if appointment is pending (requested)
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_REQUESTED;
    }

    /**
     * Check if appointment is accepted
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Accept appointment
     */
    public function accept(?string $date = null, ?string $time = null, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'confirmed_date' => $date ?? $this->preferred_date,
            'confirmed_time' => $time ?? $this->preferred_time,
            'bidan_notes' => $notes,
        ]);
    }

    /**
     * Reject appointment
     */
    public function reject(?string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Complete appointment
     */
    public function complete(?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'bidan_notes' => $notes ?? $this->bidan_notes,
        ]);
    }

    /**
     * Cancel appointment (by user)
     */
    public function cancel(?string $reason = null): void
    {
        $data = ['status' => self::STATUS_CANCELLED];
        if ($reason) {
            $data['cancellation_reason'] = $reason;
        }
        $this->update($data);
    }

    /**
     * Reschedule appointment
     */
    public function reschedule(string $date, string $time, string $by): void
    {
        $this->update([
            'status' => self::STATUS_RESCHEDULED,
            'rescheduled_date' => $date,
            'rescheduled_time' => $time,
            'rescheduled_by' => $by,
        ]);
    }

    /**
     * Check if appointment can be rescheduled
     */
    public function canReschedule(): bool
    {
        return in_array($this->status, [self::STATUS_REQUESTED, self::STATUS_ACCEPTED]);
    }

    /**
     * Get user data based on consent
     */
    public function getUserDataWithConsent(): array
    {
        $consent = $this->consent;
        
        if (!$consent) {
            return ['message' => 'No consent provided'];
        }

        $sharedFields = $consent->shared_fields ?? [];
        $userData = [];
        $user = $this->user;

        // Map fields based on consent
        $fieldMapping = [
            'name' => $user->name ?? null,
            'email' => $user->email ?? null,
            'phone' => $user->userProfile?->phone ?? null,
            'address' => $user->userProfile?->address ?? null,
            'age' => $user->userProfile?->age ?? null,
            'pregnancy_week' => $user->pregnancyCalculator?->current_week ?? null,
        ];

        foreach ($fieldMapping as $field => $value) {
            if (isset($sharedFields[$field]) && $sharedFields[$field] === true) {
                $userData[$field] = $value;
            }
        }

        return $userData;
    }
}
