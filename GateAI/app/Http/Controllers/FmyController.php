<?php

namespace App\Http\Controllers;

use App\Http\Requests\Fmy\AllListRequest;
use App\Http\Requests\Fmy\ChangePasswordRequest;
use App\Http\Requests\Fmy\LoginLogRequest;
use App\Http\Requests\Fmy\LoginRequest;
use App\Http\Requests\Fmy\RealtimeRequest;
use App\Http\Requests\Fmy\TrendRequest;
use App\Services\Fmy\AuthService;
use App\Services\Fmy\MonitoringService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FmyController extends Controller
{
    public function __construct(
        protected AuthService       $authService,
        protected MonitoringService $monitoringService,
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

    // ============== 监控大屏模块 ==============

    /**
     * 2.1 获取全部设备列表
     * GET /api/equipment/all-list
     */
    public function allList(AllListRequest $request): JsonResponse
    {
        $list = $this->monitoringService->getEquipmentAllList($request->input('reservoir_id'));
        return Result::success('操作成功', $list);
    }

    /**
     * 2.2 实时采集数据
     * GET /api/monitoring/realtime
     */
    public function realtime(RealtimeRequest $request): JsonResponse
    {
        $data = $this->monitoringService->getRealtimeData(
            (int) $request->input('reservoir_id'),
            $request->input('equipment_id') ? (int) $request->input('equipment_id') : null
        );
        return Result::success('操作成功', $data);
    }

    /**
     * 2.3 趋势图表数据
     * GET /api/monitoring/trend
     */
    public function trend(TrendRequest $request): JsonResponse
    {
        $list = $this->monitoringService->getTrendData(
            (int) $request->input('reservoir_id'),
            $request->input('range'),
            $request->input('data_type')
        );
        return Result::success('操作成功', $list);
    }
}
