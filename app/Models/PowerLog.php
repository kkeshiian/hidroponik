<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PowerLog extends Model
{
    protected $table = 'power_logs';

    protected $fillable = [
        'device_name',
        'state',
        'mode',
        'current_ma',
        'timestamp',
        'is_estimated',
    ];

    protected $casts = [
        'current_ma' => 'float',
        'timestamp' => 'datetime',
        'is_estimated' => 'boolean',
    ];
}
