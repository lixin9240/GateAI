<?php
// 模型漂移检测日志表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 模型数据分布漂移检测日志
     * 记录 Wasserstein 距离等漂移指标，用于监控模型输入特征分布变化
     */
    public function up(): void
    {
        Schema::create('model_drift_logs', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedInteger('reservoir_id')->index()->comment('水库ID');
            $table->decimal('drift_score', 5, 4)->default(0)->comment('Wasserstein 距离');
            $table->string('drift_level', 20)->default('normal')->comment('漂移等级：normal / warning / critical');
            $table->json('affected_features')->nullable()->comment('发生漂移的特征列表');
            $table->dateTime('detected_at')->index()->comment('检测时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_drift_logs');
    }
};
