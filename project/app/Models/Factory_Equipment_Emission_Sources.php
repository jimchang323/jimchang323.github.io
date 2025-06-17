<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factory_Equipment_Emission_Sources extends Model
{
    use HasFactory;

    protected $table = 'Factory_Equipment_Emission_Sources';
    public $timestamps = false;
    protected $primaryKey = 'FEE_id';
    public $incrementing = true;

    protected $fillable = [
        'FE_ID', 'S_ID', 'Cat_id', 'Sub_id', 'G_id', 'ESC_id', 'F_id', 'EX_ELE_REF_ID', 'GWP', 'FEE_Status_stop', 'FEE_JUD_ID', 'DBB_ID', 'flag'
    ];

    public function ExEmissionCoefficient()
    {
        return $this->hasMany(ExEmissionCoefficient::class, 'EX_ID', 'EX_ELE_REF_ID');
    }

    public function DBBoundary()
    {
        return $this->belongsTo(DBBoundary::class, 'DBB_ID', 'DBB_ID');
    }
}