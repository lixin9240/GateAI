<?php
// 告警阈值配置表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 告警阈值配置表
     */
    public function up(): void
    {
        Schema::create('settings_thresholds', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('reservoir_id')->nullable()->comment('适用水库，NULL=全局默认');
            $table->foreign('reservoir_id')->references('id')->on('reservoirs')->onDelete('restrict');
            $table->string('metric', 50)->index()->comment('监控指标：upstream_level / downstream_level / inflow_rate / outflow_rate / gate_opening / power_output');
            $table->string('equipment_type', 50)->nullable()->index()->comment('适用设备类型，NULL=全局');
            $table->index(['metric', 'equipment_type'], 'idx_thresholds_metric_equip');
            $table->decimal('warning_upper', 15, 4)->nullable()->comment('预警上限');
            $table->decimal('warning_lower', 15, 4)->nullable()->comment('预警下限');
            $table->decimal('critical_upper', 15, 4)->nullable()->comment('紧急上限');
            $table->decimal('critical_lower', 15, 4)->nullable()->comment('紧急下限');
            $table->unsignedInteger('debounce_seconds')->default(30)->comment('防抖时间（秒）');
            $table->tinyInteger('enabled')->default(1)->index()->comment('是否启用');
            $table->string('description', 255)->nullable()->comment('规则描述');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('最后更新人');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings_thresholds');
    }
};
