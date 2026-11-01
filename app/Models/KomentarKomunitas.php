<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KomentarKomunitas extends Model
{
    use HasFactory;

    protected $table = 'komentarkomunitas';

    protected $fillable = [
        'post_id',
        'user_id',
        'komentar',
        'is_bidan_reply',
        'is_pinned',
        'suggest_appointment',
        'bidan_user_id',
    ];

    protected $casts = [
        'is_bidan_reply'      => 'boolean',
        'is_pinned'           => 'boolean',
        'suggest_appointment' => 'boolean',
        'bidan_user_id'       => 'integer',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    public function komunitas(): BelongsTo
    {
        return $this->belongsTo(Komunitas::class, 'post_id', 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function bidan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bidan_user_id', 'user_id');
    }
}
