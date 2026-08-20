<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class Vehicle extends Model
{
    use HasApiTokens, HasFactory;

    protected $table = 'V_VEHICLE_FULL'; // Nama tabel tanpa prefix schema
	protected $fillable = [
		'FCCODE',
		'FCNAME',
		'VEHICLEGROUPCODE',
		'DATECREATED',
		'REGISTRATIONNO',
		'MAKE',
		'MODEL',
		'YEAROFMADE',
		'YEAROFPURCHASE',
	];

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->timezone('Asia/Makassar')->toDateTimeString();
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->timezone('Asia/Makassar')->toDateTimeString();
    }
}
