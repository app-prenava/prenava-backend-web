<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Saldo extends Model
{
    use HasFactory;

    protected $table = 'saldo';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 
        'amount', 
        'type', 
        'keterangan', 
        'status',
        'admin_id',
        'cancelled_by',
        'cancelled_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Hubungan dengan user (ibu hamil)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Hubungan dengan admin yang memberikan saldo
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Hubungan dengan admin yang membatalkan transaksi
     */
    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}