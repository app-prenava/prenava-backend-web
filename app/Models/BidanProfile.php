<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BidanProfile extends Model
{
    protected $table = 'bidan_profile';
    protected $primaryKey = 'bidan_profile_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'photo',
        'tempat_praktik',
        'alamat_praktik',
        'provinsi_tempat_praktik',
        'province_code',
        'kota_tempat_praktik',
        'regency_code',
        'kecamatan_tempat_praktik',
        'district_code',
        'telepon_tempat_praktik',
        'spesialisasi',
        'str_number',
        'sip_number',
        'is_verified_partner',
        'verified_at',
    ];

    protected $casts = [
        'is_verified_partner' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the user that owns this profile
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
