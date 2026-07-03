<?php
// 仿真任务表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 仿真任务表
     */
    public function up(): void
    {
        Schema::create('simulation_tasks', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('task_no', 50)->unique()->comment('仿真任务编号');
            $table->unsignedBigInteger('scenario_id')->index()->comment('仿真场景ID');
            $table->foreign('scenario_id')->references('id')->on('simulation_scenarios')->onDelete('cascade');
            $table->index(['scenario_id', 'status'], 'idx_sim_tasks_scenario_status');
            $table->unsignedBigInteger('model_id')->index()->comment('数字孪生模型ID');
            $table->foreign('model_id')->references('id')->on('settings_models')->onDelete('restrict');
            $table->unsignedInteger('duration')->default(3600)->comment('仿真时长（秒）');
            $table->decimal('speed', 3, 1)->default(1.0)->comment('时间加速倍率');
            $table->json('params')->nullable()->comment('仿真初始参数');
            $table->string('status', 20)->default('pending')->index()->comment('pending / running / completed / terminated / error');
            $table->timestamp('start_time')->nullable()->index()->comment('仿真启动时间');
            $table->timestamp('end_time')->nullable()->comment('仿真结束时间');
            $table->timestamp('estimated_end_time')->nullable()->comment('预计结束时间');
            $table->string('ws_endpoint', 255)->nullable()->comment('WebSocket推送地址');
            $table->decimal('progress', 5, 2)->default(0)->comment('仿真进度 0.00~100.00');
            $table->json('result_summary')->nullable()->comment('仿真结果摘要');
            $table->unsignedInteger('anomaly_count')->nullable()->comment('异常事件总数');
            $table->decimal('max_upstream_level', 10, 3)->nullable()->comment('最高上游水位（m）');
            $table->decimal('min_upstream_level', 10, 3)->nullable()->comment('最低上游水位（m）');
            $table->decimal('max_downstream_level', 10, 3)->nullable()->comment('最高下游水位（m）');
            $table->decimal('max_inflow_rate', 10, 3)->nullable()->comment('最大入库流量（m³/s）');
            $table->decimal('max_outflow_rate', 10, 3)->nullable()->comment('最大出库流量（m³/s）');
            $table->decimal('total_energy', 15, 3)->nullable()->comment('总发电量（kWh）');
            $table->decimal('total_discharge', 15, 3)->nullable()->comment('总泄流量（m³）');
            $table->text('error_msg')->nullable()->comment('失败原因');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
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
        Schema::dropIfExists('simulation_tasks');
    }
};
