<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subcategory extends Model
{
    protected $table = 'Subcategory';
    protected $primaryKey = 'Sub_id';
    public $timestamps = false;

    protected $fillable = [
        'Cat_id',
        'Sub_CName',
        'Sub_EName',
        'S_Status_stop'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class, 'Cat_id', 'Cat_id');
    }

    public function ExEmissionCoefficient()
    {
        return $this->hasMany(ExEmissionCoefficient::class, 'EX_ID', 'EX_ID');
    }
}