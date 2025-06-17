<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoefficientMain extends Model
{
    use HasFactory;

    protected $table = 'CoefficientMain';
    protected $primaryKey = 'CM_ID';
    public $timestamps = false;
    protected $fillable = [
        'CM_order', 'CM_Manage_Name', 'CM_path', 'CM_Introduction',
        'CM_law', 'CM_year', 'CM_source', 'CM_Status_stop'
    ];

    protected $casts = [
        'CM_Status_stop' => 'boolean',
    ];

    public function emissionCoefficients()
    {
        return $this->hasMany(ExEmissionCoefficient::class, 'CM_ID', 'CM_ID');
    }
}