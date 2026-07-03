<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * 校验当前请求的 token 是否与用户最新登录 token 一致
 * —— 实现"重新登录后旧 token 失效"
 */
class CheckTokenValidity
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user) {
            $currentToken = JWTAuth::getToken();

            // login_token 为空说明是旧数据或首次登录——放行，登录后会自动写入
            if ($currentToken && $user->login_token !== null) {
                $tokenString = (string) $currentToken;
                if ($user->login_token !== 'Bearer ' . $tokenString) {
                    throw new AuthenticationException('账号已在其他设备登录，请重新登录');
                }
            }
        }

        return $next($request);
    }
}
