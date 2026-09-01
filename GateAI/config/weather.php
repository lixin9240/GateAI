<?php
// 天气服务配置
return [

    'cache_ttl' => env('WEATHER_CACHE_TTL', 300),

    'drivers' => [
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
