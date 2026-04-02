<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalibrationSetting extends Model
{
    protected $fillable = [
        'kebun',
        'tds_multiplier',
        'suhu_correction'
    ];

    protected $casts = [
        'tds_multiplier' => 'float',
        'suhu_correction' => 'float',
    ];
}
