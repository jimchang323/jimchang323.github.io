<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    // 定義資料表名稱
    protected $table = 'Staff';

    // 定義可大量指派的欄位
    protected $fillable = [
        'C_ID', 'Fa_ID', 'S_number', 'S_Name', 'S_Address', 'S_Phone', 'S_Mail',
        'S_department', 'S_extension', 'S_Account', 'S_Password'
    ];

    // 隱藏的欄位（例如密碼）
    protected $hidden = [
        'S_Password',
    ];

    // 禁用時間戳（因為資料表中沒有 created_at 和 updated_at）
    public $timestamps = false;

    protected $primaryKey = 'S_ID';

    // 定義與 Company 的關係
    public function company()
    {
        return $this->belongsTo(Company::class, 'C_ID', 'C_ID');
    }

    // 定義與 Factory_area 的關係
    public function factory_area()
    {
        return $this->belongsTo(Factory_area::class, 'Fa_ID', 'Fa_ID');
    }
}