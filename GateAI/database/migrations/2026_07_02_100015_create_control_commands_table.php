<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 控制指令表
     */
    public function up(): void
    {
        Schema::create('control_commands', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('command_id', 50)->unique()->comment('全局唯一指令ID');
            $table->string('trace_id', 50)->index()->comment('全链路追踪ID');
            $table->unsignedBigInteger('decision_id')->nullable()->comment('关联AI决策');
            $table->foreign('decision_id')->references('id')->on('dispatch_decisions')->onDelete('set null');
            $table->unsignedBigInteger('gate_action_id')->nullable()->comment('关联闸门动作');
            $table->foreign('gate_action_id')->references('id')->on('gate_actions')->onDelete('set null');
            $table->unsignedBigInteger('edge_node_id')->index()->comment('目标边缘节点');
            $table->foreign('edge_node_id')->references('id')->on('edge_nodes')->onDelete('cascade');
            $table->index(['edge_node_id', 'status'], 'idx_commands_edge_status');
            $table->unsignedBigInteger('operator_id')->nullable()->index()->comment('操作人');
            $table->foreign('operator_id')->references('id')->on('users')->onDelete('restrict');
            $table->string('command_type', 50)->comment('指令类型：ai_auto / manual_adjust / emergency_stop / mode_switch / model_reload');
            $table->json('payload')->comment('指令内容');
            $table->string('target_equipment', 50)->nullable()->comment('目标设备编号');
            $table->decimal('target_opening', 5, 2)->comment('目标闸门开度（%）');
            $table->string('sign', 128)->comment('HMAC-SHA256签名');
            $table->string('nonce', 64)->index()->comment('防重放随机数');
            $table->index(['nonce', 'expire_at'], 'idx_commands_nonce_expire');
            $table->timestamp('expire_at')->comment('过期时间戳');
            $table->string('status', 20)->default('pending')->index()->comment('pending / sent / acknowledged / verified / executed / failed / rejected / expired');
            $table->timestamp('sent_at')->nullable()->index()->comment('云端下发时刻');
            $table->timestamp('acknowledged_at')->nullable()->comment('边缘端确认时间');
            $table->timestamp('verified_at')->nullable()->comment('校验通过时间');
            $table->timestamp('executed_at')->nullable()->comment('PLC执行时刻');
            $table->timestamp('feedback_at')->nullable()->comment('执行回执时间');
            $table->unsignedInteger('full_delay_ms')->nullable()->comment('全链路耗时（ms）');
            $table->json('execution_result')->nullable()->comment('执行回执详情');
            $table->string('reject_reason', 255)->nullable()->comment('拒绝原因');
            $table->tinyInteger('is_emergency')->default(0)->comment('是否急停指令');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('control_commands');
    }
};
