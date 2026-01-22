<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_email',
        'method',
        'endpoint',
        'request_data',
        'response_code',
        'ip_address',
        'user_agent',
        'created_at'
    ];

    protected $casts = [
        'request_data' => 'array'
    ];
}