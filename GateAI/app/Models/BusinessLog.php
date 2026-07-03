<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessLog extends Model
{
    protected $table = 'business_logs';

    public $timestamps = false;

    protected $fillable = [
        'trace_id',       // 链路追踪ID
        'channel',        // 日志通道
        'level',          // 日志级别
        'message',        // 日志内容
        'context',        // 上下文数据
        'user_id',        // 操作人
        'ip_address',     // 操作人IP
        'operation_type', // 操作类型
        'created_at',     // 记录时间
    ];

    protected $casts = [
        'context' => 'json',
    ];
}
