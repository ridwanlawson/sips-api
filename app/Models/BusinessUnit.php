<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Carbon\Carbon;

class BusinessUnit extends Model
{
    use HasApiTokens, HasFactory;

    protected $connection = 'sips_production'; // Menggunakan koneksi sips_production
    protected $table = 'BUSINESSUNIT'; // Nama tabel tanpa prefix schema
    protected $fillable = [];

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->timezone('Asia/Makassar')->toDateTimeString();
    }

    public function getUpdatedAtAttribute($value)
    {
        return Carbon::parse($value)->timezone('Asia/Makassar')->toDateTimeString();
    }
}
