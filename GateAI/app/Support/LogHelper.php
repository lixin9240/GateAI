<?php

namespace App\Support;

use App\Models\ApiLog;
use App\Models\BusinessLog;
use App\Models\ExceptionLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogHelper
{
    /**
     * 业务日志（同时写文件和数据库）
     */
    public static function business(string $message, array $context = [], string $level = 'info', ?string $operationType = null): void
    {
        $traceId = request()->attributes->get('trace_id');
        $userId  = auth()->id();
        $ip      = request()->ip();

        Log::channel('business')->{$level}($message, array_merge($context, [
            'trace_id'       => $traceId,
            'user_id'        => $userId,
            'operation_type' => $operationType,
        ]));

        try {
            BusinessLog::create([
                'trace_id'       => $traceId,
                'channel'        => 'business',
                'level'          => $level,
                'message'        => $message,
                'context'        => $context,
                'user_id'        => $userId,
                'ip_address'     => $ip,
                'operation_type' => $operationType,
                'created_at'     => now(),
            ]);
        } catch (\Throwable) {
            // 数据库不可用时不影响业务
        }
    }

    /**
     * 异常日志（同时写文件和数据库）
     */
    public static function exception(Throwable $e, array $extra = [], ?string $label = null): void
    {
        $traceId = request()->attributes->get('trace_id');
        $userId  = auth()->id();

        $context = array_merge([
            'trace_id' => $traceId,
            'file'     => $e->getFile(),
            'line'     => $e->getLine(),
            'trace'    => $e->getTraceAsString(),
        ], $extra);

        $logMessage = $label ?? $e->getMessage();

        Log::channel('exception')->error($logMessage, $context);

        try {
            ExceptionLog::create([
                'trace_id'    => $traceId,
                'message'     => $e->getMessage(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
                'trace'       => $e->getTraceAsString(),
                'sql'         => $extra['sql'] ?? null,
                'bindings'    => $extra['bindings'] ?? null,
                'user_id'     => $userId,
                'request_url' => request()->fullUrl(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * 错误日志 — 非异常的通用错误（写文件和数据库）
     */
    public static function error(string $message, array $context = []): void
    {
        $traceId = request()->attributes->get('trace_id');
        $userId  = auth()->id();

        Log::channel('exception')->error($message, array_merge($context, [
            'trace_id' => $traceId,
        ]));

        try {
            ExceptionLog::create([
                'trace_id'    => $traceId,
                'message'     => $message,
                'file'        => $context['file'] ?? '',
                'line'        => $context['line'] ?? 0,
                'trace'       => $context['trace'] ?? '',
                'sql'         => $context['sql'] ?? null,
                'bindings'    => $context['bindings'] ?? null,
                'user_id'     => $userId,
                'request_url' => request()->fullUrl(),
                'created_at'  => now(),
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * API 请求日志（同时写文件和数据库）
     */
    public static function api(array $data): void
    {
        Log::channel('api')->info('API Request', $data);

        try {
            ApiLog::create(array_merge($data, ['created_at' => now()]));
        } catch (\Throwable) {
        }
    }
}
