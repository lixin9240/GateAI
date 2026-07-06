<?php
// 设备表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 设备表
     */
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('name', 100)->comment('设备名称');
            $table->string('code', 50)->unique()->comment('设备编号');
            $table->string('type', 50)->index()->comment('设备类型：level_sensor / flow_sensor / plc / edge_gateway / actuator');// level_sensor：水位传感器 flow_sensor：流量传感器 plc：PLC edge_gateway：边缘网关 actuator：执行器
            $table->unsignedBigInteger('reservoir_id')->comment('所属水库');
            $table->foreign('reservoir_id')->references('id')->on('reservoirs')->onDelete('restrict');
            $table->string('status', 20)->default('offline')->index()->comment('online / offline / fault / maintenance');// online：在线 offline：离线 fault：故障 maintenance：维护中
            $table->index(['type', 'status'], 'idx_equipment_type_status');
            $table->string('location', 255)->nullable()->comment('安装位置描述');
            $table->string('manufacturer', 100)->nullable()->comment('制造商');
            $table->string('model', 100)->nullable()->comment('型号');
            $table->string('serial_number', 100)->nullable()->unique()->comment('序列号');
            $table->date('purchase_date')->nullable()->comment('采购日期');
            $table->date('warranty_expire')->nullable()->comment('质保到期日');
            $table->json('specs')->nullable()->comment('技术规格');
            $table->json('current_metrics')->nullable()->comment('当前实时指标');
            $table->decimal('health_score', 5, 2)->nullable()->index()->comment('健康评分 0.00~100.00');
            $table->json('tags')->nullable()->comment('标签列表');
            $table->unsignedBigInteger('edge_node_id')->nullable()->index()->comment('所属边缘节点');
            $table->foreign('edge_node_id')->references('id')->on('edge_nodes')->onDelete('set null');
            $table->string('plc_register', 50)->nullable()->comment('PLC寄存器地址');
            $table->string('communication_protocol', 30)->nullable()->comment('通信协议');
            $table->unsignedInteger('heartbeat_interval')->default(5)->comment('心跳间隔（秒）');
            $table->unsignedInteger('offline_threshold')->default(30)->comment('离线判定阈值（秒）');
            $table->string('firmware_version', 30)->nullable()->comment('固件版本');
            $table->unsignedInteger('maintenance_count')->default(0)->comment('维护次数');
            $table->timestamp('last_maintenance_at')->nullable()->comment('上次维护时间');
            $table->timestamp('next_maintenance_at')->nullable()->comment('下次计划维护时间');
            $table->unsignedBigInteger('total_runtime')->default(0)->comment('累计运行时长（秒）');
            $table->string('ip_address', 50)->nullable()->comment('设备IP地址');
            $table->unsignedInteger('port')->nullable()->comment('通信端口');
            $table->timestamp('last_online')->nullable()->index()->comment('最后在线时间');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('更新人');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->softDeletes()->comment('软删除时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment');
    }
};
