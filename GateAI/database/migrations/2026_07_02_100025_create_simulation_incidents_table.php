<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 故障复盘记录表
     */
    public function up(): void
    {
        Schema::create('simulation_incidents', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('incident_name', 100)->comment('故障名称');
            $table->text('description')->nullable()->comment('故障描述');
            $table->string('severity', 20)->index()->comment('low / medium / high / critical');
            $table->unsignedBigInteger('equipment_id')->index()->comment('关联设备ID');
            $table->foreign('equipment_id')->references('id')->on('equipment')->onDelete('restrict');
            $table->index(['equipment_id', 'occurred_at'], 'idx_incidents_equipment_occurred');
            $table->timestamp('occurred_at')->index()->comment('发生时间');
            $table->timestamp('resolved_at')->nullable()->comment('恢复时间');
            $table->unsignedInteger('duration')->nullable()->comment('持续时长（秒）');
            $table->text('root_cause')->nullable()->comment('根因分析摘要');
            $table->unsignedBigInteger('simulation_id')->nullable()->comment('关联仿真任务ID');
            $table->foreign('simulation_id')->references('id')->on('simulation_tasks')->onDelete('set null');
            $table->json('raw_data')->nullable()->comment('故障原始数据');
            $table->json('scenario_config')->nullable()->comment('导入后生成场景的自定义配置');
            $table->string('incident_type', 50)->nullable()->comment('故障类型');
            $table->text('resolution')->nullable()->comment('处置措施');
            $table->string('responsibility', 100)->nullable()->comment('责任认定');
            $table->text('lesson_learned')->nullable()->comment('经验教训');
            $table->json('related_alarms')->nullable()->comment('关联告警ID列表');
            $table->unsignedBigInteger('replayed_scenario_id')->nullable()->comment('复盘仿真场景ID');
            $table->foreign('replayed_scenario_id')->references('id')->on('simulation_scenarios')->onDelete('set null');
            $table->string('import_id', 50)->nullable()->unique()->comment('导入任务ID');
            $table->string('status', 20)->default('imported')->index()->comment('imported / processing / failed / success');
            $table->unsignedBigInteger('created_by')->nullable()->comment('导入人');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simulation_incidents');
    }
};
