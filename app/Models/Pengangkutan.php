<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Pengangkutan extends Model
{
    use HasFactory;

    protected $table = 'PENGANGKUTAN';
    protected $fillable = [
        'NOPENGANGKUTAN',
        'NOSPB',
        'NODOKUMEN',
        'TANGGAL',
        'KODE_KARYAWAN_KERANI',
        'KODE_KARYAWAN_DRIVER',
        'TKBM1',
        'TKBM2',
        'TKBM3',
        'TKBM4',
        'TKBM5',
        'TYPE_PENGANGKUTAN',
        'KODE_KENDARAAN',
        'TPH',
        'FIELDCODE',
        'TOTALJANJANG',
        'OUTPUT',
        'JANJANGNORMAL',
        'BRONDOLAN',
        'STATUS_PENGANGKUTAN',
        'AFDELING',
        'FCBA',
        'PABRIK_TUJUAN',
        'IMAGES',
        'CARD_ID',
        'FLAG',
        'CREATED_BY',
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
