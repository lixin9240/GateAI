<?php
// Open-Meteo 驱动
namespace App\Services\Weather\Drivers;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Support\LogHelper;
use Illuminate\Support\Facades\Http;

class OpenMeteoDriver implements WeatherDriverInterface
{
    protected string $baseUrl;
    protected int $timeout;
    protected int $retryTimes;
    protected int $retrySleep;

    public function __construct()
    {
        $config             = config('weather.drivers.openmeteo');
        $this->baseUrl      = $config['base_url'];
        $this->timeout      = $config['timeout'] ?? 10;
        $this->retryTimes   = $config['retry_times'] ?? 2;
        $this->retrySleep   = $config['retry_sleep'] ?? 1000;
    }

    protected function getClient()
    {
        return Http::timeout($this->timeout)
            ->retry($this->retryTimes, $this->retrySleep)
            ->withOptions(['verify' => storage_path('cacert.pem')]);
    }

    public function getDriverName(): string
    {
        return 'openmeteo';
    }

    public function getCurrentWeather(float $lat, float $lon): array
    {
        $response = $this->getClient()->get("{$this->baseUrl}/forecast", [
            'latitude'      => $lat,
            'longitude'     => $lon,
            'current'       => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m,wind_direction_10m,surface_pressure,precipitation',
            'timezone'      => 'Asia/Shanghai',
            'forecast_days' => 1,
        ]);

        if ($response->failed()) {
            LogHelper::error('[OpenMeteo] 实时天气获取失败', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new BusinessException('Open-Meteo 实时天气请求失败', ResponseCode::THIRD_PARTY_ERROR);
        }

        $data = $response->json();

        $observedAt = $data['current']['time'] ?? now()->toISOString();
        if (!str_contains($observedAt, '+08:00') && !str_contains($observedAt, 'Z')) {
            $observedAt .= '+08:00';
        }

        return [
            'temperature'      => $data['current']['temperature_2m'] ?? null,
            'humidity'         => $data['current']['relative_humidity_2m'] ?? null,
            'weather_code'     => $data['current']['weather_code'] ?? null,
            'wind_speed'       => $data['current']['wind_speed_10m'] ?? null,
            'wind_direction'   => $data['current']['wind_direction_10m'] ?? null,
            'surface_pressure' => $data['current']['surface_pressure'] ?? null,
            'precipitation'    => $data['current']['precipitation'] ?? null,
            'observed_at'      => $observedAt,
            'source'           => 'openmeteo',
        ];
    }

    public function getHourlyForecast(float $lat, float $lon, int $hours = 24): array
    {
        $forecastDays = (int) ceil($hours / 24);

        $response = $this->getClient()->get("{$this->baseUrl}/forecast", [
            'latitude'      => $lat,
            'longitude'     => $lon,
            'hourly'        => 'temperature_2m,precipitation,precipitation_probability,relative_humidity_2m,wind_speed_10m,weather_code,surface_pressure',
            'timezone'      => 'Asia/Shanghai',
            'forecast_days' => $forecastDays,
        ]);

        if ($response->failed()) {
            LogHelper::error('[OpenMeteo] 逐时预报获取失败', [
                'status' => $response->status(),
            ]);
            throw new BusinessException('Open-Meteo 逐时预报请求失败', ResponseCode::THIRD_PARTY_ERROR);
        }

        $data   = $response->json();
        $hourly = $data['hourly'] ?? [];
        $result = [];
        $count  = min($hours, count($hourly['time']));

        for ($i = 0; $i < $count; $i++) {
            $forecastTime = $hourly['time'][$i];
            if (!str_contains($forecastTime, '+08:00') && !str_contains($forecastTime, 'Z')) {
                $forecastTime .= '+08:00';
            }

            $result[] = [
                'forecast_time'             => $forecastTime,
                'temperature'               => $hourly['temperature_2m'][$i] ?? null,
                'precipitation'             => $hourly['precipitation'][$i] ?? null,
                'precipitation_probability' => $hourly['precipitation_probability'][$i] ?? null,
                'humidity'                  => $hourly['relative_humidity_2m'][$i] ?? null,
                'wind_speed'                => $hourly['wind_speed_10m'][$i] ?? null,
                'weather_code'              => $hourly['weather_code'][$i] ?? null,
                'surface_pressure'          => $hourly['surface_pressure'][$i] ?? null,
                'source'                    => 'openmeteo',
            ];
        }

        return $result;
    }

    public function getDailyForecast(float $lat, float $lon, int $days = 7): array
    {
        $response = $this->getClient()->get("{$this->baseUrl}/forecast", [
            'latitude'      => $lat,
            'longitude'     => $lon,
            'daily'         => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max,wind_speed_10m_max',
            'timezone'      => 'Asia/Shanghai',
            'forecast_days' => $days,
        ]);

        if ($response->failed()) {
            LogHelper::error('[OpenMeteo] 逐日预报获取失败');
            throw new BusinessException('Open-Meteo 逐日预报请求失败', ResponseCode::THIRD_PARTY_ERROR);
        }

        $data  = $response->json();
        $daily = $data['daily'] ?? [];
        $result = [];
        $count = min($days, count($daily['time']));

        for ($i = 0; $i < $count; $i++) {
            $forecastDate = $daily['time'][$i];
            if (!str_contains($forecastDate, '+08:00') && !str_contains($forecastDate, 'Z')) {
                $forecastDate .= '+08:00';
            }

            $result[] = [
                'forecast_date'             => $forecastDate,
                'weather_code'              => $daily['weather_code'][$i] ?? null,
                'temperature_max'           => $daily['temperature_2m_max'][$i] ?? null,
                'temperature_min'           => $daily['temperature_2m_min'][$i] ?? null,
                'precipitation_sum'         => $daily['precipitation_sum'][$i] ?? null,
                'precipitation_probability' => $daily['precipitation_probability_max'][$i] ?? null,
                'wind_speed_max'            => $daily['wind_speed_10m_max'][$i] ?? null,
                'source'                    => 'openmeteo',
            ];
        }

        return $result;
    }
}
