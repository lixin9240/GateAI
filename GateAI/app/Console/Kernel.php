<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('partition:maintain')->monthlyOn(1, '03:00');

        // 每小时：刷新模型健康缓存
        $schedule->command('model:health-cache')->hourly();

        // 每天凌晨：清理过期日志
        $schedule->command('model:prune-logs')->dailyAt('04:00');

        // 每分钟：检查超时未响应的控制指令
        $schedule->command('dispatch:check-timeout')->everyMinute();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
