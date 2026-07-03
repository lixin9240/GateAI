<?php

namespace App\Http\Controllers\Api;

use App\Enums\ResponseCode;
use App\Http\Controllers\Controller;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'account'  => 'required|string',
            'password' => 'required|string',
        ]);

        $token = auth('api')->attempt($request->only('account', 'password'));

        if (! $token) {
            return Result::error(ResponseCode::UNAUTHORIZED, '账号或密码错误');
        }

        return Result::success('登录成功', [
            'token'      => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl', 43200) * 60,
        ]);
    }

    public function logout(): JsonResponse
    {
        return Result::success('已登出，请客户端丢弃 token');
    }

    public function me(): JsonResponse
    {
        return Result::success('获取用户信息成功', auth('api')->user());
    }
}
