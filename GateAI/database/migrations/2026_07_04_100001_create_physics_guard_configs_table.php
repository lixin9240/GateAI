<?php
// 物理防护配置表 — 替代 Python 侧硬编码的安全阈值，支持前端可视化配置、多水库差异化、变更可追溯可回滚
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physics_guard_configs', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('reservoir_id')->comment('关联水库');
            $table->foreign('reservoir_id')->references('id')->on('reservoirs')->onDelete('restrict');
            $table->string('config_version', 10)->comment('配置版本号，每次变更递增');
            $table->tinyInteger('is_active')->default(1)->comment('是否启用，同一水库仅一条 active');

            // 上游水位阈值
            $table->decimal('upstream_danger', 6, 2)->default(190.00)->comment('上游危险水位 (m)');
            $table->decimal('upstream_emergency', 6, 2)->default(193.00)->comment('上游紧急水位 (m)');
            $table->decimal('upstream_warning', 6, 2)->default(188.00)->comment('上游预警水位 (m)');
            $table->decimal('upstream_min', 6, 2)->default(167.00)->comment('死水位保护线 (m)');
            $table->decimal('ideal_min', 6, 2)->default(178.00)->comment('理想区间下限 (m)');
            $table->decimal('ideal_max', 6, 2)->default(188.00)->comment('理想区间上限 (m)');

            // 下游水位阈值
            $table->decimal('downstream_danger', 6, 2)->default(128.00)->comment('下游危险水位 (m)');
            $table->decimal('downstream_max', 6, 2)->default(130.00)->comment('下游最大水位 (m)');
            $table->decimal('downstream_min', 6, 2)->default(115.00)->comment('下游最小水位 (m)');

            // 生态/环境
            $table->decimal('eco_flow_min', 8, 2)->default(20.00)->comment('最小生态流量 (m³/s)');

            // 物理参数
            $table->decimal('reservoir_area', 12, 0)->default(15000000)->comment('水库水面面积 (m²)');
            $table->decimal('max_level_change_per_hour', 5, 2)->default(2.00)->comment('水位最大变化率 (m/h)');

            // 影子水位模型参数
            $table->unsignedInteger('shadow_lookahead_steps')->default(3)->comment('影子水位前瞻步数');
            $table->decimal('shadow_danger_offset', 5, 2)->default(3.00)->comment('影子水位危险线偏移量 (m)');

            // 指令平滑参数
            $table->decimal('deadband_percent', 5, 4)->default(0.0200)->comment('死区百分比 (0~1)');
            $table->decimal('max_rate_per_hour', 5, 4)->default(0.1000)->comment('最大变化率 %/h (0~1)');

            // 熔断阈值
            $table->decimal('fusion_l3_confidence', 5, 4)->default(0.7000)->comment('L3自动 置信度阈值');
            $table->decimal('fusion_l3_risk', 5, 4)->default(0.3000)->comment('L3自动 风险概率阈值');
            $table->decimal('fusion_l2_confidence', 5, 4)->default(0.5000)->comment('L2建议 置信度阈值');
            $table->decimal('fusion_l2_risk', 5, 4)->default(0.1000)->comment('L2建议 风险概率阈值');

            // 闸门参数
            $table->json('gate_max_discharge')->nullable()->comment('各闸门最大泄流量 (m³/s)，JSON数组');

            // 元数据
            $table->string('description', 255)->nullable()->comment('变更说明');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('更新人');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');

            // 唯一约束：同一水库同一版本号不可重复
            $table->unique(['reservoir_id', 'config_version'], 'uk_reservoir_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physics_guard_configs');
    }
};
