<?php
// 设备状态变更日志表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 设备状态变更日志表
     */
    public function up(): void
    {
        Schema::create('equipment_status_logs', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('equipment_id')->index()->comment('设备ID');
            $table->foreign('equipment_id')->references('id')->on('equipment')->onDelete('cascade');
            $table->string('previous_status', 20)->comment('变更前状态');
            $table->string('current_status', 20)->comment('变更后状态');
            $table->string('reason', 255)->nullable()->comment('变更原因');
            $table->string('operator', 50)->nullable()->comment('操作人');
            $table->timestamp('changed_at')->nullable()->index()->comment('变更时间');
            $table->index(['equipment_id', 'changed_at'], 'idx_equipment_status_equip_changed');
            $table->unsignedBigInteger('changed_by')->nullable()->comment('操作人ID');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
            $table->string('ip_address', 50)->nullable()->comment('操作人IP');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_status_logs');
    }
};
