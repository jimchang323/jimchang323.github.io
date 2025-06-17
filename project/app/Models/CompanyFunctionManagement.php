<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyFunctionManagement extends Model
{
    use HasFactory;

    // 定義資料表名稱（如果不是預設的複數形式，可以自行指定）
    protected $table = 'company_function_management';

    // 定義可填入的欄位（目前留空，之後可根據需求添加）
    protected $fillable = [];
}