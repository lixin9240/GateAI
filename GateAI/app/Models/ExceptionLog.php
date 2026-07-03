<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExceptionLog extends Model
{
    protected $table = 'exception_logs';

    public $timestamps = false;

    protected $fillable = [
        'trace_id',
        'message',
        'file',
        'line',
        'trace',
        'sql',
        'bindings',
        'user_id',
        'request_url',
        'created_at',
    ];

    protected $casts = [
        'bindings' => 'json',
    ];
}
