<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refrigerant extends Model
{
    protected $table = 'Refrigerant';  // 資料表名稱
    protected $primaryKey = 'REF_ID';  // 主鍵
    public $timestamps = false;  // 資料表無自動時間戳
    protected $fillable = ['CM_ID', 'REF_name','REF_coefficient','ELE_GWP','ELE_source','REF_Status_stop'];  // 可填充的欄位

    // 關聯 CoefficientMain
    public function coefficientMain()
    {
        return $this->belongsTo(CoefficientMain::class, 'CM_ID');
    }
}
