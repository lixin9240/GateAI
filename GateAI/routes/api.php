<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WjcController;


Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    
    // 3.告警管理模块
    Route::prefix('alarms')->group(function () {
        Route::get('/', [WjcController::class, 'index']); // 3.1 正式告警列表
        Route::put('/{id}/acknowledge', [WjcController::class, 'acknowledge']); // 3.2 确认告警
        Route::put('/{id}/dispose', [WjcController::class, 'dispose']); // 3.3 处置告警
        Route::get('/exceed-logs', [WjcController::class, 'exceedLogs']); // 3.4 瞬时超限日志
    });

    // 4.调度决策模块
    Route::prefix('dispatch')->group(function () {
        Route::get('/predictions', [WjcController::class, 'predictions']); // 4.1 LSTM预测
        Route::get('/decisions', [WjcController::class, 'decisions']); // 4.3 决策历史列表
        Route::get('/decisions/{id}', [WjcController::class, 'decisionDetail']); // 4.2 决策详情
        Route::post('/execute', [WjcController::class, 'execute']); // 4.4 人工下发
        Route::get('/commands/{command_id}/trace', [WjcController::class, 'traceCommand']); // 4.5 指令追踪
        Route::get('/gate-actions', [WjcController::class, 'gateActions']); // 4.6 闸门动作历史
        Route::post('/emergency-stop', [WjcController::class, 'emergencyStop']); // 4.7 全局急停
        Route::put('/stop-recover/{id}', [WjcController::class, 'stopRecover']); // 4.8 恢复自动
        Route::get('/emergency-stops', [WjcController::class, 'emergencyStops']); // 4.9 急停日志
    });
});