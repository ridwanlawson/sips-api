<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    protected $table = 'ATTENDANCE';
    protected $fillable = [
        'TANGGAL',
        'KODE_KARYAWAN_MANDOR',
        'KODE_KARYAWAN',
        'TIME_IN',
        'LOCATION_IN',
        'TIME_OUT',
        'LOCATION_OUT',
        'PENGANCAKAN',
        'TOTAL_LATE_TIME',
        'GO_HOME_EARLY',
        'ATTENDANCE_TYPE',
        'EXCEPTION_CASE',
        'NO_BA_EXCA',
        'FCBA',
        'SECTION',
        'GANG',
        'ATTENDANCE',
        'MANDAYS',
        'STATUS_ATTENDANCE',
        'FCBA_DESTINATION',
        'ID_DEVICE',
        'MAC_ADDRESS',
        'IMAGES',
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
