<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 业务日志表
     */
    public function up(): void
    {
        Schema::create('business_logs', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('trace_id', 50)->nullable()->index()->comment('链路追踪ID');
            $table->string('channel', 30)->default('business')->index()->comment('日志通道');
            $table->string('level', 20)->index()->comment('日志级别：info / warning / error');
            $table->index(['channel', 'level', 'created_at'], 'idx_business_logs_channel_level_created');
            $table->string('message', 500)->comment('日志内容');
            $table->json('context')->nullable()->comment('上下文数据');
            $table->unsignedBigInteger('user_id')->nullable()->comment('操作人');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->string('ip_address', 50)->nullable()->comment('操作人IP');
            $table->string('operation_type', 50)->nullable()->comment('操作类型');
            $table->timestamp('created_at')->nullable()->index()->comment('记录时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_logs');
    }
};
