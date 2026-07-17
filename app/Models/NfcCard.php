<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class NfcCard extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'nfc_cards';

    protected $fillable = [
        'uid', 'card_id', 'ownership', 'status', 'notes',
        'fcba', 'afdeling', 'registered_at',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
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
