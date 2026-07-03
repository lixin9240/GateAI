<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WeatherController;
use App\Http\Controllers\FmyController;
use App\Http\Controllers\WjcController;
use Illuminate\Support\Facades\Route;

// 公开接口
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('/weather/current', [WeatherController::class, 'current']);
    Route::get('/weather/hourly', [WeatherController::class, 'hourly']);
    Route::get('/weather/daily', [WeatherController::class, 'daily']);
    Route::get('/weather/snapshot', [WeatherController::class, 'snapshot']);
});

// 需要认证的接口
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // 3. 告警管理模块
    Route::prefix('alarms')->group(function () {
        Route::get('/', [WjcController::class, 'index']);
        Route::put('/{id}/acknowledge', [WjcController::class, 'acknowledge']);
        Route::put('/{id}/dispose', [WjcController::class, 'dispose']);
        Route::get('/exceed-logs', [WjcController::class, 'exceedLogs']);
    });

    // 4. 调度决策模块
    Route::prefix('dispatch')->group(function () {
        Route::get('/predictions', [WjcController::class, 'predictions']);
        Route::get('/decisions', [WjcController::class, 'decisions']);
        Route::get('/decisions/{id}', [WjcController::class, 'decisionDetail']);
        Route::post('/execute', [WjcController::class, 'execute']);
        Route::get('/commands/{command_id}/trace', [WjcController::class, 'traceCommand']);
        Route::get('/gate-actions', [WjcController::class, 'gateActions']);
        Route::post('/emergency-stop', [WjcController::class, 'emergencyStop']);
        Route::put('/stop-recover/{id}', [WjcController::class, 'stopRecover']);
        Route::get('/emergency-stops', [WjcController::class, 'emergencyStops']);
    });
});


// ===== Fmy 模块路由（JWT 认证）=====
// 公开
Route::post('/auth/login', [FmyController::class, 'login']);
// JWT 认证
Route::middleware(['auth:api', 'token.valid'])->group(function () {
    // 1. 认证模块
    //用户登出
    Route::post('/auth/logout', [FmyController::class, 'logout']);
    //修改密码
    Route::post('/auth/change-pwd', [FmyController::class, 'changePassword']);
    //登录日志查询
    Route::get('/login-logs', [FmyController::class, 'loginLogs']);
    // 2. 监控大屏模块
    //获取全部设备列表
    Route::get('/equipment/all-list', [FmyController::class, 'allList']);
    //实时采集数据
    Route::get('/monitoring/realtime', [FmyController::class, 'realtime']);
    //趋势图表数据
    Route::get('/monitoring/trend', [FmyController::class, 'trend']);
});
