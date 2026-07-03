<?php
// 天气控制器
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Weather\WeatherCurrentRequest;
use App\Http\Requests\Weather\WeatherDailyRequest;
use App\Http\Requests\Weather\WeatherHourlyRequest;
use App\Services\Weather\WeatherService;
use App\Support\Result;
use Illuminate\Http\JsonResponse;

class WeatherController extends Controller
{
    public function __construct(
        protected WeatherService $weatherService
    ) {}

    public function current(WeatherCurrentRequest $request): JsonResponse
    {
        $lat = $request->input('latitude', config('weather.station.latitude'));
        $lon = $request->input('longitude', config('weather.station.longitude'));

        $data = $this->weatherService->getCurrentWeather((float) $lat, (float) $lon);

        return Result::success('获取实时天气成功', $data);
    }

    public function hourly(WeatherHourlyRequest $request): JsonResponse
    {
        $lat   = $request->input('latitude', config('weather.station.latitude'));
        $lon   = $request->input('longitude', config('weather.station.longitude'));
        $hours = (int) $request->input('hours', 24);

        $data = $this->weatherService->getHourlyForecast((float) $lat, (float) $lon, $hours);

        return Result::success('获取逐时预报成功', [
            'list'  => $data,
            'count' => count($data),
        ]);
    }

    public function daily(WeatherDailyRequest $request): JsonResponse
    {
        $lat  = $request->input('latitude', config('weather.station.latitude'));
        $lon  = $request->input('longitude', config('weather.station.longitude'));
        $days = (int) $request->input('days', 7);

        $data = $this->weatherService->getDailyForecast((float) $lat, (float) $lon, $days);

        return Result::success('获取逐日预报成功', [
            'list'  => $data,
            'count' => count($data),
        ]);
    }

    public function snapshot(WeatherCurrentRequest $request): JsonResponse
    {
        $lat = $request->input('latitude', config('weather.station.latitude'));
        $lon = $request->input('longitude', config('weather.station.longitude'));

        $current = $this->weatherService->getCurrentWeather((float) $lat, (float) $lon);
        $daily   = $this->weatherService->getDailyForecast((float) $lat, (float) $lon, 3);

        return Result::success('获取气象快照成功', [
            'current' => $current,
            'daily'   => $daily,
        ]);
    }
}
