<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class FeatureSetting extends Model
{
    use HasFactory;

    protected $table = 'feature_settings';

    protected $fillable = [
        'menu', 'feature', 'fcba', 'afdeling', 'status',
        'created_by', 'updated_by',
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
