<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('power_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reservoir_id')->comment('所属水库');
            $table->foreign('reservoir_id')->references('id')->on('reservoirs')->onDelete('cascade');
            $table->string('name', 100)->comment('机组名称，如 1号发电机组');
            $table->string('code', 50)->unique()->comment('机组编号');
            $table->string('type', 30)->default('hydro')->comment('机组类型：hydro=水轮发电机');
            $table->decimal('installed_capacity', 12, 2)->comment('装机容量（kW）');
            $table->string('status', 20)->default('offline')->index()->comment('online / offline / maintenance / fault');
            $table->decimal('current_output', 10, 2)->nullable()->comment('当前出力（kW），从 monitoring_data 定时同步');
            $table->string('manufacturer', 100)->nullable()->comment('制造商');
            $table->string('model', 100)->nullable()->comment('型号');
            $table->date('commission_date')->nullable()->comment('投产日期');
            $table->timestamp('last_synced_at')->nullable()->comment('最后同步时间');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('power_units');
    }
};
