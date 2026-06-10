<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Map extends Model
{
    use HasFactory;

    protected $fillable = [
        'geojson',
        'type_map',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | MUTATOR → Saat SIMPAN ke DB (array → JSON string)
    |--------------------------------------------------------------------------
    */
    public function setGeojsonAttribute($value)
    {
        // Jika array/object dari controller → encode ke string
        $this->attributes['geojson'] = is_array($value) || is_object($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE)
            : $value;
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSOR → Saat DIAMBIL dari DB (CLOB → array)
    |--------------------------------------------------------------------------
    */
    public function getGeojsonAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        // Oracle kadang kirim CLOB sebagai stream
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        $decoded = json_decode($value, true);

        // Jika ternyata masih string (data lama double encode)
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
