<?php
// 应用服务提供器
namespace App\Providers;

use App\Filesystems\OssAdapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use OSS\OssClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 全局时间序列化为北京时间 Y-m-d H:i:s
        Carbon::serializeUsing(fn ($carbon) => $carbon->format('Y-m-d H:i:s'));

        // 注册 OSS 驱动（兼容 Flysystem 3.x）
        Storage::extend('oss', function ($app, $config) {
            $client = new OssClient(
                $config['access_id'] ?? '',
                $config['access_key'] ?? '',
                $config['endpoint'] ?? '',
            );

            $adapter = new OssAdapter($client, $config['bucket'] ?? '', $config['endpoint'] ?? '');

            $driver = new Filesystem($adapter, $config);

            return new FilesystemAdapter($driver, $adapter, [
                ...$config,
                'prefix' => null,
            ]);
        });
    }
}
