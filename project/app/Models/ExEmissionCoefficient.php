<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExEmissionCoefficient extends Model
{
    use HasFactory;

    protected $table = 'EX_Emission_coefficient';
    protected $primaryKey = 'EX_ID';
    public $timestamps = false;

    protected $fillable = [
        'CM_ID',
        'G_id',
        'Sub_id',
        'ESC_id',
        'F_id',
        'EX_coefficient',
        'EX_lower_limit',
        'EX_upper_limit',
        'EX_Status_stop',
        'EX_recommendation',
        'EX_Coefficientunit',
    ];

    protected $casts = [
        'EX_Status_stop' => 'boolean',
    ];

    public function coefficientMain()
    {
        return $this->belongsTo(CoefficientMain::class, 'CM_ID');
    }

    public function gas()
    {
        return $this->belongsTo(Gas::class, 'G_id');
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class, 'Sub_id');
    }

    public function emissionSourceCategory()
    {
        return $this->belongsTo(EmissionSourceCategory::class, 'ESC_id' );
    }

    public function fuel()
    {
        return $this->belongsTo(Fuel::class, 'F_id', );
    }
}