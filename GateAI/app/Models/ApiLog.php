<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $table = 'api_logs';

    public $timestamps = false;

    protected $fillable = [
        'trace_id',        // 链路追踪ID
        'url',             // 请求URL
        'method',          // 请求方法
        'ip',              // 客户端IP
        'user_id',         // 用户ID
        'request',         // 请求参数
        'response_status', // 响应状态码
        'duration_ms',     // 耗时（毫秒）
        'user_agent',      // 客户端UA
        'response_body',   // 响应内容
        'request_headers', // 请求头
        'created_at',      // 记录时间
    ];

    protected $casts = [
        'request'         => 'json',
        'response_body'   => 'json',
        'request_headers' => 'json',
    ];
}
