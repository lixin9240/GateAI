<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WeatherController;
use App\Http\Controllers\Wjc\WjcAlarmController;
use App\Http\Controllers\Wjc\WjcDispatchController;
use App\Http\Controllers\Wjc\WjcReservoirController;
use App\Http\Controllers\Wjc\WjcEdgeNodeController;
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
});
