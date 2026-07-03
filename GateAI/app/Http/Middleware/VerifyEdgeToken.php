<?php

namespace App\Http\Middleware;

use App\Enums\ResponseCode;
use App\Support\LogHelper;
use App\Support\Result;
use Closure;
use Illuminate\Http\Request;

class VerifyEdgeToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (! $token) {
            LogHelper::error('[异常拦截] 缺少边缘端Token', [
                'ip'  => $request->ip(),
                'url' => $request->fullUrl(),
            ]);

            return Result::error(ResponseCode::UNAUTHORIZED, '边缘端认证失败：缺少Token');
        }

        // Token 过期检测（JWT 由 auth:api 中间件处理，此处为补充校验）
        // 重放攻击检测（后续可在此处引入 nonce 校验）
        // 签名校验（后续可在此处引入 HMAC 校验）

        return $next($request);
    }
}
