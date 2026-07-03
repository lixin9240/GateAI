<?php
// 正式告警表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 正式告警表
     */
    public function up(): void
    {
        Schema::create('alarms', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('alarm_no', 50)->unique()->comment('告警编号');
            $table->unsignedBigInteger('reservoir_id')->index()->comment('所属水库');
            $table->foreign('reservoir_id')->references('id')->on('reservoirs')->onDelete('restrict');
            $table->index(['reservoir_id', 'status', 'created_at'], 'idx_alarms_reservoir_status_created');
            $table->unsignedBigInteger('equipment_id')->nullable()->index()->comment('关联设备');
            $table->foreign('equipment_id')->references('id')->on('equipment')->onDelete('set null');
            $table->index(['equipment_id', 'status'], 'idx_alarms_equipment_status');
            $table->string('type', 50)->index()->comment('告警类型：water_level / flow / gate / power / equipment / physics_violation / comm_loss');
            $table->string('level', 20)->index()->comment('紧急程度：urgent / important / normal');
            $table->index(['level', 'status', 'created_at'], 'idx_alarms_level_status_created');
            $table->string('message', 500)->comment('告警描述');
            $table->unsignedBigInteger('threshold_id')->nullable()->comment('触发阈值规则ID');
            $table->foreign('threshold_id')->references('id')->on('settings_thresholds')->onDelete('set null');
            $table->decimal('metric_value', 15, 4)->comment('触发时实际值');
            $table->decimal('threshold_value', 15, 4)->comment('触发阈值');
            $table->unsignedInteger('duration')->default(0)->comment('持续时长（秒）');
            $table->timestamp('exceed_start')->nullable()->comment('开始超限时间');
            $table->string('status', 20)->default('unhandled')->index()->comment('unhandled / acknowledged / disposed');
            $table->timestamp('acknowledged_at')->nullable()->comment('确认时间');
            $table->unsignedBigInteger('acknowledged_by')->nullable()->comment('确认人');
            $table->foreign('acknowledged_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('disposed_at')->nullable()->comment('处置完成时间');
            $table->unsignedBigInteger('disposed_by')->nullable()->comment('处置人');
            $table->foreign('disposed_by')->references('id')->on('users')->onDelete('set null');
            $table->string('dispose_note', 500)->nullable()->comment('处置备注');
            $table->timestamp('resolved_at')->nullable()->comment('恢复时间');
            $table->unsignedBigInteger('resolved_by')->nullable()->comment('恢复确认人');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            $table->string('trace_id', 50)->nullable()->index()->comment('全链路追踪ID');
            $table->unsignedBigInteger('edge_node_id')->nullable()->comment('来源边缘节点');
            $table->foreign('edge_node_id')->references('id')->on('edge_nodes')->onDelete('cascade');
            $table->timestamp('created_at')->nullable()->index()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarms');
    }
};
