<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleRent extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_no',
        'fcba',
        'vehicle_code',
        'vehicle_name',
        'registration_no',
        'nik',
        'driver_name',
        'tanggal',
        'valid_from',
        'valid_until',
    ];
}
