<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Uncertainty extends Model
{
    use HasFactory;

    protected $table = 'Uncertainty';
    public $timestamps = false;
    protected $primaryKey = 'Unc_ID'; 
    public $incrementing = true; 

    // 新增 F_ID,  到可填充欄位
    protected $fillable = [
        'F_id', 'Unc_Activity_lower_limit', 'Unc_Activity_upper_limit', 'Unc_Activitydata', 'Unc_regulations', 'Unc_Remark'
    ];

}