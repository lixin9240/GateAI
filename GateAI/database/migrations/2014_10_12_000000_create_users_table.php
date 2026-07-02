<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 用户表
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment('用户唯一ID');
            $table->string('account', 50)->unique()->comment('登录账号');
            $table->string('password', 255)->comment('bcrypt/ARGON2ID加密密码');
            $table->string('realname', 30)->index()->comment('真实姓名');
            $table->unsignedBigInteger('role_id')->comment('关联角色');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('restrict');
            $table->string('phone', 20)->nullable()->comment('联系电话');
            $table->tinyInteger('force_change_password')->default(1)->comment('首次登录是否强制改密');
            $table->tinyInteger('login_fail_count')->default(0)->comment('连续密码错误次数');
            $table->dateTime('lock_expire_time')->nullable()->index()->comment('账号锁定到期时间');
            $table->string('login_token', 512)->nullable()->comment('登录令牌');
            $table->dateTime('token_expire_time')->nullable()->comment('Token过期时间');
            $table->tinyInteger('is_enabled')->default(1)->comment('账号是否启用');
            $table->softDeletes()->comment('软删除时间');
            $table->timestamp('created_at')->nullable()->index()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
