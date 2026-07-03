<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessLog extends Model
{
    protected $table = 'business_logs';

    public $timestamps = false;

    protected $fillable = [
        'trace_id',
        'channel',
        'level',
        'message',
        'context',
        'user_id',
        'ip_address',
        'operation_type',
        'created_at',
    ];

    protected $casts = [
        'context' => 'json',
    ];
}
