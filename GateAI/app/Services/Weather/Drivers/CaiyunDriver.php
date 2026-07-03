<?php

namespace App\Services\Weather\Drivers;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CaiyunDriver implements WeatherDriverInterface
{
    protected string $baseUrl;
    protected string $token;
    protected int $timeout;

    public function __construct()
    {
        $config        = config('weather.drivers.caiyun');
        $this->baseUrl = $config['base_url'];
        $this->token   = $config['token'];
        $this->timeout = $config['timeout'] ?? 10;
    }

    protected function request(float $lat, float $lon): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/{$this->token}/{$lon},{$lat}/weather.json");

            $json = $response->json();

            if (($json['status'] ?? '') !== 'ok') {
                $error = $json['error'] ?? 'unknown';
                $msg   = match ($error) {
                    'invalid_token'        => '彩云天气 Token 无效',
                    'permission denied'    => '彩云天气无权限，需在控制台开通免费订阅',
                    'quota exceeded'       => '彩云天气调用次数已用完',
                    'invalid_coord'        => '彩云天气坐标格式错误',
                    'internal server error'=> '彩云天气服务器异常',
                    default                => "彩云天气异常：{$error}",
                };

                throw new BusinessException($msg, ResponseCode::THIRD_PARTY_ERROR);
            }

            return $json['result'] ?? [];
        } catch (ConnectionException) {
            throw new BusinessException('彩云天气服务不可达', ResponseCode::THIRD_PARTY_ERROR);
        }
    }

    public function getDriverName(): string
    {
        return 'caiyun';
    }

    public function getCurrentWeather(float $lat, float $lon): array
    {
        $data     = $this->request($lat, $lon);
        $realtime = $data['realtime'] ?? [];

        return [
            'temperature'      => (float) ($realtime['temperature'] ?? 0),
            'humidity'         => (float) ($realtime['humidity'] ?? 0),
            'weather_code'     => $realtime['skycon'] ?? '',
            'wind_speed'       => (float) ($realtime['wind']['speed'] ?? 0),
            'wind_direction'   => (float) ($realtime['wind']['direction'] ?? 0),
            'surface_pressure' => (float) ($realtime['pressure'] ?? 0),
            'precipitation'    => (float) ($realtime['precipitation']['local']['intensity'] ?? 0),
            'observed_at'      => now()->toISOString(),
            'source'           => 'caiyun',
        ];
    }

    public function getHourlyForecast(float $lat, float $lon, int $hours = 24): array
    {
        $data   = $this->request($lat, $lon);
        $hourly = $data['hourly']['precipitation'] ?? $data['hourly']['temperature'] ?? [];
        $temps  = $data['hourly']['temperature'] ?? [];
        $humids = $data['hourly']['humidity'] ?? [];
        $winds  = $data['hourly']['wind'] ?? [];
        $precip = $data['hourly']['precipitation'] ?? [];
        $skycon = $data['hourly']['skycon'] ?? [];
        $pres   = $data['hourly']['pressure'] ?? [];

        $result = [];
        $count  = min($hours, count($temps));

        for ($i = 0; $i < $count; $i++) {
            $result[] = [
                'forecast_time'             => $temps[$i]['datetime'] ?? null,
                'temperature'               => (float) ($temps[$i]['value'] ?? 0),
                'precipitation'             => (float) ($precip[$i]['value'] ?? 0),
                'precipitation_probability' => 0,
                'humidity'                  => (float) ($humids[$i]['value'] ?? 0),
                'wind_speed'                => (float) ($winds[$i]['speed'] ?? $winds[$i]['avg']['speed'] ?? 0),
                'weather_code'              => $skycon[$i]['value'] ?? '',
                'surface_pressure'          => (float) ($pres[$i]['value'] ?? 0),
                'source'                    => 'caiyun',
            ];
        }

        return $result;
    }

    public function getDailyForecast(float $lat, float $lon, int $days = 7): array
    {
        $data  = $this->request($lat, $lon);
        $daily = $data['daily']['temperature'] ?? [];
        $sky   = $data['daily']['skycon'] ?? [];
        $precip = $data['daily']['precipitation'] ?? [];
        $wind  = $data['daily']['wind'] ?? [];

        $result = [];
        $count  = min($days, count($daily));

        for ($i = 0; $i < $count; $i++) {
            $result[] = [
                'forecast_date'             => $daily[$i]['date'] ?? null,
                'weather_code'              => $sky[$i]['value'] ?? '',
                'temperature_max'           => (float) ($daily[$i]['max'] ?? 0),
                'temperature_min'           => (float) ($daily[$i]['min'] ?? 0),
                'precipitation_sum'         => (float) ($precip[$i]['max'] ?? 0),
                'precipitation_probability' => 0,
                'wind_speed_max'            => (float) ($wind[$i]['avg']['speed'] ?? 0),
                'source'                    => 'caiyun',
            ];
        }

        return $result;
    }
}
