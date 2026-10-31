<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatatanKunjungan extends Model
{
    use HasFactory;

    protected $table = 'catatankunjungan';
    protected $primaryKey = 'kunjungan_id';

    protected $fillable = [
        'kunjungan_id',
        'tanggal_kunjungan', 
        'keluhan', 
        'pertanyaan', 
        'hasil_kunjungan', 
    ];
    
}