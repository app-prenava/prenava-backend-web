<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'activity_type',
        'activity_label',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Activity type constants
     */
    const TYPE_LOGIN              = 'login';
    const TYPE_LOGOUT             = 'logout';
    const TYPE_REGISTER           = 'register';
    const TYPE_UPDATE_PROFILE     = 'update_profile';
    const TYPE_CHANGE_PASSWORD    = 'change_password';
    const TYPE_DEACTIVATED        = 'deactivated';
    const TYPE_ACTIVATED          = 'activated';
    const TYPE_DETEKSI_DEPRESI    = 'deteksi_depresi';
    const TYPE_DETEKSI_ANEMIA     = 'deteksi_anemia';
    const TYPE_REKOMENDASI_MAKANAN = 'rekomendasi_makanan';
    const TYPE_REKOMENDASI_GERAKAN = 'rekomendasi_gerakan';

    /**
     * Labels for each activity type (Bahasa Indonesia)
     */
    const LABELS = [
        self::TYPE_LOGIN              => 'Login',
        self::TYPE_LOGOUT             => 'Logout',
        self::TYPE_REGISTER           => 'Registrasi',
        self::TYPE_UPDATE_PROFILE     => 'Update Profil',
        self::TYPE_CHANGE_PASSWORD    => 'Ganti Password',
        self::TYPE_DEACTIVATED        => 'Akun Dinonaktifkan',
        self::TYPE_ACTIVATED          => 'Akun Diaktifkan',
        self::TYPE_DETEKSI_DEPRESI    => 'Deteksi Depresi',
        self::TYPE_DETEKSI_ANEMIA     => 'Deteksi Anemia',
        self::TYPE_REKOMENDASI_MAKANAN => 'Rekomendasi Makanan',
        self::TYPE_REKOMENDASI_GERAKAN => 'Rekomendasi Gerakan',
    ];

    /**
     * Get user relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get label for the activity type
     */
    public function getActivityLabelAttribute($value): string
    {
        return $value ?: (self::LABELS[$this->activity_type] ?? $this->activity_type);
    }
}
