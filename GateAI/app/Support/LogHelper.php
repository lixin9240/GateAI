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
     * 截断超长数据，防止撑爆数据库字段
     */
    private static function safeString(?string $value, int $maxLen = 500): string
    {
        if ($value === null) return '';
        return mb_strlen($value) > $maxLen
            ? mb_substr($value, 0, $maxLen) . '...[truncated]'
            : $value;
    }

    private static function safeJson(array $data, int $maxKb = 64): array
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false || strlen($json) <= $maxKb * 1024) {
            return $data;
        }
        return ['_truncated' => true, '_original_size_kb' => round(strlen($json) / 1024, 1)];
    }

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
                'message'        => self::safeString($message, 500),
                'context'        => self::safeJson($context),
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
                'message'     => self::safeString($e->getMessage(), 1000),
                'file'        => self::safeString($e->getFile(), 255),
                'line'        => $e->getLine(),
                'trace'       => self::safeString($e->getTraceAsString(), 65535),
                'sql'         => self::safeString($extra['sql'] ?? null, 65535),
                'bindings'    => self::safeJson($extra['bindings'] ?? []),
                'user_id'     => $userId,
                'request_url' => self::safeString(request()->fullUrl(), 500),
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
                'message'     => self::safeString($message, 1000),
                'file'        => self::safeString($context['file'] ?? '', 255),
                'line'        => $context['line'] ?? 0,
                'trace'       => self::safeString($context['trace'] ?? '', 65535),
                'sql'         => self::safeString($context['sql'] ?? null, 65535),
                'bindings'    => self::safeJson($context['bindings'] ?? []),
                'user_id'     => $userId,
                'request_url' => self::safeString(request()->fullUrl(), 500),
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
            ApiLog::create([
                'trace_id'        => $data['trace_id'] ?? request()->attributes->get('trace_id'),
                'url'             => self::safeString($data['url'] ?? request()->fullUrl(), 500),
                'method'          => $data['method'] ?? request()->method(),
                'ip'              => $data['ip'] ?? request()->ip(),
                'user_id'         => $data['user_id'] ?? auth()->id(),
                'request'         => self::safeJson($data['request'] ?? [], 256),
                'response_status' => $data['response_status'] ?? 200,
                'duration_ms'     => $data['duration_ms'] ?? 0,
                'user_agent'      => self::safeString($data['user_agent'] ?? '', 500),
                'response_body'   => self::safeJson($data['response_body'] ?? [], 256),
                'request_headers' => self::safeJson($data['request_headers'] ?? [], 64),
                'created_at'      => now(),
            ]);
        } catch (\Throwable) {
        }
    }
}
