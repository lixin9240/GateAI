<?php
// 应用服务提供器
namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

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
    }
}
