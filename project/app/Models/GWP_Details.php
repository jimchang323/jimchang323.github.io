<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GWP_Details extends Model
{
    protected $table = 'GWP_Details';  // 資料表名稱
    protected $primaryKey = 'GWPD_ID';  // 主鍵
    public $timestamps = false;  // 資料表無自動時間戳
    protected $fillable = [
        'GWP_ID', 'CM_ID', 'GWPD_code', 'GWPD_formula', 'GWPD_report1',
        'GWPD_report2', 'GWPD_report3', 'GWPD_report4', 'GWPD_Status_stop'
    ];  // 可填充的欄位

    // 關聯 GWPMain
    public function gwpMain()
    {
        return $this->belongsTo(GWPMain::class, 'GWP_ID');
    }

    // 關聯 CoefficientMain
    public function coefficientMain()
    {
        return $this->belongsTo(CoefficientMain::class, 'CM_ID');
    }
}
