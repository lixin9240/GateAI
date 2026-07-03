<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // api_logs 按季度 RANGE 分区，以 created_at 字段为分区键
    // 主键调整为 (id, created_at) 复合主键以支持分区
    public function up(): void
    {
        // MySQL 分区表不支持外键，先删除
        $fks = DB::select("
            SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'api_logs'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE api_logs DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }

        DB::statement('ALTER TABLE api_logs DROP PRIMARY KEY, ADD PRIMARY KEY (id, `created_at`)');

        $partitions = $this->buildQuarterPartitions();
        DB::statement("ALTER TABLE api_logs PARTITION BY RANGE (UNIX_TIMESTAMP(`created_at`)) ({$partitions})");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE api_logs REMOVE PARTITIONING');
        DB::statement('ALTER TABLE api_logs DROP PRIMARY KEY, ADD PRIMARY KEY (id)');

        DB::statement('ALTER TABLE api_logs ADD CONSTRAINT api_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL');
    }

    private function buildQuarterPartitions(): string
    {
        $parts  = [];
        $year   = (int) now()->format('Y');
        $quarter = (int) ceil(now()->format('n') / 3);

        // 从当前季度往前两个季度开始
        $startYear   = $quarter <= 2 ? $year - 1 : $year;
        $startQuarter = $quarter <= 2 ? $quarter + 2 : $quarter - 2;

        $q = ['year' => $startYear, 'quarter' => $startQuarter];

        for ($i = 0; $i < 12; $i++) {
            $name  = "p{$q['year']}q{$q['quarter']}";
            $qDate = $this->quarterEndDate($q['year'], $q['quarter'])->addDay();

            $parts[] = "PARTITION {$name} VALUES LESS THAN (UNIX_TIMESTAMP('{$qDate->toDateString()}'))";

            $q['quarter']++;
            if ($q['quarter'] > 4) {
                $q['quarter'] = 1;
                $q['year']++;
            }
        }

        $parts[] = 'PARTITION p_future VALUES LESS THAN MAXVALUE';

        return implode(', ', $parts);
    }

    private function quarterEndDate(int $year, int $quarter): \Illuminate\Support\Carbon
    {
        $month = ($quarter - 1) * 3 + 3;
        return \Illuminate\Support\Carbon::create($year, $month, 1)->endOfMonth();
    }
};
