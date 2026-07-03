<?php
// 急停日志表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 急停日志表
     */
    public function up(): void
    {
        Schema::create('emergency_stops', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('trigger_user_id')->comment('触发急停操作人员');
            $table->foreign('trigger_user_id')->references('id')->on('users')->onDelete('restrict');
            $table->unsignedBigInteger('decision_id')->nullable()->comment('触发时的调度决策ID');
            $table->foreign('decision_id')->references('id')->on('dispatch_decisions')->onDelete('set null');
            $table->unsignedBigInteger('command_id')->nullable()->comment('急停指令ID');
            $table->foreign('command_id')->references('id')->on('control_commands')->onDelete('set null');
            $table->timestamp('trigger_time')->index()->comment('急停下发时间');
            $table->timestamp('edge_ack_time')->nullable()->comment('边缘网关确认时间');
            $table->timestamp('plc_shut_time')->nullable()->comment('PLC闸门停止时刻');
            $table->unsignedBigInteger('recover_user_id')->nullable()->comment('恢复自动操作人员');
            $table->foreign('recover_user_id')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('recover_time')->nullable()->comment('恢复操作时间');
            $table->string('stop_reason', 255)->nullable()->comment('急停原因备注');
            $table->timestamp('created_at')->nullable()->comment('记录创建时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_stops');
    }
};
