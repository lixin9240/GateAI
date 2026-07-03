# 水电站闸门智能调度系统 — 免费气象 API 接入指南

> **适用版本：** Laravel 10.1+ / PHP 8.1+  
> **测试工具：** Apifox  
> **编制日期：** 2026-07-02  

---

## 目录

1. [为什么需要气象数据](#一为什么需要气象数据)
2. [免费气象 API 对比选型](#二免费气象-api-对比选型)
3. [方案一：Open-Meteo（推荐首选）](#三方案一open-meteo推荐首选)
4. [方案二：和风天气（国内备选）](#四方案二和风天气国内备选)
5. [方案三：OpenWeatherMap（国际备选）](#五方案三openweathermap国际备选)
6. [Laravel 后端架构设计](#六laravel-后端架构设计)
7. [核心代码实现](#七核心代码实现)
8. [数据库表设计](#八数据库表设计)
9. [定时任务配置](#九定时任务配置)
10. [Apifox 接口测试](#十apifox-接口测试)
11. [业务融合：气象数据如何参与调度决策](#十一业务融合气象数据如何参与调度决策)

---

## 一、为什么需要气象数据

水电站调度与气象条件高度相关，接入实时气象数据可以：

| 场景 | 气象数据的作用 |
|------|---------------|
| **洪水预警** | 根据未来降雨量预测入库流量变化，提前泄洪 |
| **发电调度** | 枯水期根据天气预报优化发电计划 |
| **LSTM 预测增强** | 将降雨量作为输入特征，提升水位/流量预测精度 |
| **生态流量** | 根据气温、蒸发量调整生态下泄流量 |
| **告警联动** | 暴雨、强风等极端天气提前触发预警 |

**核心目标：将气象数据作为 LSTM 预测模型的额外输入特征，提升 AI 调度决策的准确性。**

---

## 二、免费气象 API 对比选型

### 2.1 一览表

| 维度 | Open-Meteo | 和风天气 | OpenWeatherMap | 中国气象数据网 |
|------|:---:|:---:|:---:|:---:|
| **免费额度** | 10000次/天 | 1000次/天 | 60次/分钟 | 基础免费 |
| **需要注册** | 不需要 | 需要 | 需要 | 需要实名 |
| **需要 API Key** | 不需要 | 需要 | 需要 | 需要 |
| **国内访问速度** | 一般 | 快 | 一般 | 快 |
| **中文支持** | 无 | 完整 | 部分 | 完整 |
| **数据丰富度** | ★★★★☆ | ★★★★★ | ★★★★☆ | ★★★★★ |
| **降雨预报** | 16天 | 15天 | 8天 | 有 |
| **历史数据** | 1940年起 | 付费 | 40年 | 完整 |
| **空气质量** | 有 | 有 | 有 | 有 |
| **SLA 保障** | 无 | 付费版有 | 付费版有 | 有 |

### 2.2 推荐组合策略

```
┌─────────────────────────────────────────────────────┐
│  主通道：Open-Meteo（免费、无需注册、覆盖全）         │
│  备用通道：和风天气（国内快、中文友好、灾害预警强）    │
│  权威通道：中国气象数据网（政府项目、数据合规）        │
│                                                     │
│  策略：主通道每 5 分钟拉取，备用通道每 30 分钟，       │
│        任一失败自动切换，数据做融合校验               │
└─────────────────────────────────────────────────────┘
```

> **建议：优先接入 Open-Meteo + 和风天气双通道。** Open-Meteo 零门槛上手，和风天气作为国内备选（含灾害预警更全）。

---

## 三、方案一：Open-Meteo（推荐首选）

### 3.1 优势

- **完全不需注册，不需 API Key**，一个 URL 直接返回 JSON
- 免费额度 **10000 次/天**，远超水电站调度需求（每 5 分钟拉一次 = 288 次/天）
- 覆盖全球，中国区域精度 11km 网格，山区水电站够用
- 支持历史数据回溯（1940 年起），可用于偏差校正

### 3.2 核心 API 端点

| 用途 | 端点 | 方法 |
|------|------|:---:|
| 天气预报 | `https://api.open-meteo.com/v1/forecast` | GET |
| 历史天气 | `https://archive-api.open-meteo.com/v1/archive` | GET |
| 地理编码 | `https://geocoding-api.open-meteo.com/v1/search` | GET |
| 空气质量 | `https://air-quality-api.open-meteo.com/v1/air-quality` | GET |

### 3.3 请求示例 — 获取某水电站坐标的 7 天预报

```
GET https://api.open-meteo.com/v1/forecast?latitude=30.0&longitude=105.0&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m&hourly=temperature_2m,precipitation,precipitation_probability&daily=temperature_2m_max,temperature_2m_min,precipitation_sum,weather_code&timezone=Asia/Shanghai&forecast_days=7
```

**参数说明：**

| 参数 | 值 | 说明 |
|------|-----|------|
| `latitude` | 水电站纬度（如 30.0） | WGS84 坐标 |
| `longitude` | 水电站经度（如 105.0） | WGS84 坐标 |
| `current` | 逗号分隔的指标 | 实时天气参数 |
| `hourly` | 逗号分隔的指标 | 逐小时预报参数 |
| `daily` | 逗号分隔的指标 | 逐日预报参数 |
| `timezone` | `Asia/Shanghai` | 中国标准时间 |
| `forecast_days` | `1`~`16` | 预报天数 |

### 3.4 水电站常用气象指标

```php
// 当前天气
'current' => [
    'temperature_2m',        // 2米温度 (°C)
    'relative_humidity_2m',  // 相对湿度 (%)
    'weather_code',          // WMO 天气代码
    'wind_speed_10m',        // 10米风速 (km/h)
    'wind_direction_10m',    // 10米风向 (°)
    'surface_pressure',      // 地表气压 (hPa)
]

// 逐小时预报（LSTM 预测关键输入）
'hourly' => [
    'temperature_2m',
    'precipitation',         // 降水量 (mm)
    'precipitation_probability', // 降水概率 (%)
    'relative_humidity_2m',
    'wind_speed_10m',
]

// 逐日预报
'daily' => [
    'temperature_2m_max',    // 最高温度
    'temperature_2m_min',    // 最低温度
    'precipitation_sum',      // 累计降水量
    'weather_code',
]
```

---

## 四、方案二：和风天气（国内备选）

### 4.1 注册步骤

1. 访问 **https://console.qweather.com** 注册账号
2. 完成邮箱验证
3. 进入控制台 → 创建项目 → 选择**免费订阅**
4. 获取 API Key（格式如 `aff40f07926348b9b06f3229d2b52e6a`）

### 4.2 核心 API 端点

| 用途 | 端点（域名 `devapi.qweather.com`） |
|------|------|
| 城市查询 | `/v2/city/lookup?location=北京&key=YOUR_KEY` |
| 实时天气 | `/v7/weather/now?location=城市ID&key=YOUR_KEY` |
| 7天预报 | `/v7/weather/7d?location=城市ID&key=YOUR_KEY` |
| 24小时逐时 | `/v7/weather/24h?location=城市ID&key=YOUR_KEY` |
| 分钟级降水 | `/v7/minutely/5m?location=坐标&key=YOUR_KEY` |
| 灾害预警 | `/v7/warning/now?location=城市ID&key=YOUR_KEY` |
| 空气质量 | `/v7/air/now?location=城市ID&key=YOUR_KEY` |

### 4.3 获取水电站所在城市 ID

和风使用自有 Location ID。两种方式获取：

**方式一：城市查询 API**
```
GET https://geoapi.qweather.com/v2/city/lookup?location=雅安&key=YOUR_KEY
```
返回 JSON 中包含 `id` 字段（如 `101271701`）

**方式二：下载城市列表**
https://github.com/qwd/LocationList/blob/master/China-City-List-latest.csv

### 4.4 请求示例

```bash
# 实时天气（雅安）
curl "https://devapi.qweather.com/v7/weather/now?location=101271701&key=YOUR_KEY" \
  -H "Accept: application/json"

# 7天预报
curl "https://devapi.qweather.com/v7/weather/7d?location=101271701&key=YOUR_KEY" \
  -H "Accept: application/json"

# 灾害预警（对水电站非常重要）
curl "https://devapi.qweather.com/v7/warning/now?location=101271701&key=YOUR_KEY" \
  -H "Accept: application/json"
```

> **免费额度 1000次/天**，建议缓存在 Redis，每 30 分钟刷新一次即可。

---

## 六、Laravel 后端架构设计

### 6.1 整体调用链路

```
┌────────────────────────────────────────────────────────────┐
│                     Laravel 后端                            │
│                                                            │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐ │
│  │ WeatherService│   │ WeatherCache │    │WeatherController│
│  │  (核心服务)    │   │  (缓存层)     │    │  (API 暴露)    │ │
│  └──────┬───────┘    └──────┬───────┘    └──────┬───────┘ │
│         │                   │                    │          │
│  ┌──────┴───────────────────┴────────────────────┴───────┐ │
│  │                  Weather 统一门面                       │ │
│  │  ① 先查缓存 → ② 缓存命中返回 → ③ 未命中调用 API       │ │
│  │  ④ 主通道失败 → ⑤ 自动切换备用通道 → ⑥ 都失败抛异常    │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                            │
│  ┌───────────────────────────────────────────────────────┐ │
│  │              Laravel Task Scheduling                   │ │
│  │  · 每 5 分钟拉取 Open-Meteo 数据                       │ │
│  │  · 每 30 分钟拉取和风天气数据（备用）                   │ │
│  │  · 每天 0 点归档昨日数据                               │ │
│  └───────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────┘
```

### 6.2 文件结构

```
app/
├── Services/
│   ├── Weather/
│   │   ├── WeatherService.php          # 核心门面，统一入口
│   │   ├── Drivers/
│   │   │   ├── OpenMeteoDriver.php      # Open-Meteo 驱动
│   │   │   ├── QWeatherDriver.php       # 和风天气驱动
│   │   │   └── OpenWeatherMapDriver.php # OpenWeatherMap 驱动
│   │   └── WeatherCache.php             # 缓存管理
│   └── Schedule/
│       └── WeatherDataCollector.php      # 定时采集逻辑
│
├── Http/
│   └── Controllers/
│       └── Api/
│           └── WeatherController.php     # 天气数据 API 暴露给前端
│
├── Models/
│   ├── WeatherCurrent.php               # 实时天气数据模型
│   ├── WeatherHourly.php                # 逐时预报数据模型
│   └── WeatherDaily.php                 # 逐日预报数据模型
│
├── config/
│   └── weather.php                      # 天气服务配置
│
├── database/
│   └── migrations/
│       └── xxxx_create_weather_tables.php
│
└── routes/
    └── api.php                          # 天气 API 路由

```

---

## 七、核心代码实现

### 7.1 配置文件 `config/weather.php`

```php
<?php

return [
    // 默认驱动：openmeteo | qweather | openweathermap
    'default' => env('WEATHER_DRIVER', 'openmeteo'),

    // 主备通道失败自动切换
    'fallback_enabled' => env('WEATHER_FALLBACK_ENABLED', true),

    // 缓存 TTL（秒），免费 API 不建议拉太频繁
    'cache_ttl' => env('WEATHER_CACHE_TTL', 300), // 5分钟

    'drivers' => [
        'openmeteo' => [
            'base_url'       => 'https://api.open-meteo.com/v1',
            'archive_url'    => 'https://archive-api.open-meteo.com/v1',
            'geocoding_url'  => 'https://geocoding-api.open-meteo.com/v1',
            'timeout'        => 10,
            'retry_times'    => 2,
            'retry_sleep'    => 1000, // 毫秒
        ],

        'qweather' => [
            'base_url'  => 'https://devapi.qweather.com/v7',
            'geo_url'   => 'https://geoapi.qweather.com/v2',
            'api_key'   => env('QWEATHER_API_KEY', ''),
            'timeout'   => 10,
        ],

        'openweathermap' => [
            'base_url'  => 'https://api.openweathermap.org/data/2.5',
            'api_key'   => env('OWM_API_KEY', ''),
            'timeout'   => 10,
        ],
    ],

    // 水电站坐标（默认值，可在数据库配置）
    'station' => [
        'latitude'  => env('STATION_LATITUDE', 30.0),
        'longitude' => env('STATION_LONGITUDE', 105.0),
        'name'      => env('STATION_NAME', '默认水电站'),
    ],
];
```

`.env` 新增配置：

```env
# 天气服务配置
WEATHER_DRIVER=openmeteo
WEATHER_FALLBACK_ENABLED=true
WEATHER_CACHE_TTL=300

# 水电站坐标（根据实际位置修改）
STATION_LATITUDE=30.0
STATION_LONGITUDE=105.0
STATION_NAME=雅安水电站

# 和风天气 API Key（注册后填写）
QWEATHER_API_KEY=

# OpenWeatherMap API Key（注册后填写）
OWM_API_KEY=
```

### 7.2 驱动接口 `app/Services/Weather/Drivers/WeatherDriverInterface.php`

```php
<?php

namespace App\Services\Weather\Drivers;

interface WeatherDriverInterface
{
    public function getCurrentWeather(float $lat, float $lon): array;
    public function getHourlyForecast(float $lat, float $lon, int $hours = 24): array;
    public function getDailyForecast(float $lat, float $lon, int $days = 7): array;
    public function getDriverName(): string;
}
```

### 7.3 Open-Meteo 驱动实现

```php
<?php

namespace App\Services\Weather\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenMeteoDriver implements WeatherDriverInterface
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('weather.drivers.openmeteo.base_url');
        $this->timeout = config('weather.drivers.openmeteo.timeout', 10);
    }

    public function getDriverName(): string
    {
        return 'openmeteo';
    }

    public function getCurrentWeather(float $lat, float $lon): array
    {
        $response = Http::timeout($this->timeout)
            ->retry(
                config('weather.drivers.openmeteo.retry_times', 2),
                config('weather.drivers.openmeteo.retry_sleep', 1000)
            )
            ->get("{$this->baseUrl}/forecast", [
                'latitude'  => $lat,
                'longitude' => $lon,
                'current'   => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m,wind_direction_10m,surface_pressure,precipitation',
                'timezone'  => 'Asia/Shanghai',
                'forecast_days' => 1,
            ]);

        if ($response->failed()) {
            Log::error('[OpenMeteo] 实时天气获取失败', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Open-Meteo 实时天气请求失败: ' . $response->status());
        }

        $data = $response->json();

        return [
            'temperature'       => $data['current']['temperature_2m'] ?? null,
            'humidity'          => $data['current']['relative_humidity_2m'] ?? null,
            'weather_code'      => $data['current']['weather_code'] ?? null,
            'wind_speed'        => $data['current']['wind_speed_10m'] ?? null,
            'wind_direction'    => $data['current']['wind_direction_10m'] ?? null,
            'surface_pressure'  => $data['current']['surface_pressure'] ?? null,
            'precipitation'     => $data['current']['precipitation'] ?? null,
            'observed_at'       => $data['current']['time'] ?? now()->toISOString(),
            'source'            => 'openmeteo',
        ];
    }

    public function getHourlyForecast(float $lat, float $lon, int $hours = 24): array
    {
        $forecastDays = (int) ceil($hours / 24);

        $response = Http::timeout($this->timeout)
            ->retry(2, 1000)
            ->get("{$this->baseUrl}/forecast", [
                'latitude'  => $lat,
                'longitude' => $lon,
                'hourly'    => 'temperature_2m,precipitation,precipitation_probability,relative_humidity_2m,wind_speed_10m,weather_code,surface_pressure',
                'timezone'  => 'Asia/Shanghai',
                'forecast_days' => $forecastDays,
            ]);

        if ($response->failed()) {
            Log::error('[OpenMeteo] 逐时预报获取失败', [
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Open-Meteo 逐时预报请求失败');
        }

        $data = $response->json();
        $hourly = $data['hourly'] ?? [];

        $result = [];
        $count  = min($hours, count($hourly['time']));

        for ($i = 0; $i < $count; $i++) {
            $result[] = [
                'forecast_time'             => $hourly['time'][$i],
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
        $response = Http::timeout($this->timeout)
            ->retry(2, 1000)
            ->get("{$this->baseUrl}/forecast", [
                'latitude'  => $lat,
                'longitude' => $lon,
                'daily'     => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max,wind_speed_10m_max',
                'timezone'  => 'Asia/Shanghai',
                'forecast_days' => $days,
            ]);

        if ($response->failed()) {
            Log::error('[OpenMeteo] 逐日预报获取失败');
            throw new \RuntimeException('OpenMeteo 逐日预报请求失败');
        }

        $data   = $response->json();
        $daily  = $data['daily'] ?? [];
        $result = [];

        $count = min($days, count($daily['time']));

        for ($i = 0; $i < $count; $i++) {
            $result[] = [
                'forecast_date'              => $daily['time'][$i],
                'weather_code'               => $daily['weather_code'][$i] ?? null,
                'temperature_max'            => $daily['temperature_2m_max'][$i] ?? null,
                'temperature_min'            => $daily['temperature_2m_min'][$i] ?? null,
                'precipitation_sum'          => $daily['precipitation_sum'][$i] ?? null,
                'precipitation_probability'  => $daily['precipitation_probability_max'][$i] ?? null,
                'wind_speed_max'             => $daily['wind_speed_10m_max'][$i] ?? null,
                'source'                     => 'openmeteo',
            ];
        }

        return $result;
    }
}
```

### 7.4 和风天气驱动（备用通道）

```php
<?php

namespace App\Services\Weather\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QWeatherDriver implements WeatherDriverInterface
{
    protected string $baseUrl;
    protected string $apiKey;
    protected ?string $locationId = null; // 缓存城市 ID

    public function __construct()
    {
        $this->baseUrl = config('weather.drivers.qweather.base_url');
        $this->apiKey  = config('weather.drivers.qweather.api_key');
    }

    public function getDriverName(): string
    {
        return 'qweather';
    }

    /**
     * 按坐标查找城市 Location ID
     */
    protected function getLocationId(float $lat, float $lon): string
    {
        if ($this->locationId) {
            return $this->locationId;
        }

        $geoUrl = config('weather.drivers.qweather.geo_url');
        $location = "{$lon},{$lat}";

        $response = Http::timeout(10)
            ->get("{$geoUrl}/city/lookup", [
                'location' => $location,
                'key'      => $this->apiKey,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $this->locationId = $data['location'][0]['id'] ?? null;
        }

        if (!$this->locationId) {
            throw new \RuntimeException('和风天气：无法根据坐标获取城市 ID');
        }

        return $this->locationId;
    }

    public function getCurrentWeather(float $lat, float $lon): array
    {
        $locId = $this->getLocationId($lat, $lon);

        $response = Http::timeout(10)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("{$this->baseUrl}/weather/now", [
                'location' => $locId,
                'key'      => $this->apiKey,
            ]);

        if ($response->failed()) {
            Log::error('[QWeather] 实时天气获取失败', [
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('和风天气实时天气请求失败');
        }

        $data = $response->json();
        $now  = $data['now'] ?? [];

        return [
            'temperature'      => (float) ($now['temp'] ?? 0),
            'humidity'         => (float) ($now['humidity'] ?? 0),
            'weather_code'     => (int) ($now['icon'] ?? 0),
            'weather_text'     => $now['text'] ?? '',
            'wind_speed'       => (float) ($now['windSpeed'] ?? 0),
            'wind_direction'   => $now['windDir'] ?? '',
            'surface_pressure' => (float) ($now['pressure'] ?? 0),
            'precipitation'    => (float) ($now['precip'] ?? 0),
            'observed_at'      => $data['updateTime'] ?? now()->toISOString(),
            'source'           => 'qweather',
        ];
    }

    public function getHourlyForecast(float $lat, float $lon, int $hours = 24): array
    {
        $locId = $this->getLocationId($lat, $lon);

        $response = Http::timeout(10)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("{$this->baseUrl}/weather/24h", [
                'location' => $locId,
                'key'      => $this->apiKey,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('和风天气逐时预报请求失败');
        }

        $data   = $response->json();
        $hourly = $data['hourly'] ?? [];
        $result = [];

        foreach (array_slice($hourly, 0, $hours) as $h) {
            $result[] = [
                'forecast_time'  => $h['fxTime'] ?? null,
                'temperature'    => (float) ($h['temp'] ?? 0),
                'precipitation'  => (float) ($h['precip'] ?? 0),
                'humidity'       => (float) ($h['humidity'] ?? 0),
                'wind_speed'     => (float) ($h['windSpeed'] ?? 0),
                'weather_code'   => (int) ($h['icon'] ?? 0),
                'weather_text'   => $h['text'] ?? '',
                'source'          => 'qweather',
            ];
        }

        return $result;
    }

    public function getDailyForecast(float $lat, float $lon, int $days = 7): array
    {
        $locId = $this->getLocationId($lat, $lon);

        $response = Http::timeout(10)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("{$this->baseUrl}/weather/7d", [
                'location' => $locId,
                'key'      => $this->apiKey,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('和风天气逐日预报请求失败');
        }

        $data   = $response->json();
        $daily  = $data['daily'] ?? [];
        $result = [];

        foreach (array_slice($daily, 0, $days) as $d) {
            $result[] = [
                'forecast_date'       => $d['fxDate'] ?? null,
                'temperature_max'     => (float) ($d['tempMax'] ?? 0),
                'temperature_min'     => (float) ($d['tempMin'] ?? 0),
                'precipitation_sum'   => (float) ($d['precip'] ?? 0),
                'weather_code'        => (int) ($d['iconDay'] ?? 0),
                'weather_text_day'    => $d['textDay'] ?? '',
                'wind_speed_max'      => (float) ($d['windSpeedDay'] ?? 0),
                'source'              => 'qweather',
            ];
        }

        return $result;
    }

    // ========== 和风独有：灾害预警 ==========

    public function getWeatherWarnings(float $lat, float $lon): array
    {
        $locId = $this->getLocationId($lat, $lon);

        $response = Http::timeout(10)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("{$this->baseUrl}/warning/now", [
                'location' => $locId,
                'key'      => $this->apiKey,
            ]);

        if ($response->failed()) {
            return []; // 预警不是必须的，失败不抛异常
        }

        $data     = $response->json();
        $warnings = $data['warning'] ?? [];

        return array_map(function ($w) {
            return [
                'warning_id'    => $w['id'] ?? null,
                'type'          => $w['typeName'] ?? '',
                'level'         => $w['level'] ?? '',
                'title'         => $w['title'] ?? '',
                'text'          => $w['text'] ?? '',
                'publish_time'  => $w['pubTime'] ?? null,
            ];
        }, $warnings);
    }
}
```

### 7.5 核心门面服务 `app/Services/Weather/WeatherService.php`

```php
<?php

namespace App\Services\Weather;

use App\Services\Weather\Drivers\OpenMeteoDriver;
use App\Services\Weather\Drivers\QWeatherDriver;
use App\Services\Weather\Drivers\WeatherDriverInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    protected WeatherDriverInterface $primaryDriver;
    protected ?WeatherDriverInterface $fallbackDriver = null;

    public function __construct()
    {
        $default = config('weather.default', 'openmeteo');

        $this->primaryDriver = match ($default) {
            'qweather'       => new QWeatherDriver(),
            'openweathermap' => new QWeatherDriver(), // 可替换
            default          => new OpenMeteoDriver(),
        };

        // 备用通道
        if (config('weather.fallback_enabled', true) && $default === 'openmeteo') {
            $this->fallbackDriver = new QWeatherDriver();
        }
    }

    public function getCurrentWeather(float $lat = null, float $lon = null): array
    {
        $lat  = $lat  ?? config('weather.station.latitude');
        $lon  = $lon  ?? config('weather.station.longitude');
        $ttl  = config('weather.cache_ttl', 300);

        $cacheKey = "weather:current:{$lat}:{$lon}";

        return Cache::remember($cacheKey, $ttl, function () use ($lat, $lon) {
            return $this->executeWithFallback('getCurrentWeather', $lat, $lon);
        });
    }

    public function getHourlyForecast(float $lat = null, float $lon = null, int $hours = 24): array
    {
        $lat  = $lat  ?? config('weather.station.latitude');
        $lon  = $lon  ?? config('weather.station.longitude');
        $ttl  = config('weather.cache_ttl', 300);

        $cacheKey = "weather:hourly:{$lat}:{$lon}:{$hours}";

        return Cache::remember($cacheKey, $ttl, function () use ($lat, $lon, $hours) {
            return $this->executeWithFallback('getHourlyForecast', $lat, $lon, $hours);
        });
    }

    public function getDailyForecast(float $lat = null, float $lon = null, int $days = 7): array
    {
        $lat  = $lat  ?? config('weather.station.latitude');
        $lon  = $lon  ?? config('weather.station.longitude');
        $ttl  = config('weather.cache_ttl', 300);

        $cacheKey = "weather:daily:{$lat}:{$lon}:{$days}";

        return Cache::remember($cacheKey, $ttl, function () use ($lat, $lon, $days) {
            return $this->executeWithFallback('getDailyForecast', $lat, $lon, $days);
        });
    }

    /**
     * 主通道 → 失败 → 备用通道 → 都失败抛异常
     */
    protected function executeWithFallback(string $method, ...$args): array
    {
        try {
            return $this->primaryDriver->{$method}(...$args);
        } catch (\Throwable $e) {
            Log::warning("[Weather] 主通道({$this->primaryDriver->getDriverName()})失败，尝试备用通道", [
                'error' => $e->getMessage(),
            ]);

            if ($this->fallbackDriver) {
                try {
                    return $this->fallbackDriver->{$method}(...$args);
                } catch (\Throwable $fb) {
                    Log::error('[Weather] 备用通道也失败', [
                        'error' => $fb->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * 获取灾害预警（仅和风天气支持）
     */
    public function getWeatherWarnings(float $lat = null, float $lon = null): array
    {
        $cacheKey = "weather:warnings:{$lat}:{$lon}";
        $ttl      = 600; // 预警缓存 10 分钟

        return Cache::remember($cacheKey, $ttl, function () use ($lat, $lon) {
            $driver = new QWeatherDriver();
            return $driver->getWeatherWarnings($lat, $lon);
        });
    }
}
```

### 7.6 控制器 `app/Http/Controllers/Api/WeatherController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Weather\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    public function __construct(
        protected WeatherService $weatherService
    ) {}

    /**
     * 获取水电站当前天气
     *
     * GET /api/weather/current
     */
    public function current(Request $request): JsonResponse
    {
        $lat = $request->input('latitude', config('weather.station.latitude'));
        $lon = $request->input('longitude', config('weather.station.longitude'));

        $data = $this->weatherService->getCurrentWeather($lat, $lon);

        return response()->json([
            'code' => 200,
            'data' => $data,
        ]);
    }

    /**
     * 获取逐时天气预报
     *
     * GET /api/weather/hourly?hours=24
     */
    public function hourly(Request $request): JsonResponse
    {
        $request->validate([
            'hours' => 'integer|min:1|max:168',
        ]);

        $lat   = $request->input('latitude', config('weather.station.latitude'));
        $lon   = $request->input('longitude', config('weather.station.longitude'));
        $hours = $request->input('hours', 24);

        $data = $this->weatherService->getHourlyForecast($lat, $lon, $hours);

        return response()->json([
            'code' => 200,
            'data' => $data,
            'meta' => ['count' => count($data)],
        ]);
    }

    /**
     * 获取逐日天气预报
     *
     * GET /api/weather/daily?days=7
     */
    public function daily(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'integer|min:1|max:16',
        ]);

        $lat  = $request->input('latitude', config('weather.station.latitude'));
        $lon  = $request->input('longitude', config('weather.station.longitude'));
        $days = $request->input('days', 7);

        $data = $this->weatherService->getDailyForecast($lat, $lon, $days);

        return response()->json([
            'code' => 200,
            'data' => $data,
            'meta' => ['count' => count($data)],
        ]);
    }

    /**
     * 获取灾害天气预警
     *
     * GET /api/weather/warnings
     */
    public function warnings(Request $request): JsonResponse
    {
        $lat = $request->input('latitude', config('weather.station.latitude'));
        $lon = $request->input('longitude', config('weather.station.longitude'));

        $data = $this->weatherService->getWeatherWarnings($lat, $lon);

        return response()->json([
            'code' => 200,
            'data' => $data,
        ]);
    }

    /**
     * 获取气象综合快照（监控大屏用）
     *
     * GET /api/weather/snapshot
     */
    public function snapshot(Request $request): JsonResponse
    {
        $lat = $request->input('latitude', config('weather.station.latitude'));
        $lon = $request->input('longitude', config('weather.station.longitude'));

        $current  = $this->weatherService->getCurrentWeather($lat, $lon);
        $daily    = $this->weatherService->getDailyForecast($lat, $lon, 3);
        $warnings = $this->weatherService->getWeatherWarnings($lat, $lon);

        return response()->json([
            'code' => 200,
            'data' => [
                'current'  => $current,
                'daily'    => $daily,
                'warnings' => $warnings,
            ],
        ]);
    }
}
```

### 7.7 路由注册 `routes/api.php`

```php
<?php

use App\Http\Controllers\Api\WeatherController;
use Illuminate\Support\Facades\Route;

// 气象数据接口（认证后访问）
Route::middleware('auth:sanctum')->prefix('weather')->group(function () {
    Route::get('current',  [WeatherController::class, 'current']);
    Route::get('hourly',   [WeatherController::class, 'hourly']);
    Route::get('daily',    [WeatherController::class, 'daily']);
    Route::get('warnings', [WeatherController::class, 'warnings']);
    Route::get('snapshot', [WeatherController::class, 'snapshot']);
});
```

---

## 八、数据库表设计

### 8.1 实时天气记录表 `weather_current_logs`

```php
// migration
Schema::create('weather_current_logs', function (Blueprint $table) {
    $table->id();
    $table->decimal('latitude', 9, 6);
    $table->decimal('longitude', 9, 6);
    $table->decimal('temperature', 6, 2)->nullable()->comment('温度 °C');
    $table->decimal('humidity', 6, 2)->nullable()->comment('相对湿度 %');
    $table->integer('weather_code')->nullable()->comment('WMO 天气代码');
    $table->decimal('wind_speed', 6, 2)->nullable()->comment('风速 km/h');
    $table->integer('wind_direction')->nullable()->comment('风向角度');
    $table->decimal('surface_pressure', 8, 2)->nullable()->comment('气压 hPa');
    $table->decimal('precipitation', 6, 2)->nullable()->comment('降水量 mm');
    $table->string('source', 30)->comment('数据来源: openmeteo/qweather');
    $table->timestamp('observed_at')->comment('气象观测时间');
    $table->timestamps();

    $table->index(['latitude', 'longitude', 'observed_at']);
});
```

### 8.2 逐时预报表 `weather_hourly_forecasts`

```php
Schema::create('weather_hourly_forecasts', function (Blueprint $table) {
    $table->id();
    $table->decimal('latitude', 9, 6);
    $table->decimal('longitude', 9, 6);
    $table->timestamp('forecast_time')->comment('预报时间点');
    $table->decimal('temperature', 6, 2)->nullable();
    $table->decimal('precipitation', 6, 2)->nullable()->comment('降水量 mm');
    $table->integer('precipitation_probability')->nullable()->comment('降水概率 %');
    $table->decimal('humidity', 6, 2)->nullable();
    $table->decimal('wind_speed', 6, 2)->nullable();
    $table->string('source', 30);
    $table->timestamps();

    $table->unique(['latitude', 'longitude', 'forecast_time', 'source'],
        'uk_hourly_forecast');
});
```

### 8.3 逐日预报表 `weather_daily_forecasts`

```php
Schema::create('weather_daily_forecasts', function (Blueprint $table) {
    $table->id();
    $table->decimal('latitude', 9, 6);
    $table->decimal('longitude', 9, 6);
    $table->date('forecast_date')->comment('预报日期');
    $table->decimal('temperature_max', 6, 2)->nullable();
    $table->decimal('temperature_min', 6, 2)->nullable();
    $table->decimal('precipitation_sum', 8, 2)->nullable()->comment('全天累计降水 mm');
    $table->integer('precipitation_probability')->nullable();
    $table->decimal('wind_speed_max', 6, 2)->nullable();
    $table->integer('weather_code')->nullable();
    $table->string('source', 30);
    $table->timestamps();

    $table->unique(['latitude', 'longitude', 'forecast_date', 'source'],
        'uk_daily_forecast');
});
```

---

## 九、定时任务配置

### 9.1 调度器 `app/Console/Kernel.php`

```php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // 每 5 分钟拉取 Open-Meteo 天气数据
        $schedule->call(function () {
            app(\App\Services\Schedule\WeatherDataCollector::class)->collectAll();
        })->everyFiveMinutes()
          ->name('weather:collect')
          ->withoutOverlapping(10) // 防止任务堆积
          ->onFailure(function () {
              \Log::error('[定时任务] 天气数据采集失败');
          });

        // 每小时清理 3 天前的逐时预报（已过期）
        $schedule->command('weather:cleanup')->hourly();

        // 每天拉取一次和风天气灾害预警
        $schedule->call(function () {
            $service = app(\App\Services\Weather\WeatherService::class);
            $service->getWeatherWarnings();
        })->hourly()
          ->name('weather:warnings');
    }
}
```

### 9.2 数据采集器 `app/Services/Schedule/WeatherDataCollector.php`

```php
<?php

namespace App\Services\Schedule;

use App\Models\WeatherCurrent;
use App\Models\WeatherHourly;
use App\Models\WeatherDaily;
use App\Services\Weather\WeatherService;
use Illuminate\Support\Facades\Log;

class WeatherDataCollector
{
    protected WeatherService $weather;

    public function __construct()
    {
        $this->weather = app(WeatherService::class);
    }

    public function collectAll(): void
    {
        $lat = config('weather.station.latitude');
        $lon = config('weather.station.longitude');

        Log::info('[WeatherCollector] 开始采集气象数据', compact('lat', 'lon'));

        try {
            // 直接调用服务（绕过缓存获取最新数据）
            $current = $this->weather->getCurrentWeather($lat, $lon);
            WeatherCurrent::create($current);
            Log::info('[WeatherCollector] 实时天气入库成功', [
                'temp'    => $current['temperature'],
                'source'  => $current['source'],
            ]);
        } catch (\Throwable $e) {
            Log::error('[WeatherCollector] 实时天气采集失败: ' . $e->getMessage());
        }

        try {
            $hourly = $this->weather->getHourlyForecast($lat, $lon, 48);
            foreach ($hourly as $h) {
                WeatherHourly::updateOrCreate(
                    [
                        'latitude'       => $lat,
                        'longitude'      => $lon,
                        'forecast_time'  => $h['forecast_time'],
                        'source'         => $h['source'],
                    ],
                    $h
                );
            }
            Log::info('[WeatherCollector] 逐时预报入库成功', ['count' => count($hourly)]);
        } catch (\Throwable $e) {
            Log::error('[WeatherCollector] 逐时预报采集失败: ' . $e->getMessage());
        }

        try {
            $daily = $this->weather->getDailyForecast($lat, $lon, 7);
            foreach ($daily as $d) {
                WeatherDaily::updateOrCreate(
                    [
                        'latitude'       => $lat,
                        'longitude'      => $lon,
                        'forecast_date'  => $d['forecast_date'],
                        'source'         => $d['source'],
                    ],
                    $d
                );
            }
            Log::info('[WeatherCollector] 逐日预报入库成功', ['count' => count($daily)]);
        } catch (\Throwable $e) {
            Log::error('[WeatherCollector] 逐日预报采集失败: ' . $e->getMessage());
        }
    }
}
```

---

## 十、Apifox 接口测试

### 10.1 测试准备

1. 启动 Laravel 开发服务器：
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

2. 打开 **Apifox**，创建新项目 → 设置 Base URL：
```
http://localhost:8000/api
```

3. 在 Apifox 中配置全局 Header（如需要认证 Token）：
```
Authorization: Bearer {你的 Token}
Accept: application/json
```

### 10.2 接口测试用例

#### 测试 1：获取实时天气

```
GET {{baseUrl}}/weather/current?latitude=30.0&longitude=105.0
```

**Apifox 预期响应：**
```json
{
    "code": 200,
    "data": {
        "temperature": 28.5,
        "humidity": 72,
        "weather_code": 1,
        "wind_speed": 12.3,
        "wind_direction": 180,
        "surface_pressure": 1013.2,
        "precipitation": 0.0,
        "observed_at": "2026-07-02T10:00",
        "source": "openmeteo"
    }
}
```

**Apifox 断言（后置脚本）：**
```javascript
// Apifox 断言
pm.test("状态码为 200", () => {
    pm.response.to.have.status(200);
});

pm.test("返回数据包含必要字段", () => {
    const jsonData = pm.response.json();
    pm.expect(jsonData.code).to.eql(200);
    pm.expect(jsonData.data).to.have.property('temperature');
    pm.expect(jsonData.data).to.have.property('humidity');
    pm.expect(jsonData.data).to.have.property('precipitation');
    pm.expect(jsonData.data).to.have.property('source');
});
```

#### 测试 2：获取逐时预报

```
GET {{baseUrl}}/weather/hourly?latitude=30.0&longitude=105.0&hours=24
```

**Apifox 预期响应：**
```json
{
    "code": 200,
    "data": [
        {
            "forecast_time": "2026-07-02T11:00",
            "temperature": 29.0,
            "precipitation": 0.0,
            "precipitation_probability": 5,
            "humidity": 68,
            "wind_speed": 10.5,
            "weather_code": 2,
            "source": "openmeteo"
        }
    ],
    "meta": {"count": 24}
}
```

#### 测试 3：获取逐日预报

```
GET {{baseUrl}}/weather/daily?latitude=30.0&longitude=105.0&days=7
```

#### 测试 4：获取灾害预警

```
GET {{baseUrl}}/weather/warnings?latitude=30.0&longitude=105.0
```

#### 测试 5：获取气象综合快照（大屏用）

```
GET {{baseUrl}}/weather/snapshot?latitude=30.0&longitude=105.0
```

### 10.3 Apifox 环境变量配置

在 Apifox 中创建环境变量，方便切换测试环境：

| 变量名 | 开发环境 | 生产环境 |
|--------|----------|----------|
| `baseUrl` | `http://localhost:8000/api` | `https://你的域名/api` |
| `token` | 开发 Token | 生产 Token |
| `station_lat` | `30.0` | 实际纬度 |
| `station_lon` | `105.0` | 实际经度 |

### 10.4 直接测试 Open-Meteo（不经过后端）

如果想先验证气象 API 本身是否可用，在 Apifox 中直接新建一个接口：

```
GET https://api.open-meteo.com/v1/forecast?latitude=30.0&longitude=105.0&current=temperature_2m,relative_humidity_2m,weather_code,precipitation,wind_speed_10m&timezone=Asia/Shanghai&forecast_days=1
```

无需任何 Header，直接发送即可获得 JSON 响应。这是最快速的验证方式。

---

## 十一、业务融合：气象数据如何参与调度决策

### 11.1 降雨量 → LSTM 预测模型增强

```
原 LSTM 输入特征：
  历史水位(t-1h, t-2h, t-3h...)
  历史流量(t-1h, t-2h, t-3h...)
  闸门开度(t-1h)
  日期特征（月份、小时）

建议增加气象特征 → 提升预测精度：
  + 未来 1h/3h/6h 预报降水量（来自 weather_hourly_forecasts.precipitation）
  + 当前湿度（来自 weather_current_logs.humidity）
  + 降水概率（来自 weather_hourly_forecasts.precipitation_probability）
```

### 11.2 降雨阈值 → 告警联动

在告警系统中增加气象相关的告警规则：

| 告警类型 | 触发条件 | 级别 |
|----------|----------|:---:|
| `HEAVY_RAIN_WARNING` | 未来 3h 降水 > 30mm | 重要 |
| `STORM_WARNING` | 未来 6h 降水 > 50mm | 紧急 |
| `DROUGHT_WARNING` | 连续 7 天无有效降水 | 一般 |
| `EXTREME_WIND` | 风速 > 20m/s | 重要 |
| `WEATHER_DISASTER_WARNING` | 气象局发布灾害预警 | 紧急 |

### 11.3 降水与调度权重自动联动

```
汛期场景（自动检测到连续强降水预报）：
  系统 → 自动推送建议"将调度权重调整为 汛期预设"
  w_power → 0.15
  w_safety → 0.70
  w_eco → 0.15

枯水期场景（检测到长期无降水）：
  系统 → 自动推送建议"将调度权重调整为 枯水期预设"
  w_power → 0.60
  w_safety → 0.25
  w_eco → 0.15
```

### 11.4 前端在大屏中展示气象模块

在监控大屏上增加一个气象数据卡片（DataV 边框风格）：

```
┌──────── 气象实况 ────────┐
│ 温度  28.5°C    ☁️ 多云  │
│ 湿度  72%       ← 南风   │
│ 风速  12.3 km/h  3 级    │
│ 气压  1013.2 hPa         │
│ 降水  0.0 mm（未来 3h）   │
│                          │
│ ⚠️ 暴雨黄色预警（如有）    │
│ → 未来 48h 累计降水 80mm  │
└──────────────────────────┘
```

---

## 附录

### A. 接入时序总结

```
第 1 步（立即）：Open-Meteo 无需注册，直接代码集成，10 分钟跑通
第 2 步（2 小时）：注册和风天气 + 实现双通道容灾
第 3 步（4 小时）：数据库表 + 定时任务 + 接口上线
第 4 步（1 天）：Apifox 全量接口测试
第 5 步（后续迭代）：气象数据喂入 LSTM 模型 + 告警规则联动
```

### B. 免费 API 注意事项

1. **遵守使用条款**：免费 API 不可转售数据
2. **合理调用频率**：做缓存，不要每秒都去拉
3. **标注数据来源**：前端展示时注明"数据来源：Open-Meteo / 和风天气"
4. **数据可靠性**：免费 API 无 SLA 保障，关键时刻可能不可用，双通道容灾是必须的
5. **和风天气需标注**：免费版要求在应用显著位置显示"数据来源：和风天气"

### C. 常见问题

**Q: Open-Meteo 在中国山区水电站精度够用吗？**

A: Open-Meteo 使用 11km 网格分辨率，对山区有一定的平滑效应。建议运行一段时间后，将气象数据与实际水位计/流量计数据做对比校准，建立本地偏差修正模型。

**Q: 和风天气城市 ID 怎么获得？**

A: 下载城市列表 CSV https://github.com/qwd/LocationList/blob/master/China-City-List-latest.csv ，或使用城市查询 API `GET https://geoapi.qweather.com/v2/city/lookup?location=城市名&key=KEY`

**Q: 免费额度不够用怎么办？**

A: Open-Meteo 每天 10000 次足够（每 5 分钟拉 = 288次/天 ≈ 只用了 3%）。和风天气 1000 次/天，如果作为备用通道每 30 分钟拉一次也完全够用。如果真的不够，升级和风天气付费版（约 ¥99/月）。

---

> **文档结束**  
> 下一步：从 Open-Meteo 开始，10 分钟内即可跑通第一个接口！
