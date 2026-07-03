<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * LSTM预测结果表
     */
    public function up(): void
    {
        Schema::create('lstm_predictions', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('equipment_id')->comment('监测站点设备');
            $table->foreign('equipment_id')->references('id')->on('equipment')->onDelete('restrict');
            $table->tinyInteger('predict_term')->index()->comment('1=1h 2=3h 3=6h');
            $table->timestamp('base_time')->index()->comment('预测基准时间点');
            $table->json('water_seq_json')->comment('时序水位预测数组');
            $table->json('flow_seq_json')->comment('时序流量预测数组');
            $table->decimal('predict_accuracy', 5, 2)->comment('预测准确率 0~100');
            $table->timestamp('created_at')->nullable()->comment('预测生成时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lstm_predictions');
    }
};
