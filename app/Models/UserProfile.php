<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $table = 'user_profile';
    protected $primaryKey = 'user_profile_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'photo',
        'tanggal_lahir',
        'usia',
        'alamat',
        'no_telepon',
        'pendidikan_terakhir',
        'pekerjaan',
        'golongan_darah',
        'status_kandungan',
        'provinsi',
        'province_code',
        'kota',
        'regency_code',
        'kecamatan',
        'district_code',
        'pendapatan_keluarga',
        'lat',
        'lng',
        'location_updated_at',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'usia' => 'integer',
        'lat' => 'float',
        'lng' => 'float',
        'location_updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns this profile
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Get phone attribute (alias for no_telepon)
     */
    public function getPhoneAttribute(): ?string
    {
        return $this->no_telepon;
    }

    /**
     * Get address attribute (alias for alamat)
     */
    public function getAddressAttribute(): ?string
    {
        return $this->alamat;
    }

    /**
     * Get age attribute (alias for usia)
     */
    public function getAgeAttribute(): ?int
    {
        return $this->usia;
    }
}
