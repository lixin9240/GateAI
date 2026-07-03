<?php
// 仿真场景表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 仿真场景表
     */
    public function up(): void
    {
        Schema::create('simulation_scenarios', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('name', 100)->comment('场景名称');
            $table->string('type', 50)->index()->comment('场景类型：production / energy / fault');// 场景类型：生产/能量/故障
            $table->text('description')->nullable()->comment('场景描述');
            $table->string('status', 20)->default('draft')->index()->comment('active / inactive / draft');// 状态：草稿/已发布/已停用
            $table->unsignedBigInteger('model_id')->nullable()->index()->comment('关联模型ID');
            $table->foreign('model_id')->references('id')->on('settings_models')->onDelete('set null');
            $table->json('scenario_config')->nullable()->comment('场景参数配置');
            $table->unsignedInteger('duration')->default(3600)->comment('默认仿真时长（秒）');
            $table->decimal('speed', 3, 1)->default(1.0)->comment('默认加速倍率');
            $table->tinyInteger('is_preset')->default(0)->comment('是否系统预设场景');
            $table->unsignedInteger('usage_count')->default(0)->comment('使用次数');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('更新人');
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
        Schema::dropIfExists('simulation_scenarios');
    }
};
