<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Harvesting extends Model
{
    use HasFactory;

    protected $table = 'HARVESTING';
    protected $fillable = [
        'NODOKUMEN',
        'TANGGAL',
        'KODE_KARYAWAN_MANDOR1',
        'KODE_KARYAWAN_MANDOR_PANEN',
        'KODE_KARYAWAN_KERANI',
        'KODE_KARYAWAN',
        'NOANCAK',
        'TPH',
        'FIELDCODE',
        'OUTPUT',
        'MENTAH',
        'OVERRIPE',
        'BUSUK',
        'BUSUK2',
        'BUAHKECIL',
        'PARTENO',
        'BRONDOL',
        'ALASBRONDOL',
        'TANGKAIPANJANG',
        'STATUS_ASSISTENSI',
        'STATUS_HARVESTING',
        'IMAGES',
        'AFDELING',
        'FCBA',
        'ID_DEVICE',
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
