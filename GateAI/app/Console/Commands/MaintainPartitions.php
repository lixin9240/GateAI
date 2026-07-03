<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MaintainPartitions extends Command
{
    protected $signature = 'partition:maintain';
    protected $description = '自动创建下月/下季度分区，清理过期分区';

    public function handle(): int
    {
        $this->maintainMonthly('monitoring_data', 24);
        $this->maintainQuarterly('api_logs', 12);

        $this->info('分区维护完成');
        return 0;
    }

    private function maintainMonthly(string $table, int $keepCount): void
    {
        $nextMonth = now()->addMonth()->startOfMonth();
        $name      = 'p' . $nextMonth->format('Ym');
        $to        = $nextMonth->copy()->addMonth()->startOfMonth();

        $exists = DB::selectOne(
            "SELECT 1 FROM information_schema.partitions
             WHERE table_schema = DATABASE() AND table_name = ? AND partition_name = ?",
            [$table, $name]
        );

        if (! $exists) {
            DB::statement("ALTER TABLE {$table} REORGANIZE PARTITION p_future INTO (
                PARTITION {$name} VALUES LESS THAN (UNIX_TIMESTAMP('{$to->toDateString()}')),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )");

            $this->info("[{$table}] 创建分区: {$name}");
        }

        // 清理过期分区
        $cutoff = now()->subMonths($keepCount)->startOfMonth()->format('Ym');
        $rows   = DB::select(
            "SELECT partition_name FROM information_schema.partitions
             WHERE table_schema = DATABASE() AND table_name = ?
               AND partition_name REGEXP '^p[0-9]{6}$'
               AND partition_name < ?",
            [$table, "p{$cutoff}"]
        );

        foreach ($rows as $row) {
            DB::statement("ALTER TABLE {$table} DROP PARTITION {$row->partition_name}");
            $this->info("[{$table}] 删除过期分区: {$row->partition_name}");
        }
    }

    private function maintainQuarterly(string $table, int $keepCount): void
    {
        $nextQuarter = $this->nextQuarter();
        $name = "p{$nextQuarter['year']}q{$nextQuarter['quarter']}";
        $to   = $this->quarterEnd($nextQuarter['year'], $nextQuarter['quarter'])->addDay();

        $exists = DB::selectOne(
            "SELECT 1 FROM information_schema.partitions
             WHERE table_schema = DATABASE() AND table_name = ? AND partition_name = ?",
            [$table, $name]
        );

        if (! $exists) {
            DB::statement("ALTER TABLE {$table} REORGANIZE PARTITION p_future INTO (
                PARTITION {$name} VALUES LESS THAN (UNIX_TIMESTAMP('{$to->toDateString()}')),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )");

            $this->info("[{$table}] 创建分区: {$name}");
        }

        // 清理过期分区
        $all = DB::select(
            "SELECT partition_name FROM information_schema.partitions
             WHERE table_schema = DATABASE() AND table_name = ?
               AND partition_name REGEXP '^p[0-9]{4}q[1-4]$'",
            [$table]
        );

        $names = array_column($all, 'partition_name');
        rsort($names);
        $toDrop = array_slice($names, $keepCount);

        foreach ($toDrop as $name) {
            DB::statement("ALTER TABLE {$table} DROP PARTITION {$name}");
            $this->info("[{$table}] 删除过期分区: {$name}");
        }
    }

    private function nextQuarter(): array
    {
        $month   = (int) now()->addMonth()->format('n');
        $quarter = (int) ceil($month / 3);
        return ['year' => (int) now()->addMonth()->format('Y'), 'quarter' => $quarter];
    }

    private function quarterEnd(int $year, int $quarter): \Illuminate\Support\Carbon
    {
        $month = ($quarter - 1) * 3 + 3;
        return \Illuminate\Support\Carbon::create($year, $month, 1)->endOfMonth();
    }
}
