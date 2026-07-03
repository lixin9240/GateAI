<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $table = 'api_logs';

    public $timestamps = false;

    protected $fillable = [
        'trace_id',
        'url',
        'method',
        'ip',
        'user_id',
        'request',
        'response_status',
        'duration_ms',
        'user_agent',
        'response_body',
        'request_headers',
        'created_at',
    ];

    protected $casts = [
        'request'         => 'json',
        'response_body'   => 'json',
        'request_headers' => 'json',
    ];
}
