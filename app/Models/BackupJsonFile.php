<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackupJsonFile extends Model
{
    use HasFactory;

    protected $table = 'backup_json_files';

    protected $fillable = [
        'activity',
        'fcba',
        'afdeling',
        'tanggal',
        'year',
        'month',
        'date',
        'file_name',
        'file_path',
        'file_size',
        'url',
        'uploaded_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'file_size' => 'integer',
    ];
}
