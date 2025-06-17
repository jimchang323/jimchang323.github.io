<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'Category';
    protected $primaryKey = 'Cat_id';
    public $timestamps = false;
    protected $fillable = ['Cat_CName', 'Cat_EName','Cat_Category' ,'Cat_Ecategory' ,'C_Status_stop'];

    // 定義與 Subcategory 的關聯
    public function subcategories()
    {
        return $this->hasMany(Subcategory::class, 'Cat_id', 'Cat_id');
    }

}

