<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment_inventory_details extends Model
{
    protected $table = 'Equipment_Inventory_Details';
    protected $primaryKey = 'EID_ID';
    public $incrementing = true;
    protected $fillable = [
        'FEE_id',
        'EID_Activity_data',
        'EID_Active_data_unit',
        'EID_Data_source',
        'EID_CO2',
        'EID_day',
        'C_ID',
        'EID_Remark', 
        'EID_picture', 
    ];

    public $timestamps = false;

    protected $casts = [
        'EID_day' => 'datetime',
        'EID_Activity_data' => 'float',
        'EID_CO2' => 'float',
    ];

    // 定義外鍵關係
    public function factoryEmissionEquipment()
    {
        return $this->belongsTo(Factory_Equipment_Emission_Sources::class, 'FEE_id', 'FEE_id')
            ->with(['gwpDetail', 'staff']);
    }

    // 關聯 GWP_Details
    public function gwpDetail()
    {
        return $this->hasOneThrough(
            GWP_Details::class,
            Factory_Equipment_Emission_Sources::class,
            'FEE_id', // Factory_Equipment_Emission_Sources 的外鍵
            'GWP_ID', // GWP_Details 的外鍵
            'FEE_id', // Equipment_inventory_details 的本地鍵
            'GWP_ID'  // Factory_Equipment_Emission_Sources 的本地鍵
        );
    }
}