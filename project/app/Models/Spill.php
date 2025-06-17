<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Spill extends Model
{
    protected $table = 'Spill';
    protected $primaryKey = 'Sp_id';
    public $timestamps = false;
    protected $fillable = ['Sp_Raw_material_name', 'CM_ID','Sp_Device_name','Sp_BOD' ,'Sp_unit' ,'Sp_Sewage','Sp_working_days','Sp_apiece','Sp_Wastewater_volume','Sp_Processing_efficiency','Sp_Emission_coefficient','Sp_unit2','Sp_Status_stop'];

    protected $casts = [
        'Sp_Status_stop' => 'boolean',
    ];
    // 關聯 CoefficientMain
    public function coefficientMain()
    {
        return $this->belongsTo(CoefficientMain::class, 'CM_ID');
    }
}
