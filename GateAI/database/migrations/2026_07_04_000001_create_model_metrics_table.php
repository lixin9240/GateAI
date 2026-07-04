<?php
// 模型三维评判指标表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 模型健康度三维评判——指标表
     * 滚动24h聚合边缘端上报数据，计算预测准确性、决策可靠性、物理合规性评分
     */
    public function up(): void
    {
        Schema::create('model_metrics', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedInteger('edge_node_id')->index()->comment('边缘节点ID');
            $table->unsignedInteger('reservoir_id')->index()->comment('水库ID');
            $table->dateTime('metric_time')->index()->comment('指标时间（每小时一条）');

            // —— 维度一：预测准确性 ——
            $table->decimal('water_level_mae_24h', 8, 4)->default(0)->comment('滚动24h水位MAE (m)');
            $table->decimal('flow_mae_24h', 8, 2)->default(0)->comment('滚动24h流量MAE (m³/s)');
            $table->decimal('physics_correction_rate', 5, 4)->default(0)->comment('物理修正次数占比 (0~1)');
            $table->decimal('trend_accuracy', 5, 4)->default(0)->comment('趋势方向准确率 (0~1)');
            $table->decimal('prediction_score', 5, 4)->default(0)->comment('维度一综合分 (0~1)');

            // —— 维度二：决策可靠性 ——
            $table->decimal('safety_override_rate', 5, 4)->default(0)->comment('安全规则覆盖率 (0~1)');
            $table->json('decision_level_dist')->nullable()->comment('{L3:%, L2:%, L1:%, OVERRIDE:%}');
            $table->decimal('shadow_risk_pass_rate', 5, 4)->default(0)->comment('影子风险通过率 (0~1)');
            $table->decimal('smooth_filter_rate', 5, 4)->default(0)->comment('指令平滑率 (0~1)');
            $table->decimal('decision_score', 5, 4)->default(0)->comment('维度二综合分 (0~1)');

            // —— 维度三：物理合规性 ——
            $table->decimal('avg_physics_violation', 8, 4)->default(0)->comment('平均物理偏差 (m)');
            $table->decimal('gate_limit_touch_rate', 5, 4)->default(0)->comment('闸门限位触碰率 (0~1)');
            $table->decimal('rate_limit_exceed_rate', 5, 4)->default(0)->comment('变化率超限率 (0~1)');
            $table->decimal('compliance_score', 5, 4)->default(0)->comment('维度三综合分 (0~1)');

            // —— 综合 ——
            $table->decimal('overall_score', 5, 4)->default(0)->comment('综合评分 (0~1)');
            $table->char('health_grade', 1)->default('D')->comment('健康等级：S/A/B/C/D');

            $table->timestamp('created_at')->nullable()->comment('创建时间');

            // 复合唯一索引：每个水库每小时只能有一条指标
            $table->unique(['reservoir_id', 'metric_time'], 'idx_reservoir_time');
            $table->index(['edge_node_id', 'metric_time'], 'idx_node_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_metrics');
    }
};
