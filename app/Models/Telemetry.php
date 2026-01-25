<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Telemetry extends Model
{
    protected $table = 'telemetries';

    protected $fillable = [
        'kebun', 'ph', 'tds', 'suhu', 'cal_ph_netral', 'cal_ph_asam', 'cal_tds_k', 'tds_mentah', 'recorded_at'
    ];

    protected $casts = [
        'ph' => 'float',
        'tds' => 'integer',
        'suhu' => 'float',
        'cal_ph_netral' => 'float',
        'cal_ph_asam' => 'float',
        'cal_tds_k' => 'float',
        'tds_mentah' => 'integer',
        'recorded_at' => 'datetime',
    ];
}
