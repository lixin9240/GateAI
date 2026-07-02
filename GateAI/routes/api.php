<?php

use App\Http\Controllers\Api\WeatherController;
use Illuminate\Support\Facades\Route;

Route::prefix('weather')->group(function () {
    Route::get('current', [WeatherController::class, 'current']);// 当前天气
    Route::get('hourly', [WeatherController::class, 'hourly']);// 小时天气
    Route::get('daily', [WeatherController::class, 'daily']);// 日天气
    Route::get('snapshot', [WeatherController::class, 'snapshot']);// 快照天气
});
