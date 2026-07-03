<?php
// 物理参数表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 物理参数表 —— 水位-库区面积映射表，供边缘端物理校验模块使用
     */
    public function up(): void
    {
        Schema::create('physical_parameters', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('reservoir_id')->comment('所属水库');
            $table->foreign('reservoir_id')->references('id')->on('reservoirs')->onDelete('restrict');
            $table->decimal('water_level', 10, 3)->comment('水位（m）');
            $table->unique(['reservoir_id', 'water_level'], 'uk_reservoir_water_level');
            $table->decimal('surface_area', 12, 2)->comment('该水位对应库区面积（m²）');
            $table->decimal('max_discharge', 10, 2)->nullable()->comment('该水位下最大泄洪能力（m³/s）');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('physical_parameters');
    }
};
