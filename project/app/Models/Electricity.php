<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Electricity extends Model
{
    protected $table = 'Electricity';  // 資料表名稱
    protected $primaryKey = 'ELE_ID';  // 主鍵
    public $timestamps = false;  // 資料表無自動時間戳
    protected $fillable = ['CM_ID', 'ELE_name','ELE_coefficient','ELE_co2','ELE_unit','ELE_source','ELE_Status_stop'];  // 可填充的欄位

    // 關聯 CoefficientMain
    public function coefficientMain()
    {
        return $this->belongsTo(CoefficientMain::class, 'CM_ID');
    }
}
