<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 仿真结果时序数据表
     */
    public function up(): void
    {
        Schema::create('simulation_result_time_series', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('result_id')->index()->comment('仿真结果ID');
            $table->foreign('result_id')->references('id')->on('simulation_results')->onDelete('cascade');
            $table->index(['result_id', 'timestamp'], 'idx_sim_ts_result_time');
            $table->timestamp('timestamp')->index()->comment('时间点');
            $table->json('values')->comment('各指标数值');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('simulation_result_time_series');
    }
};
