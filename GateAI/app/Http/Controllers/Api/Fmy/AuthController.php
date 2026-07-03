<?php

namespace App\Http\Controllers\Api\Fmy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fmy\ChangePasswordRequest;
use App\Http\Requests\Fmy\LoginLogRequest;
use App\Http\Requests\Fmy\LoginRequest;
use App\Services\Fmy\AuthService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 认证模块 —— 登录、登出、修改密码、登录日志
 */
class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
    ) {}

    /**
     * 1.1 用户登录
     * POST /api/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->only(['account', 'password', 'remember']));
        return Result::success('登录成功', $result);
    }

    /**
     * 1.2 修改密码
     * POST /api/auth/change-pwd
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword($request->user()->id, $request->only(['old_password', 'new_password', 'confirm_password']));
        return Result::success('修改密码成功');
    }

    /**
     * 1.3 用户登出
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user()->id);
        return Result::success('已登出');
    }

    /**
     * 1.4 登录日志分页查询
     * GET /api/login-logs
     */
    public function loginLogs(LoginLogRequest $request): JsonResponse
    {
        $logs = $this->authService->getLoginLogs($request->only(['page', 'page_size', 'start_time', 'end_time']));
        return Result::success('操作成功', $logs);
    }
}
