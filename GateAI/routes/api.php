<?php

use App\Http\Controllers\Api\AuthController;
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
});
