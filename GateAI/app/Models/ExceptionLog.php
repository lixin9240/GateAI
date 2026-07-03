<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExceptionLog extends Model
{
    protected $table = 'exception_logs';

    public $timestamps = false;

    protected $fillable = [
        'trace_id',    // 链路追踪ID
        'message',     // 异常信息
        'file',        // 发生文件
        'line',        // 发生行号
        'trace',       // 堆栈跟踪
        'sql',         // 执行的SQL
        'bindings',    // SQL绑定参数
        'user_id',     // 触发用户
        'request_url', // 触发请求URL
        'created_at',  // 记录时间
    ];

    protected $casts = [
        'bindings' => 'json',
    ];
}
