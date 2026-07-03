<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GYZ\SettingsModelController;
use App\Http\Controllers\Api\GYZ\SettingsThresholdController;
use App\Http\Controllers\Api\GYZ\SettingsWeightController;
use App\Http\Controllers\Api\GYZ\UserManagementController;
use App\Http\Controllers\Api\LX\EdgeController;
use App\Http\Controllers\Api\LX\HistoryController;
use App\Http\Controllers\Api\LX\IncidentController;
use App\Http\Controllers\Api\LX\PhysicalController;
use App\Http\Controllers\Api\LX\ScenarioController;
use App\Http\Controllers\Api\LX\SimulationController;
use App\Http\Controllers\Api\WeatherController;
use App\Http\Controllers\Api\Wjc\WjcAlarmController;
use App\Http\Controllers\Api\Wjc\WjcDispatchController;
use App\Http\Controllers\Api\Wjc\WjcReservoirController;
use App\Http\Controllers\Api\Wjc\WjcEdgeNodeController;
use App\Http\Controllers\Api\Fmy\AuthController as FmyAuthController;
use App\Http\Controllers\Api\Fmy\MonitorController;
use App\Http\Controllers\WjcController;
use Illuminate\Support\Facades\Route;

// 公开接口
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);// 登录

    Route::get('/weather/current', [WeatherController::class, 'current']);// 当前天气
    Route::get('/weather/hourly', [WeatherController::class, 'hourly']);//小时天气
    Route::get('/weather/daily', [WeatherController::class, 'daily']);// 日天气
});

// 需要认证的接口
Route::prefix('v1')->middleware(['auth:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);// 登出
    Route::get('/me', [AuthController::class, 'me']);// 获取用户信息

    // 3. 告警管理模块
    Route::prefix('alarms')->group(function () {
        Route::get('/', [WjcAlarmController::class, 'index']);
        Route::put('/{id}/acknowledge', [WjcAlarmController::class, 'acknowledge']);
        Route::put('/{id}/dispose', [WjcAlarmController::class, 'dispose']);
        Route::get('/exceed-logs', [WjcAlarmController::class, 'exceedLogs']);
    });

    // 4. 调度决策模块
    Route::prefix('dispatch')->group(function () {
        Route::get('/predictions', [WjcDispatchController::class, 'predictions']);
        Route::get('/decisions', [WjcDispatchController::class, 'decisions']);
        Route::get('/decisions/{id}', [WjcDispatchController::class, 'decisionDetail']);
        Route::post('/execute', [WjcDispatchController::class, 'execute']);
        Route::get('/commands/{command_id}/trace', [WjcDispatchController::class, 'traceCommand']);
        Route::get('/gate-actions', [WjcDispatchController::class, 'gateActions']);
        Route::post('/emergency-stop', [WjcDispatchController::class, 'emergencyStop']);
        Route::put('/stop-recover/{id}', [WjcDispatchController::class, 'stopRecover']);
        Route::get('/emergency-stops', [WjcDispatchController::class, 'emergencyStops']);
    });

    // 5. 水库管理
    Route::prefix('reservoirs')->group(function () {
        Route::get('/', [WjcReservoirController::class, 'index']);
        Route::post('/', [WjcReservoirController::class, 'store']);
        Route::get('/{id}', [WjcReservoirController::class, 'show']);
        Route::put('/{id}', [WjcReservoirController::class, 'update']);
        Route::delete('/{id}', [WjcReservoirController::class, 'destroy']);
    });

    // 6. 边缘节点管理
    Route::prefix('edge-nodes')->group(function () {
        Route::get('/', [WjcEdgeNodeController::class, 'index']);
        Route::post('/', [WjcEdgeNodeController::class, 'store']);
        Route::get('/{id}', [WjcEdgeNodeController::class, 'show']);
        Route::post('/{id}/heartbeat', [WjcEdgeNodeController::class, 'heartbeat']);
        Route::delete('/{id}', [WjcEdgeNodeController::class, 'destroy']);
    });

    // 10. 历史查询模块
    Route::prefix('history')->group(function () {
        Route::get('data', [HistoryController::class, 'data']);
        Route::post('export', [HistoryController::class, 'export']);
        Route::get('export/{task_id}/status', [HistoryController::class, 'exportStatus']);
    });

    // 8. 数字孪生模块
    Route::prefix('simulation')->group(function () {
        Route::get('scenarios', [ScenarioController::class, 'scenarios']);// 获取场景列表
        Route::post('scenarios', [ScenarioController::class, 'store']);// 创建场景
        Route::put('scenarios/{id}', [ScenarioController::class, 'update']);// 更新场景
        Route::delete('scenarios/{id}', [ScenarioController::class, 'destroy']);// 删除场景
        Route::post('start', [SimulationController::class, 'start'])->name('simulation.start');// 启动模拟
        Route::get('{id}/result', [SimulationController::class, 'result']);// 获取模拟结果
        Route::post('{id}/report', [SimulationController::class, 'report'])->name('simulation.report');// 提交模拟报告
        Route::get('incidents', [IncidentController::class, 'incidents']);// 获取事件列表
        Route::post('import-incident', [IncidentController::class, 'importIncident']);// 导入事件
    });

    // 11. 边缘端数据上报
    Route::prefix('edge')->middleware(['edge.token'])->group(function () {
        Route::post('monitoring-data', [EdgeController::class, 'reportData'])->name('edge.monitoring');
        Route::post('dispatch-decisions', [EdgeController::class, 'reportDecision'])->name('edge.dispatch');
        Route::put('control-commands/{command_id}/feedback', [EdgeController::class, 'feedback'])->name('edge.feedback');
        Route::post('alarms', [EdgeController::class, 'reportAlarm'])->name('edge.alarm');

        // 12.1 边缘端拉取物理参数
        Route::get('physics-config/{reservoir_id}', [PhysicalController::class, 'edgeConfig']);
    });

    // 12. 物理配置后台管理
    Route::prefix('admin')->group(function () {
        Route::get('physical-parameters', [PhysicalController::class, 'index']);
        Route::post('physical-parameters', [PhysicalController::class, 'upsert']);
        Route::delete('physical-parameters/{id}', [PhysicalController::class, 'delete']);
    });

    // 9. 系统设置模块
    Route::prefix('settings')->group(function () {
        Route::get('thresholds', [SettingsThresholdController::class, 'index']);
        Route::put('thresholds/{id}', [SettingsThresholdController::class, 'update']);
        Route::get('weights', [SettingsWeightController::class, 'show']);
        Route::put('weights', [SettingsWeightController::class, 'update']);
        Route::get('models', [SettingsModelController::class, 'index']);
        Route::post('models/upload', [SettingsModelController::class, 'upload']);
        Route::post('models/{id}/activate', [SettingsModelController::class, 'activate']);
        Route::post('models/{id}/rollback', [SettingsModelController::class, 'rollback']);
        Route::delete('models/{id}', [SettingsModelController::class, 'destroy']);
        Route::post('models/{id}/deploy', [SettingsModelController::class, 'deploy']);
        Route::get('users', [UserManagementController::class, 'index']);
        Route::post('users', [UserManagementController::class, 'store']);
        Route::put('users/{id}', [UserManagementController::class, 'update']);
        Route::post('users/{id}/reset-password', [UserManagementController::class, 'resetPassword']);
        Route::post('users/{id}/lock', [UserManagementController::class, 'lock']);
        Route::post('users/{id}/unlock', [UserManagementController::class, 'unlock']);
        Route::delete('users/{id}', [UserManagementController::class, 'destroy']);
    });
});


// ===== Fmy 模块路由（JWT 认证）=====
// 公开
Route::post('/auth/login', [FmyAuthController::class, 'login']);
// JWT 认证
Route::middleware(['auth:api', 'token.valid'])->group(function () {
    // 1. 认证模块
    //用户登出
    Route::post('/auth/logout', [FmyAuthController::class, 'logout']);
    //修改密码
    Route::post('/auth/change-pwd', [FmyAuthController::class, 'changePassword']);
    //登录日志查询
    Route::get('/login-logs', [FmyAuthController::class, 'loginLogs']);
    // 2. 监控大屏模块
    //获取全部设备列表
    Route::get('/equipment/all-list', [MonitorController::class, 'allList']);
    //实时采集数据
    Route::get('/monitoring/realtime', [MonitorController::class, 'realtime']);
    //趋势图表数据
    Route::get('/monitoring/trend', [MonitorController::class, 'trend']);
});
