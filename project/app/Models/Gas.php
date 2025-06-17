<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Gas extends Model
{
    use HasFactory;

    // 指定資料表名稱
    protected $table = 'gas';

    // 關閉時間戳記自動更新
    public $timestamps = false;

    // 主鍵設定
    protected $primaryKey = 'G_id'; 
    public $incrementing = true; 

    // 允許批量賦值的欄位（加入 ESC_EName 和 ESC_Status_stop）
    protected $fillable = ['G_CName', 'G_EName', 'G_Status_stop'];
    
    public function ExEmissionCoefficient()
    {
         return $this->hasMany(ExEmissionCoefficient::class, 'EX_ID', 'EX_ID');
    }
}