<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    use HasFactory;

    protected $table = 'badges';

    protected $fillable = [
        'badge_code',
        'name',
        'category',
        'description',
        'icon',
        'bonus_points',
        'requirement_type',
        'requirement_threshold',
        'is_active',
    ];

    protected $casts = [
        'bonus_points' => 'integer',
        'requirement_threshold' => 'integer',
        'is_active' => 'boolean',
    ];

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class, 'badge_id', 'id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badges', 'badge_id', 'user_id', 'id', 'user_id')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }
}
