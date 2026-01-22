<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Model
{
    use HasApiTokens, HasFactory;

    protected $connection = 'sips_production'; // Menggunakan koneksi oracle_iplas
    protected $table = 'employee';
	protected $fillable = [
		'FCCODE',
		'FCNAME',
		'SECTIONNAME',
		'GANGCODE',
		'FCBA',
		'UPDATED_BY',
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
