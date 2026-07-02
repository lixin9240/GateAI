<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 登录日志表
     */
    public function up(): void
    {
        Schema::create('user_login_logs', function (Blueprint $table) {
            $table->id()->comment('日志ID');
            $table->unsignedBigInteger('user_id')->comment('操作用户');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->string('login_ip', 64)->comment('登录IP');
            $table->tinyInteger('login_status')->index()->comment('1=成功 0=失败');
            $table->string('fail_reason', 200)->nullable()->comment('失败原因');
            $table->string('access_token', 512)->nullable()->comment('本次登录令牌');
            $table->timestamp('created_at')->nullable()->index()->comment('登录时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_login_logs');
    }
};
