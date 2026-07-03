<?php
// 天气服务
namespace App\Services\Weather;

use App\Services\Weather\Drivers\OpenMeteoDriver;
use App\Services\Weather\Drivers\WeatherDriverInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    protected WeatherDriverInterface $primaryDriver;

    public function __construct()
    {
        $this->primaryDriver = new OpenMeteoDriver();
    }

    public function getCurrentWeather(?float $lat = null, ?float $lon = null): array
    {
        $lat = $lat ?? config('weather.station.latitude');
        $lon = $lon ?? config('weather.station.longitude');
        $ttl = config('weather.cache_ttl', 300);

        $cacheKey = "weather:current:{$lat}:{$lon}";

        return Cache::remember($cacheKey, $ttl, function () use ($lat, $lon) {
            Log::channel('business')->info('[Weather] 获取实时天气', [
                'lat' => $lat,
                'lon' => $lon,
            ]);

            $result = $this->primaryDriver->getCurrentWeather($lat, $lon);

            Log::channel('business')->info('[Weather] 实时天气获取成功', [
                'source' => $result['source'],
            ]);

            return $result;
        });
    }

    public function getHourlyForecast(?float $lat = null, ?float $lon = null, int $hours = 24): array
    {
        $lat = $lat ?? config('weather.station.latitude');
        $lon = $lon ?? config('weather.station.longitude');
        $ttl = config('weather.cache_ttl', 300);

        $cacheKey = "weather:hourly:{$lat}:{$lon}:{$hours}";

        return Cache::remember($cacheKey, $ttl, function () use ($lat, $lon, $hours) {
            Log::channel('business')->info('[Weather] 获取逐时预报', [
                'lat'   => $lat,
                'lon'   => $lon,
                'hours' => $hours,
            ]);

            $result = $this->primaryDriver->getHourlyForecast($lat, $lon, $hours);

            Log::channel('business')->info('[Weather] 逐时预报获取成功', [
                'count' => count($result),
            ]);

            return $result;
        });
    }

    public function getDailyForecast(?float $lat = null, ?float $lon = null, int $days = 7): array
    {
        $lat = $lat ?? config('weather.station.latitude');
        $lon = $lon ?? config('weather.station.longitude');
        $ttl = config('weather.cache_ttl', 300);

        $cacheKey = "weather:daily:{$lat}:{$lon}:{$days}";

        return Cache::remember($cacheKey, $ttl, function () use ($lat, $lon, $days) {
            Log::channel('business')->info('[Weather] 获取逐日预报', [
                'lat'  => $lat,
                'lon'  => $lon,
                'days' => $days,
            ]);

            $result = $this->primaryDriver->getDailyForecast($lat, $lon, $days);

            Log::channel('business')->info('[Weather] 逐日预报获取成功', [
                'count' => count($result),
            ]);

            return $result;
        });
    }
}
