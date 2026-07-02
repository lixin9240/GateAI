<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 仿真结果表
     */
    public function up(): void
    {
        Schema::create('simulation_results', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('simulation_id')->unique()->comment('仿真任务ID');
            $table->foreign('simulation_id')->references('id')->on('simulation_tasks')->onDelete('cascade');
            $table->unsignedBigInteger('scenario_id')->index()->comment('场景ID');
            $table->foreign('scenario_id')->references('id')->on('simulation_scenarios')->onDelete('restrict');
            $table->string('status', 20)->index()->comment('最终状态：completed / terminated / error');
            $table->string('report_id', 50)->nullable()->comment('关联报告ID');
            $table->string('report_status', 20)->nullable()->comment('报告状态：pending / completed / failed');
            $table->timestamp('start_time')->nullable()->comment('开始时间');
            $table->timestamp('end_time')->nullable()->comment('结束时间');
            $table->json('summary')->nullable()->comment('汇总统计');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simulation_results');
    }
};
