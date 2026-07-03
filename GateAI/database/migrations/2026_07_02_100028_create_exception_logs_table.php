<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 异常日志表
     */
    public function up(): void
    {
        Schema::create('exception_logs', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('trace_id', 50)->nullable()->index()->comment('链路追踪ID');
            $table->text('message')->comment('异常信息');
            $table->string('file', 255)->comment('发生文件');
            $table->index(['file', 'line'], 'idx_exception_logs_file_line');
            $table->unsignedInteger('line')->comment('发生行号');
            $table->longText('trace')->comment('堆栈跟踪');
            $table->text('sql')->nullable()->comment('执行的SQL');
            $table->json('bindings')->nullable()->comment('SQL绑定参数');
            $table->unsignedBigInteger('user_id')->nullable()->comment('触发用户');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->string('request_url', 500)->nullable()->comment('触发请求的URL');
            $table->timestamp('created_at')->nullable()->index()->comment('记录时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exception_logs');
    }
};
