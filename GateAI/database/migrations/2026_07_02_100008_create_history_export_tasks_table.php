<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 历史数据导出任务表
     */
    public function up(): void
    {
        Schema::create('history_export_tasks', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('task_no', 50)->unique()->comment('导出任务编号');
            $table->json('equipment_ids')->comment('设备ID列表');
            $table->timestamp('start_time')->comment('开始时间');
            $table->timestamp('end_time')->comment('结束时间');
            $table->json('metrics')->comment('导出指标列表');
            $table->string('format', 20)->default('csv')->comment('csv / excel / json');
            $table->string('interval', 20)->default('1m')->comment('采样间隔');
            $table->string('file_name', 100)->nullable()->comment('自定义文件名');
            $table->string('email', 100)->nullable()->comment('通知邮箱');
            $table->string('status', 20)->default('queued')->index()->comment('queued / processing / completed / failed / expired');
            $table->index(['status', 'created_at'], 'idx_export_status_created');
            $table->decimal('progress', 5, 2)->default(0)->comment('进度 0.00~100.00');
            $table->unsignedBigInteger('file_size')->nullable()->comment('实际文件大小（字节）');
            $table->string('download_url', 500)->nullable()->comment('下载地址');
            $table->timestamp('expire_at')->nullable()->index()->comment('下载链接过期时间');
            $table->timestamp('completed_at')->nullable()->comment('任务完成时间');
            $table->text('error_msg')->nullable()->comment('错误信息');
            $table->string('estimated_size', 50)->nullable()->comment('预估文件大小');
            $table->unsignedInteger('estimated_time')->nullable()->comment('预计完成时间（秒）');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('created_at')->nullable()->index()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('history_export_tasks');
    }
};
