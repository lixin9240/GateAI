<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // monitoring_data 按月 RANGE 分区，以 timestamp 字段为分区键
    // 主键调整为 (id, timestamp) 复合主键以支持分区
    public function up(): void
    {
        // MySQL 分区表不支持外键，先删除
        $fks = DB::select("
            SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'monitoring_data'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE monitoring_data DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }

        // 主键改为复合主键以支持分区（原子操作）
        DB::statement('ALTER TABLE monitoring_data DROP PRIMARY KEY, ADD PRIMARY KEY (id, `timestamp`)');

        $partitions = $this->buildMonthlyPartitions();
        DB::statement("ALTER TABLE monitoring_data PARTITION BY RANGE (UNIX_TIMESTAMP(`timestamp`)) ({$partitions})");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE monitoring_data REMOVE PARTITIONING');
        DB::statement('ALTER TABLE monitoring_data DROP PRIMARY KEY, ADD PRIMARY KEY (id)');

        // 恢复外键
        DB::statement('ALTER TABLE monitoring_data ADD CONSTRAINT monitoring_data_reservoir_id_foreign FOREIGN KEY (reservoir_id) REFERENCES reservoirs(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE monitoring_data ADD CONSTRAINT monitoring_data_edge_node_id_foreign FOREIGN KEY (edge_node_id) REFERENCES edge_nodes(id) ON DELETE CASCADE');
    }

    private function buildMonthlyPartitions(): string
    {
        $parts = [];
        $start = now()->subMonths(3)->startOfMonth();

        for ($i = 0; $i < 24; $i++) {
            $month = $start->copy()->addMonths($i);
            $name  = 'p' . $month->format('Ym');
            $to    = $month->copy()->addMonth()->startOfMonth();
            $parts[] = "PARTITION {$name} VALUES LESS THAN (UNIX_TIMESTAMP('{$to->toDateString()}'))";
        }

        $parts[] = 'PARTITION p_future VALUES LESS THAN MAXVALUE';

        return implode(', ', $parts);
    }
};
