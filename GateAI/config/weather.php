<?php
// 天气服务配置
return [

    'default' => env('WEATHER_DRIVER', 'caiyun'),

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

        'hefeng' => [
            'base_url' => 'https://devapi.qweather.com/v7',
            'api_key'  => env('HEFENG_API_KEY'),
            'timeout'  => 10,
        ],

        'caiyun' => [
            'base_url' => 'https://api.caiyunapp.com/v2.6',
            'token'    => env('CAIYUN_TOKEN'),
            'timeout'  => 10,
        ],
    ],

    'station' => [
        'latitude'  => env('STATION_LATITUDE', 28.64),// 向家坝水电站纬度
        'longitude' => env('STATION_LONGITUDE', 104.40),// 向家坝水电站经度
        'name'      => env('STATION_NAME', '四川省宜宾市向家坝水电站'),// 水电站名称
    ],
];
