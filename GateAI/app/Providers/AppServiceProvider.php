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
            $client->setUseSSL(true);

            $adapter = new OssAdapter($client, $config['bucket'] ?? '', $config['endpoint'] ?? '');

            // 注册到容器，供 User 模型等需要签名URL的场景使用
            $app->instance(OssAdapter::class, $adapter);

            $driver = new Filesystem($adapter, $config);

            return new FilesystemAdapter($driver, $adapter, [
                ...$config,
                'prefix' => null,
            ]);
        });

        // 强制解析 OSS 磁盘，确保 OssAdapter 单例在任意请求中可用
        Storage::disk('oss');
    }
}
