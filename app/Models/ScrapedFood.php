<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapedFood extends Model
{
    protected $table = 'scraped_foods';

    protected $fillable = [
        'name',
        'description',
        'image_url',
        'price_estimate',
        'category',
        'source_url',
    ];
}
