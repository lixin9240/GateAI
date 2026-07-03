<?php
// 监测数据表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 监测数据表 —— 全系统写入压力最大的表，必须按月分区
     */
    public function up(): void
    {
        Schema::create('monitoring_data', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->timestamp('timestamp')->index()->comment('数据时间戳');
            $table->unsignedBigInteger('reservoir_id')->index()->comment('所属水库');
            $table->foreign('reservoir_id')->references('id')->on('reservoirs')->onDelete('restrict');
            $table->index(['reservoir_id', 'timestamp'], 'idx_monitoring_reservoir_ts');
            $table->decimal('upstream_level', 10, 3)->comment('上游水位（m）');
            $table->decimal('downstream_level', 10, 3)->comment('下游水位（m）');
            $table->decimal('water_head', 10, 3)->comment('水头（m）=上游−下游');
            $table->decimal('inflow_rate', 10, 3)->comment('入库流量（m³/s）');
            $table->decimal('outflow_rate', 10, 3)->comment('出库流量（m³/s）');
            $table->decimal('gate_opening', 5, 2)->comment('闸门开度（%）');
            $table->decimal('power_output', 10, 3)->comment('发电功率（kW）');
            $table->decimal('cumulative_energy', 15, 3)->default(0)->comment('累计发电量（kWh）');
            $table->unsignedBigInteger('edge_node_id')->index()->comment('来源边缘节点');
            $table->foreign('edge_node_id')->references('id')->on('edge_nodes')->onDelete('cascade');
            $table->index(['edge_node_id', 'timestamp'], 'idx_monitoring_edge_ts');
            $table->string('data_source', 20)->default('sensor')->comment('sensor / simulation / manual');
            $table->tinyInteger('is_anomaly')->default(0)->comment('是否异常值');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitoring_data');
    }
};
