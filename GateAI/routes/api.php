<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LX\IncidentController;
use App\Http\Controllers\Api\LX\ScenarioController;
use App\Http\Controllers\Api\LX\SimulationController;
use App\Http\Controllers\Api\WeatherController;
use Illuminate\Support\Facades\Route;

// 公开接口
Route::post('login', [AuthController::class, 'login']);

// 需要认证的接口
Route::middleware('auth:api')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

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
