<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PregnancyAssessment extends Model
{
    use HasFactory;

    protected $table = 'pregnancy_assessments';
    protected $primaryKey = 'assessment_id';

    protected $fillable = [
        'pregnancy_id',
        'bmi',
        'hypertension',
        'is_diabetes',
        'gestational_diabetes',
        'is_fever',
        'is_high_heart_rate',
        'previous_complications',
        'mental_health_issue',
        'back_pain',
        'low_impact_pref',
        'water_access',
        'placenta_previa',
    ];

    protected $casts = [
        'bmi'                    => 'float',
        'hypertension'           => 'boolean',
        'is_diabetes'            => 'boolean',
        'gestational_diabetes'   => 'boolean',
        'is_fever'               => 'boolean',
        'is_high_heart_rate'     => 'boolean',
        'previous_complications' => 'boolean',
        'mental_health_issue'    => 'boolean',
        'back_pain'              => 'boolean',
        'low_impact_pref'        => 'boolean',
        'water_access'           => 'boolean',
        'placenta_previa'        => 'boolean',
    ];

    /**
     * Relasi ke Pregnancy
     */
    public function pregnancy(): BelongsTo
    {
        return $this->belongsTo(Pregnancy::class, 'pregnancy_id', 'pregnancy_id');
    }
}
