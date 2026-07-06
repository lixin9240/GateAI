<?php

namespace App\Http\Controllers\Api;

use App\Enums\ResponseCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\LogHelper;
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
            LogHelper::business('用户登录失败-账号或密码错误', [
                'account' => $request->account,
                'ip'      => $request->ip(),
            ], 'warning', 'LOGIN_FAIL');

            return Result::error(ResponseCode::UNAUTHORIZED, '账号或密码错误');
        }

        if (!$user->is_enabled) {
            LogHelper::business('用户登录失败-账号已禁用', [
                'user_id' => $user->id,
                'account' => $user->account,
                'ip'      => $request->ip(),
            ], 'warning', 'LOGIN_FAIL');

            return Result::error(ResponseCode::FORBIDDEN, '账号已被禁用');
        }

        $token = auth('api')->login($user);

        // 记录最新 token，配合 CheckTokenValidity 实现"重新登录后旧 token 失效"
        $user->update(['login_token' => 'Bearer ' . $token]);

        LogHelper::business('用户登录成功', [
            'user_id' => $user->id,
            'account' => $user->account,
            'ip'      => $request->ip(),
        ], 'info', 'LOGIN_SUCCESS');

        return Result::success('登录成功', [
            'token'      => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = auth('api')->user();
        auth('api')->logout();

        LogHelper::business('用户登出成功', [
            'user_id' => $user?->id,
            'account' => $user?->account,
        ], 'info', 'LOGOUT');

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
