<?php
// 天气服务配置
return [

    'default' => env('WEATHER_DRIVER', 'openmeteo'),

    'fallback_enabled' => env('WEATHER_FALLBACK_ENABLED', false),

    'cache_ttl' => env('WEATHER_CACHE_TTL', 300),

    'drivers' => [
        'openmeteo' => [
            'base_url'      => 'https://api.open-meteo.com/v1',
            'archive_url'   => 'https://archive-api.open-meteo.com/v1',
            'geocoding_url' => 'https://geocoding-api.open-meteo.com/v1',
            'timeout'       => 10,
            'retry_times'   => 2,
            'retry_sleep'   => 1000,
        ],
    ],

    'station' => [
        'latitude'  => env('STATION_LATITUDE', 30.0),// 水电站纬度
        'longitude' => env('STATION_LONGITUDE', 105.0),// 水电站经度
        'name'      => env('STATION_NAME', '默认水电站'),// 水电站名称
    ],
];
