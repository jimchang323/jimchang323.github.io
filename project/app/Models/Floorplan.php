<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FloorPlan extends Model
{
    use HasFactory;

    // 指定資料表名稱
    protected $table = 'Floor_Plan';

    // 關閉時間戳記自動更新
    public $timestamps = false;

    // 主鍵設定
    protected $primaryKey = 'FP_ID'; 
    public $incrementing = true; 

    // 允許批量賦值的欄位
    protected $fillable = ['C_ID', 'Fa_ID', 'FP_Picture', 'FP_Text', 'FP_Status_stop'];
    
    // 自動轉換欄位類型
    protected $casts = [
        'FP_Status_stop' => 'boolean',
    ];

    // 定義 C_ID 的外鍵關係
    public function company()
    {
        return $this->belongsTo(Company::class, 'C_ID', 'C_ID');
    }

    // 定義 Fa_ID 的外鍵關係，指向 Factory_area 表
    public function factoryArea()
    {
        return $this->belongsTo(Factory_area::class, 'Fa_ID', 'Fa_ID');
    }
}