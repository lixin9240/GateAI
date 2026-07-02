<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 用户锁定记录表
     */
    public function up(): void
    {
        Schema::create('user_locks', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('user_id')->index()->comment('用户ID');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('reason', 255)->comment('锁定原因');
            $table->unsignedInteger('duration')->default(0)->comment('自动解锁时长（分钟），0=永久');
            $table->timestamp('locked_at')->nullable()->index()->comment('锁定时间');
            $table->timestamp('unlock_at')->nullable()->index()->comment('预计自动解锁时间');
            $table->string('unlock_type', 20)->default('auto')->comment('解锁方式：auto / manual');
            $table->unsignedBigInteger('unlocked_by')->nullable()->comment('手动解锁操作人');
            $table->foreign('unlocked_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('unlocked_at')->nullable()->comment('实际解锁时间');
            $table->unsignedBigInteger('locked_by')->nullable()->comment('操作人ID');
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_locks');
    }
};
