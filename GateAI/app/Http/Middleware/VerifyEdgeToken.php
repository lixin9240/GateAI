<?php

namespace App\Http\Middleware;

use App\Enums\ResponseCode;
use App\Models\EdgeNode;
use App\Support\LogHelper;
use App\Support\Result;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VerifyEdgeToken
{
    private const TIMESTAMP_TOLERANCE = 300;
    private const NONCE_TTL = 3600;

    public function handle(Request $request, Closure $next)
    {
        $auth = $request->header('Authorization');
        if (!$auth || !str_starts_with($auth, 'EdgeToken ')) {
            LogHelper::error('[EdgeToken] 缺少 EdgeToken 认证', [
                'ip'  => $request->ip(),
                'url' => $request->fullUrl(),
                'auth' => $auth ? substr($auth, 0, 30) . '...' : null,
            ]);
            return Result::error(ResponseCode::UNAUTHORIZED, '边缘端认证失败：缺少 EdgeToken');
        }

        $tokenStr = substr($auth, 10); // 去掉 "EdgeToken "
        $parts = explode('.', $tokenStr);
        if (count($parts) !== 4) {
            LogHelper::error('[EdgeToken] Token 格式错误', [
                'ip'    => $request->ip(),
                'parts' => count($parts),
            ]);
            return Result::error(ResponseCode::UNAUTHORIZED, '边缘端认证失败：Token 格式错误');
        }

        [$edgeId, $timestamp, $nonce, $signature] = $parts;

        // 时间戳防重放
        if (abs(time() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            LogHelper::error('[EdgeToken] 时间戳超出容差', [
                'edge_id'     => $edgeId,
                'timestamp'   => $timestamp,
                'server_time' => time(),
                'diff'        => abs(time() - (int) $timestamp),
            ]);
            return Result::error(ResponseCode::UNAUTHORIZED, '边缘端认证失败：请求已过期');
        }

        // Nonce 防重放
        $nonceKey = "edge_nonce:{$nonce}";
        if (Cache::has($nonceKey)) {
            LogHelper::error('[EdgeToken] Nonce 重复使用', [
                'edge_id' => $edgeId,
                'nonce'   => $nonce,
            ]);
            return Result::error(ResponseCode::UNAUTHORIZED, '边缘端认证失败：重复请求');
        }
        Cache::put($nonceKey, 1, self::NONCE_TTL);

        // 查找边缘节点
        $node = EdgeNode::find($edgeId);
        if (!$node || !$node->api_secret) {
            LogHelper::error('[EdgeToken] 边缘节点不存在或无密钥', ['edge_id' => $edgeId]);
            return Result::error(ResponseCode::UNAUTHORIZED, '边缘端认证失败：无效节点');
        }

        // HMAC-SHA256 校验
        $body = $request->getContent() ?: '';
        $payload = implode("\n", [
            $edgeId,
            $timestamp,
            $nonce,
            strtoupper($request->method()),
            $request->getPathInfo(),
            $body,
        ]);
        $expected = hash_hmac('sha256', $payload, $node->api_secret);

        if (!hash_equals($expected, $signature)) {
            LogHelper::error('[EdgeToken] 签名校验失败', [
                'edge_id'   => $edgeId,
                'expected'  => substr($expected, 0, 16) . '...',
                'received'  => substr($signature, 0, 16) . '...',
            ]);
            return Result::error(ResponseCode::UNAUTHORIZED, '边缘端认证失败：签名无效');
        }

        $request->attributes->set('edge_node', $node);
        return $next($request);
    }
}
