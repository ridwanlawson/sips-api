<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_id',
        'mac_address',
        'imei',
        'device_name',
        'platform',
        'os_version',
        'app_version',
        'assigned_to',
        'status',
        'registered_at',
        'registered_by',
        'last_login_at',
        'last_latitude',
        'last_longitude',
        'notes',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'last_login_at' => 'datetime',
        'last_latitude' => 'float',
        'last_longitude' => 'float',
    ];
}
