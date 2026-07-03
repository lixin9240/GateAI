<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WeatherController;
use Illuminate\Support\Facades\Route;

// 认证路由（公开）
Route::post('login', [AuthController::class, 'login']);

// 需要认证的路由
Route::middleware('auth:api')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::prefix('weather')->group(function () {
        Route::get('current', [WeatherController::class, 'current']);// 当前天气
        Route::get('hourly', [WeatherController::class, 'hourly']);// 小时天气
        Route::get('daily', [WeatherController::class, 'daily']);// 日天气
        Route::get('snapshot', [WeatherController::class, 'snapshot']);// 快照天气
    });
});
