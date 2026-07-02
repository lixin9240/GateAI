<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * API请求日志表 —— 建议按季度分区
     */
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('trace_id', 50)->index()->comment('链路追踪ID');
            $table->string('url', 500)->comment('请求URL');
            $table->index(['url', 'created_at'], 'idx_api_logs_url_created');
            $table->string('method', 10)->comment('请求方法');
            $table->string('ip', 50)->comment('客户端IP');
            $table->unsignedBigInteger('user_id')->nullable()->index()->comment('用户ID');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->json('request')->nullable()->comment('请求参数（生产环境仅status≥400时记录）');
            $table->unsignedSmallInteger('response_status')->comment('响应状态码');
            $table->index(['response_status', 'created_at'], 'idx_api_logs_status_created');
            $table->decimal('duration_ms', 10, 2)->comment('耗时（毫秒）');
            $table->text('user_agent')->nullable()->comment('客户端UA');
            $table->json('response_body')->nullable()->comment('响应内容（生产环境仅status≥400时记录）');
            $table->json('request_headers')->nullable()->comment('请求头');
            $table->timestamp('created_at')->nullable()->index()->comment('记录时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
