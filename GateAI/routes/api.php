<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LX\EdgeController;
use App\Http\Controllers\Api\LX\HistoryController;
use App\Http\Controllers\Api\LX\IncidentController;
use App\Http\Controllers\Api\LX\PhysicalController;
use App\Http\Controllers\Api\LX\ScenarioController;
use App\Http\Controllers\Api\LX\SimulationController;
use App\Http\Controllers\Api\WeatherController;
use App\Http\Controllers\Api\WjcController;
use Illuminate\Support\Facades\Route;

// 公开接口
Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);// 登录

    Route::get('/weather/current', [WeatherController::class, 'current']);// 当前天气
    Route::get('/weather/hourly', [WeatherController::class, 'hourly']);//小时天气
    Route::get('/weather/daily', [WeatherController::class, 'daily']);// 日天气
    Route::get('/weather/snapshot', [WeatherController::class, 'snapshot']);// 快照天气
});

// 需要认证的接口
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);// 登出
    Route::get('/me', [AuthController::class, 'me']);// 获取用户信息

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

    // 10. 历史查询模块
    Route::prefix('history')->group(function () {
        Route::get('data', [HistoryController::class, 'data']);
        Route::post('export', [HistoryController::class, 'export']);
        Route::get('export/{task_id}/status', [HistoryController::class, 'exportStatus']);
    });

    // 数字孪生模块
    Route::prefix('simulation')->group(function () {
        // 仿真场景
        Route::get('scenarios', [ScenarioController::class, 'scenarios']);// 获取仿真场景

        // 仿真任务
        Route::post('start', [SimulationController::class, 'start'])->name('simulation.start');// 启动仿真任务
        Route::get('{id}/result', [SimulationController::class, 'result']);// 获取仿真任务结果
        Route::post('{id}/report', [SimulationController::class, 'report'])->name('simulation.report');// 生成仿真报告

        // 故障复盘
        Route::get('incidents', [IncidentController::class, 'incidents']);// 获取故障复盘
        Route::post('import-incident', [IncidentController::class, 'importIncident']);// 导入故障复盘
    });

    // 11. 边缘端数据上报
    Route::prefix('edge')->group(function () {
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
});
