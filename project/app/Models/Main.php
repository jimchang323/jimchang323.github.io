<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Main extends Model
{
    protected $table = 'Main';
    protected $primaryKey = 'M_ID';
    public $incrementing = true;
    protected $fillable = [
       'C_ID', 'S_ID', 'Fa_ID', 'CM_ID', 'M_Name', 'M_year',
    'M_foundationyear', 'M_Industry', 'M_Status_stop'
    ];
    public $timestamps = false; // 禁用時間戳

    public function company()
    {
        return $this->belongsTo(Company::class, 'C_ID', 'C_ID');
    }

    public function factory_area()
    {
        return $this->belongsTo(Factory_area::class, 'Fa_ID', 'Fa_ID');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'S_ID', 'S_ID');
    }
    
    public function coefficient_main()
    {
        return $this->belongsTo(CoefficientMain::class, 'CM_ID', 'CM_ID');
    }

}