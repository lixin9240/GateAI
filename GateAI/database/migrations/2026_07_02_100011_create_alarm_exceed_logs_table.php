<?php
// 超限日志表（瞬时记录）
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 超限日志表（瞬时记录） —— 告警链路：瞬时超限 → 持续≥防抖时间 → 升级为正式告警
     */
    public function up(): void
    {
        Schema::create('alarm_exceed_logs', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('equipment_id')->index()->comment('关联设备');
            $table->foreign('equipment_id')->references('id')->on('equipment')->onDelete('cascade');
            $table->string('metric', 50)->index()->comment('监控指标');
            $table->index(['equipment_id', 'metric', 'exceed_start'], 'idx_exceed_equip_metric_start');
            $table->decimal('metric_value', 15, 4)->comment('触发时实际值');
            $table->decimal('threshold_value', 15, 4)->comment('触发阈值');
            $table->string('threshold_type', 20)->comment('warning / critical');
            $table->string('direction', 10)->comment('upper / lower');
            $table->timestamp('exceed_start')->index()->comment('开始超限时间');
            $table->timestamp('exceed_end')->nullable()->comment('结束超限时间');
            $table->unsignedInteger('duration')->default(0)->comment('持续时长（秒）');
            $table->tinyInteger('is_promoted')->default(0)->index()->comment('是否已升级为正式告警');
            $table->unsignedBigInteger('promoted_alarm_id')->nullable()->comment('关联正式告警ID');
            $table->foreign('promoted_alarm_id')->references('id')->on('alarms')->onDelete('set null');
            $table->unsignedBigInteger('edge_node_id')->nullable()->comment('来源边缘节点');
            $table->foreign('edge_node_id')->references('id')->on('edge_nodes')->onDelete('cascade');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarm_exceed_logs');
    }
};
