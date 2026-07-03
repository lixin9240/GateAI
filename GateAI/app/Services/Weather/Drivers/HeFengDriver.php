<?php

namespace App\Services\Weather\Drivers;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Support\LogHelper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class HeFengDriver implements WeatherDriverInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $config        = config('weather.drivers.hefeng');
        $this->baseUrl = $config['base_url'];
        $this->apiKey  = $config['api_key'];
        $this->timeout = $config['timeout'] ?? 10;
    }

    protected function request(string $path, float $lat, float $lon, string $label): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/{$path}", [
                    'location' => "{$lon},{$lat}",
                    'key'      => $this->apiKey,
                ]);

            $json  = $response->json();
            $code  = $json['code'] ?? 'unknown';
            $raw   = $response->body();

            if ($code !== '200') {
                $msg = match ($code) {
                    '401' => '和风天气 API Key 无效',
                    '402' => '和风天气调用次数已用完',
                    '403' => '和风天气无权限（需在控制台开通免费订阅）',
                    '404' => '和风天气接口路径不存在',
                    'unknown' => '和风天气返回异常：' . mb_substr($raw, 0, 200),
                    default => "和风天气服务异常（code={$code}）",
                };

                LogHelper::error("[HeFeng] {$label} — {$msg}", [
                    'hefeng_code' => $code,
                    'raw'         => $raw,
                ]);

                throw new BusinessException($msg, ResponseCode::THIRD_PARTY_ERROR);
            }

            return $json;
        } catch (ConnectionException) {
            throw new BusinessException(
                '和风天气服务不可达',
                ResponseCode::THIRD_PARTY_ERROR
            );
        }
    }

    public function getDriverName(): string
    {
        return 'hefeng';
    }

    public function getCurrentWeather(float $lat, float $lon): array
    {
        $data = $this->request('weather/now', $lat, $lon, '实时天气');
        $now  = $data['now'] ?? [];

        return [
            'temperature'      => (float) ($now['temp'] ?? 0),
            'humidity'         => (float) ($now['humidity'] ?? 0),
            'weather_code'     => $now['icon'] ?? '',
            'wind_speed'       => (float) ($now['windSpeed'] ?? 0),
            'wind_direction'   => (float) ($now['wind360'] ?? 0),
            'surface_pressure' => (float) ($now['pressure'] ?? 0),
            'precipitation'    => (float) ($now['precip'] ?? 0),
            'observed_at'      => $now['obsTime'] ?? now()->toISOString(),
            'source'           => 'hefeng',
        ];
    }

    public function getHourlyForecast(float $lat, float $lon, int $hours = 24): array
    {
        $data   = $this->request('weather/24h', $lat, $lon, '逐时预报');
        $hourly = $data['hourly'] ?? [];
        $result = [];
        $count  = min($hours, count($hourly));

        for ($i = 0; $i < $count; $i++) {
            $item = $hourly[$i];

            $result[] = [
                'forecast_time'             => $item['fxTime'] ?? null,
                'temperature'               => (float) ($item['temp'] ?? 0),
                'precipitation'             => (float) ($item['precip'] ?? 0),
                'precipitation_probability' => (float) ($item['pop'] ?? 0),
                'humidity'                  => (float) ($item['humidity'] ?? 0),
                'wind_speed'                => (float) ($item['windSpeed'] ?? 0),
                'weather_code'              => $item['icon'] ?? '',
                'surface_pressure'          => (float) ($item['pressure'] ?? 0),
                'source'                    => 'hefeng',
            ];
        }

        return $result;
    }

    public function getDailyForecast(float $lat, float $lon, int $days = 7): array
    {
        $data  = $this->request('weather/7d', $lat, $lon, '逐日预报');
        $daily = $data['daily'] ?? [];
        $result = [];
        $count = min($days, count($daily));

        for ($i = 0; $i < $count; $i++) {
            $item = $daily[$i];

            $result[] = [
                'forecast_date'             => $item['fxDate'] ?? null,
                'weather_code'              => $item['iconDay'] ?? '',
                'temperature_max'           => (float) ($item['tempMax'] ?? 0),
                'temperature_min'           => (float) ($item['tempMin'] ?? 0),
                'precipitation_sum'         => (float) ($item['precip'] ?? 0),
                'precipitation_probability' => (float) ($item['pop'] ?? 0),
                'wind_speed_max'            => (float) ($item['windSpeedDay'] ?? 0),
                'source'                    => 'hefeng',
            ];
        }

        return $result;
    }
}
