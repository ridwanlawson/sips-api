<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppUpload extends Model
{
    protected $table = 'app_uploads';

    protected $fillable = [
        'app_name',
        'platform',
        'version',
        'min_version',
        'force_update',
        'file_name',
        'file_path',
        'file_size',
        'file_extension',
        'changelog',
        'uploaded_by',
        'created_at'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'force_update' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
