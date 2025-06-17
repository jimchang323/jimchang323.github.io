<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GWPMain extends Model
{
    protected $table = 'GWPMain';  // 資料表名稱
    protected $primaryKey = 'GWP_ID';  // 主鍵
    public $timestamps = false;  // 資料表無自動時間戳
    protected $fillable = ['CM_ID', 'G_id'];  // 可填充的欄位

    // 關聯 CoefficientMain
    public function coefficientMain()
    {
        return $this->belongsTo(CoefficientMain::class, 'CM_ID');
    }

    // 關聯 Gas
    public function gas()
    {
        return $this->belongsTo(Gas::class, 'G_id');
    }
}
