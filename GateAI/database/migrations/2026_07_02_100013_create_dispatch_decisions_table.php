<?php
// AI调度决策表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI调度决策表
     */
    public function up(): void
    {
        Schema::create('dispatch_decisions', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('trace_id', 50)->unique()->comment('全链路追踪ID');
            $table->unsignedBigInteger('reservoir_id')->index()->comment('所属水库');
            $table->foreign('reservoir_id')->references('id')->on('reservoirs')->onDelete('restrict');
            $table->index(['reservoir_id', 'decision_time'], 'idx_decisions_reservoir_time');
            $table->unsignedBigInteger('edge_node_id')->index()->comment('边缘节点');
            $table->foreign('edge_node_id')->references('id')->on('edge_nodes')->onDelete('cascade');
            $table->index(['edge_node_id', 'decision_time'], 'idx_decisions_edge_time');
            $table->unsignedBigInteger('prediction_id')->comment('关联预测数据');
            $table->foreign('prediction_id')->references('id')->on('lstm_predictions')->onDelete('restrict');
            $table->timestamp('decision_time')->index()->comment('决策时间');
            $table->string('decision_mode', 10)->index()->comment('执行模式：L1 / L2 / L3');
            $table->tinyInteger('risk_rank')->index()->comment('1=低风险 2=中风险 3=高风险');
            $table->decimal('upstream_level', 10, 3)->comment('决策时上游水位（m）');
            $table->decimal('downstream_level', 10, 3)->comment('决策时下游水位（m）');
            $table->decimal('inflow_rate', 10, 3)->comment('决策时入库流量（m³/s）');
            $table->decimal('current_opening', 5, 2)->comment('当前闸门开度（%）');
            $table->json('lstm_predictions')->nullable()->comment('LSTM预测结果');
            $table->decimal('recommended_opening', 5, 2)->comment('推荐闸门开度（%）');
            $table->decimal('confidence', 5, 2)->comment('置信度 0.00~100.00');
            $table->json('factors')->comment('影响因素列表 [{name,value,direction,weight}]');
            $table->json('alternatives')->comment('方案对比');
            $table->json('weights_used')->comment('使用的权重配置');
            $table->decimal('reward_score', 10, 4)->nullable()->comment('奖励函数得分');
            $table->json('physics_validation')->nullable()->comment('物理校验结果');
            $table->string('execution_status', 20)->default('pending')->index()->comment('pending / executed / rejected / failed');
            $table->decimal('executed_opening', 5, 2)->nullable()->comment('实际执行开度（%）');
            $table->timestamp('executed_at')->nullable()->comment('执行时间');
            $table->unsignedBigInteger('confirmed_by')->nullable()->comment('人工确认人');
            $table->foreign('confirmed_by')->references('id')->on('users')->onDelete('set null');
            $table->text('reject_reason')->nullable()->comment('拒绝原因');
            $table->decimal('actual_level_after', 10, 3)->nullable()->comment('执行后实际水位（m）');
            $table->decimal('actual_power_after', 10, 3)->nullable()->comment('执行后实际功率（kW）');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch_decisions');
    }
};
