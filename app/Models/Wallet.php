<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $table = 'wallets';
    protected $primaryKey = 'wallets_id';

    protected $fillable = [
        'user_id',
        'wallet_ad',
        'wallet_dinkes',
    ];

    protected $casts = [
        'wallet_ad'     => 'decimal:2',
        'wallet_dinkes' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallets_id', 'wallets_id');
    }

    /**
     * Credit Dinkes wallet (reward kepatuhan maternal)
     */
    public function creditDinkes(float $amount, string $status = 'success'): WalletTransaction
    {
        $this->wallet_dinkes += $amount;
        $this->save();

        return $this->transactions()->create([
            'sof'        => 'dinkes',
            'amount'     => $amount,
            'trx_action' => 'kredit',
            'status'     => $status,
        ]);
    }

    /**
     * Debit Dinkes wallet (penukaran diskon / voucher klinik bidan)
     */
    public function debitDinkes(float $amount, string $status = 'success'): ?WalletTransaction
    {
        if ($this->wallet_dinkes < $amount) {
            return null;
        }

        $this->wallet_dinkes -= $amount;
        $this->save();

        return $this->transactions()->create([
            'sof'        => 'dinkes',
            'amount'     => $amount,
            'trx_action' => 'debit',
            'status'     => $status,
        ]);
    }

    /**
     * Credit Ad wallet
     */
    public function creditAd(float $amount, string $status = 'success'): WalletTransaction
    {
        $this->wallet_ad += $amount;
        $this->save();

        return $this->transactions()->create([
            'sof'        => 'ad',
            'amount'     => $amount,
            'trx_action' => 'kredit',
            'status'     => $status,
        ]);
    }
}
