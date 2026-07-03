<?php
// 多目标权重配置表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 多目标权重配置表
     */
    public function up(): void
    {
        Schema::create('settings_weights', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('version', 20)->index()->comment('配置版本号');
            $table->tinyInteger('enabled')->default(1)->index()->comment('是否启用');
            $table->decimal('power_weight', 3, 2)->default(0.40)->comment('发电效益权重 0.00~1.00');
            $table->decimal('safety_weight', 3, 2)->default(0.35)->comment('安全权重');
            $table->decimal('ecology_weight', 3, 2)->default(0.25)->comment('生态流量权重');
            $table->string('preset_name', 50)->nullable()->index()->comment('预设方案名称');
            $table->tinyInteger('is_preset')->default(0)->comment('是否为系统预设');
            $table->string('sync_status', 20)->default('pending')->comment('同步状态');
            $table->timestamp('synced_at')->nullable()->comment('最后同步时间');
            $table->json('synced_nodes')->nullable()->comment('已同步节点列表');
            $table->string('description', 255)->nullable()->comment('方案描述');
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
        Schema::dropIfExists('settings_weights');
    }
};
