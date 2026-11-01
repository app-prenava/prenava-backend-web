<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Komunitas extends Model
{
    use HasFactory;

    protected $table = 'komunitas';
    protected $primaryKey = 'post_id';

    protected $fillable = [
        'user_id', 
        'judul', 
        'kategori',
        'is_anonymous',
        'gestational_age_weeks',
        'trimester',
        'deskripsi', 
        'gambar', 
        'apresiasi',
        'komen',
        'has_bidan_reply',
    ];

    protected $casts = [
        'is_anonymous'          => 'boolean',
        'gestational_age_weeks' => 'integer',
        'trimester'             => 'integer',
        'has_bidan_reply'       => 'boolean',
        'apresiasi'             => 'integer',
        'komen'                 => 'integer',
    ];

    /**
     * Seluruh komentar pada thread ini
     */
    public function komentar(): HasMany
    {
        return $this->hasMany(KomentarKomunitas::class, 'post_id', 'post_id');
    }

    /**
     * Komentar terverifikasi dari bidan
     */
    public function bidanReplies(): HasMany
    {
        return $this->hasMany(KomentarKomunitas::class, 'post_id', 'post_id')
            ->where('is_bidan_reply', true);
    }

    /**
     * Pemilik postingan
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
