<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factory_Equipment extends Model
{
    use HasFactory;

    protected $table = 'Factory_Equipment';
    public $timestamps = false;
    protected $primaryKey = 'FE_id'; 
    public $incrementing = true; 

    // 新增 C_ID, Fa_ID, S_ID 到可填充欄位
    protected $fillable = [
        'C_ID', 'Fa_ID', 'S_ID', 'FE_number', 'FE_Name', 'FE_type', 'FE_brand', 'FE_CName', 'FE_EName', 'FE_Status_stop'
    ];

    public function ExEmissionCoefficient()
    {
        return $this->hasMany(ExEmissionCoefficient::class, 'EX_ID', 'EX_ID');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'S_ID', 'S_ID');
    }
}