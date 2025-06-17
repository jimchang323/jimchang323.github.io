<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factory_area extends Model
{
    use HasFactory;

    protected $table = 'dbo.Factory_area'; // 修正表名

    protected $fillable = [
        'C_ID',
        'Fa_ID',
        'F_number',
        'F_Name',
        'F_Control',
        'F_Address',
        'F_Phone',
        'F_Mail',
        'F_People',
        'S_ID', // 確保 S_ID 可填入
    ];

    protected $primaryKey = 'Fa_ID';
    public $timestamps = false;

    public function company()
    {
        return $this->belongsTo(Company::class, 'C_ID', 'C_ID');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'S_ID', 'S_ID'); // 修正為 belongsTo，基於 S_ID
    }
}