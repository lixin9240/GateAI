<?php
// 闸门动作记录表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 闸门动作记录表 —— 记录每次闸门物理动作的完整信息，用于设备寿命分析和机械磨损评估
     */
    public function up(): void
    {
        Schema::create('gate_actions', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('equipment_id')->comment('闸门设备ID');
            $table->foreign('equipment_id')->references('id')->on('equipment')->onDelete('restrict');
            $table->index(['equipment_id', 'acted_at'], 'idx_gate_actions_equip_acted');
            $table->unsignedBigInteger('decision_id')->nullable()->comment('关联调度决策');
            $table->foreign('decision_id')->references('id')->on('dispatch_decisions')->onDelete('set null');
            $table->unsignedBigInteger('command_id')->nullable()->comment('关联控制指令（FK在后续迁移中添加）');
            $table->decimal('previous_opening', 5, 2)->comment('动作前开度（%）');
            $table->decimal('target_opening', 5, 2)->comment('目标开度（%）');
            $table->decimal('actual_opening', 5, 2)->nullable()->comment('实际到位开度（%）');
            $table->string('action_type', 20)->index()->comment('open / close / maintain / emergency');
            $table->string('action_source', 30)->comment('dqn_auto / manual / emergency_override / physics_corrected');
            $table->unsignedInteger('duration_ms')->nullable()->comment('动作耗时（ms）');
            $table->decimal('actuator_current', 5, 2)->nullable()->comment('推杆电流（A），用于磨损分析');
            $table->tinyInteger('is_smoothed')->default(0)->comment('是否被指令平滑化修改');
            $table->string('smooth_reason', 100)->nullable()->comment('平滑化原因');
            $table->timestamp('acted_at')->index()->comment('动作执行时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
        });

        // 后续迁移中通过 control_commands 的创建再补充 command_id 外键
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gate_actions');
    }
};
