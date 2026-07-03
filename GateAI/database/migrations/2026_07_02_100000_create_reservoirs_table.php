<?php
// 水库主表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 水库主表
     */
    public function up(): void
    {
        Schema::create('reservoirs', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('name', 100)->comment('水库名称');
            $table->string('code', 50)->unique()->comment('水库编码');
            $table->string('type', 30)->comment('水库类型：daily_regulation / seasonal / multi_year');
            $table->decimal('dead_water_level', 10, 3)->comment('死水位（m）');
            $table->decimal('normal_water_level', 10, 3)->comment('正常蓄水位（m）');
            $table->decimal('flood_limit_level', 10, 3)->comment('防洪限制水位（m）');
            $table->decimal('design_flood_level', 10, 3)->comment('设计洪水位（m）');
            $table->decimal('check_flood_level', 10, 3)->comment('校核洪水位（m）');
            $table->decimal('total_capacity', 15, 3)->comment('总库容（万m³）');
            $table->decimal('installed_capacity', 10, 3)->nullable()->comment('装机容量（kW）');
            $table->decimal('ecological_flow', 10, 3)->comment('生态流量（m³/s）');
            $table->decimal('location_lat', 10, 7)->nullable()->comment('纬度');
            $table->decimal('location_lng', 10, 7)->nullable()->comment('经度');
            $table->string('status', 20)->default('active')->index()->comment('active / inactive / maintenance');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservoirs');
    }
};
