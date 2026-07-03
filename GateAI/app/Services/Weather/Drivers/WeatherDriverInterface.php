<?php
// 天气驱动接口
namespace App\Services\Weather\Drivers;

interface WeatherDriverInterface
{
    public function getCurrentWeather(float $lat, float $lon): array;

    public function getHourlyForecast(float $lat, float $lon, int $hours = 24): array;

    public function getDailyForecast(float $lat, float $lon, int $days = 7): array;

    public function getDriverName(): string;
}
