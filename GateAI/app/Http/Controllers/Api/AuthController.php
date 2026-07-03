<?php

namespace App\Http\Controllers\Api;

use App\Enums\ResponseCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'account'  => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('account', $request->account)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return Result::error(ResponseCode::UNAUTHORIZED, '账号或密码错误');
        }

        if (!$user->is_enabled) {
            return Result::error(ResponseCode::FORBIDDEN, '账号已被禁用');
        }

        $token = auth('api')->login($user);

        return Result::success('登录成功', [
            'token'      => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();
        return Result::success('已登出');
    }

    public function me(): JsonResponse
    {
        return Result::success('获取用户信息成功', auth('api')->user());
    }

    public function refresh(): JsonResponse
    {
        return Result::success('刷新成功', [
            'token'      => auth('api')->refresh(true),
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }
}
