<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    // 定義資料表名稱
    protected $table = 'Company';

    // 定義可大量指派的欄位
    protected $fillable = [
        'C_ID', 'C_Account', 'C_Password', 'C_Name', 'C_UBN', 'C_CN', 'C_BN', 'C_Control',
        'C_Addres', 'C_Phone', 'C_Person', 'C_People', 'C_mail', 'C_remember', 'C_money',
        'C_Industry', 'C_Status_stop'
    ];

    // 隱藏的欄位（例如密碼）
    protected $hidden = [
        'C_Password',
    ];

    // 禁用時間戳（因為資料表中沒有 created_at 和 updated_at）
    public $timestamps = false;

    protected $primaryKey = 'C_ID';
    
    public function floorPlans()
    {
        return $this->hasMany(FloorPlan::class, 'C_ID', 'C_ID');
    }
}