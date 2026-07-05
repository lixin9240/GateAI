<?php

namespace App\Http\Middleware;

use App\Support\LogHelper;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiLogMiddleware
{
    private const SENSITIVE_FIELDS = [
        'password', 'password_confirmation', 'secret',
        'old_password', 'new_password', 'new_password_confirmation',
    ];

    private const SENSITIVE_HEADERS = [
        'authorization', 'cookie', 'x-csrf-token',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('api_log_start', microtime(true));
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $start = $request->attributes->get('api_log_start', microtime(true));
        $durationMs = round((microtime(true) - $start) * 1000);

        LogHelper::api([
            'trace_id'        => $request->attributes->get('trace_id'),
            'url'             => $request->fullUrl(),
            'method'          => $request->method(),
            'ip'              => $request->ip(),
            'user_id'         => auth()->id(),
            'request'         => $this->filterSensitive($request->all()),
            'response_status' => $response->getStatusCode(),
            'duration_ms'     => $durationMs,
            'user_agent'      => $request->userAgent(),
            'response_body'   => $this->extractResponseBody($response),
            'request_headers' => $this->filterHeaders($request->header()),
        ]);
    }

    private function extractResponseBody(Response $response): array
    {
        if ($response instanceof JsonResponse) {
            $body = $response->getData(true);
            if (isset($body['data']) && is_array($body['data'])) {
                $body['data'] = ['_count' => count($body['data'])];
            }
            return $body;
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            if (isset($decoded['data']) && is_array($decoded['data'])) {
                $decoded['data'] = ['_count' => count($decoded['data'])];
            }
            return $decoded;
        }

        return ['_raw_length' => strlen($content)];
    }

    private function filterSensitive(array $data): array
    {
        return $this->recursiveExcept($data, self::SENSITIVE_FIELDS);
    }

    private function recursiveExcept(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = '***';
            }
        }
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = $this->recursiveExcept($v, $keys);
            }
        }
        return $data;
    }

    private function filterHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), self::SENSITIVE_HEADERS, true)) {
                $result[$key] = '***';
            } else {
                $result[$key] = is_array($value) && count($value) === 1 ? $value[0] : $value;
            }
        }
        return $result;
    }
}
