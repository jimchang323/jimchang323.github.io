<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DBBoundary extends Model
{
    use HasFactory;

    protected $table = 'DBBoundary';
    protected $primaryKey = 'DBB_ID';
    public $timestamps = false;

    protected $fillable = [
        'M_ID',
        'Fa_ID',
        'S_ID',
        'DBB_Plan1',
        'DBB_Plan2',
        'DBB_Plan3',
        'DBB_Plan4',
        'DBB_Status_stop',
    ];

    protected $casts = [
        'DBB_Status_stop' => 'boolean',
    ];

    public function Main()
    {
        return $this->belongsTo(Main::class, 'M_ID');
    }

    public function Factory_area()
    {
        return $this->belongsTo(Factory_area::class, 'Fa_ID');
    }

    public function Staff()
    {
        return $this->belongsTo(Staff::class, 'S_ID');
    }
}