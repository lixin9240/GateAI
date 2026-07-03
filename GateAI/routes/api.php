<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GYZ\SettingsModelController;
use App\Http\Controllers\Api\GYZ\SettingsThresholdController;
use App\Http\Controllers\Api\GYZ\SettingsWeightController;
use App\Http\Controllers\Api\GYZ\UserManagementController;
use App\Http\Controllers\Api\LX\IncidentController;
use App\Http\Controllers\Api\LX\ScenarioController;
use App\Http\Controllers\Api\LX\SimulationController;
use App\Http\Controllers\Api\WeatherController;
use App\Http\Controllers\Api\WjcController;
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


    // 气象模块
    Route::prefix('weather')->group(function () {
        Route::get('current', [WeatherController::class, 'current']);
        Route::get('hourly', [WeatherController::class, 'hourly']);
        Route::get('daily', [WeatherController::class, 'daily']);
        Route::get('snapshot', [WeatherController::class, 'snapshot']);
    });

    // 数字孪生模块
    Route::prefix('simulation')->group(function () {
        // 仿真场景
        Route::get('scenarios', [ScenarioController::class, 'scenarios']);

        // 仿真任务
        Route::post('start', [SimulationController::class, 'start'])->name('simulation.start');
        Route::get('{id}/result', [SimulationController::class, 'result']);
        Route::post('{id}/report', [SimulationController::class, 'report'])->name('simulation.report');

        // 故障复盘
        Route::get('incidents', [IncidentController::class, 'incidents']);
        Route::post('import-incident', [IncidentController::class, 'importIncident']);
    });

    // ─── 8. 系统设置模块 ────────────────────────
    Route::prefix('settings')->group(function () {

        // 8.1 告警阈值配置
        Route::get('thresholds', [SettingsThresholdController::class, 'index']);
        Route::put('thresholds/{id}', [SettingsThresholdController::class, 'update']);

        // 8.2 多目标权重配置
        Route::get('weights', [SettingsWeightController::class, 'show']);
        Route::put('weights', [SettingsWeightController::class, 'update']);

        // 8.3 AI 模型管理
        Route::get('models', [SettingsModelController::class, 'index']);
        Route::post('models/upload', [SettingsModelController::class, 'upload']);
        Route::post('models/{id}/activate', [SettingsModelController::class, 'activate']);
        Route::post('models/{id}/rollback', [SettingsModelController::class, 'rollback']);
        Route::delete('models/{id}', [SettingsModelController::class, 'destroy']);
        Route::post('models/{id}/deploy', [SettingsModelController::class, 'deploy']);

        // 8.4 用户管理
        Route::get('users', [UserManagementController::class, 'index']);
        Route::post('users', [UserManagementController::class, 'store']);
        Route::put('users/{id}', [UserManagementController::class, 'update']);
        Route::post('users/{id}/reset-password', [UserManagementController::class, 'resetPassword']);
        Route::post('users/{id}/lock', [UserManagementController::class, 'lock']);
        Route::post('users/{id}/unlock', [UserManagementController::class, 'unlock']);
        Route::delete('users/{id}', [UserManagementController::class, 'destroy']);
    });
});

// AI 推理测试路由
Route::post('/infer', function (Request $request) {
    $sensor = [
        'upstream_level'   => $request->input('upstream_level', 180),
        'downstream_level' => $request->input('downstream_level', 120),
        'inflow'           => $request->input('inflow', 200),
        'rainfall'         => $request->input('rainfall', 0),
        'temperature'      => $request->input('temperature', 20),
        'gate1_opening'    => $request->input('gate1_opening', 0.3),
        'gate2_opening'    => $request->input('gate2_opening', 0.2),
        'gate3_opening'    => $request->input('gate3_opening', 0.4),
    ];

    $result = \App\Services\HydropowerService::infer($sensor);

    if (!$result) {
        return response()->json(['code' => 90003, 'msg' => 'AI推理失败', 'success' => false]);
    }

    return response()->json([
        'code'    => 0,
        'msg'     => '操作成功',
        'success' => true,
        'data'    => $result,
    ]);
});
