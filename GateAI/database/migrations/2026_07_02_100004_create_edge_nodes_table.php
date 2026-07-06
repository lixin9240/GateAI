<?php
// 边缘节点表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 边缘节点表
     */
    public function up(): void
    {
        Schema::create('edge_nodes', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('name', 100)->comment('节点名称');
            $table->string('code', 50)->unique()->comment('节点编号');
            $table->unsignedBigInteger('reservoir_id')->comment('所属水库');
            $table->foreign('reservoir_id')->references('id')->on('reservoirs')->onDelete('restrict');
            $table->string('status', 20)->default('offline')->index()->comment('online / offline / fault');// online：在线 offline：离线 fault：故障
            $table->string('location', 255)->nullable()->comment('安装位置');
            $table->string('ip', 50)->nullable()->comment('IP地址');
            $table->timestamp('last_heartbeat')->nullable()->index()->comment('最后心跳时间');
            $table->string('edge_version', 30)->nullable()->comment('边缘端软件版本');
            $table->string('model_version', 30)->nullable()->comment('当前AI模型版本');
            $table->string('threshold_version', 30)->nullable()->comment('当前阈值配置版本');
            $table->string('weight_version', 30)->nullable()->comment('当前权重配置版本');
            $table->string('physics_config_version', 30)->nullable()->comment('物理参数配置版本');
            $table->tinyInteger('autonomy_mode')->default(0)->comment('是否断网自治');
            $table->timestamp('autonomy_since')->nullable()->comment('进入自治模式时间');
            $table->unsignedBigInteger('cache_size')->default(0)->comment('本地缓存数据条数');
            $table->decimal('cpu_usage', 5, 2)->nullable()->comment('CPU使用率（%）');
            $table->decimal('memory_usage', 5, 2)->nullable()->comment('内存使用率（%）');
            $table->decimal('disk_usage', 5, 2)->nullable()->comment('磁盘使用率（%）');
            $table->string('plc_status', 20)->default('offline')->comment('PLC连接状态');
            $table->timestamp('plc_last_comm')->nullable()->comment('PLC最后通信时间');
            $table->unsignedBigInteger('total_uptime')->default(0)->comment('累计运行时长（秒）');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('edge_nodes');
    }
};
