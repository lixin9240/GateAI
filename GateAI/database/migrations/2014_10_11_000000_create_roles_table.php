<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 角色表
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id()->comment('角色ID');
            $table->string('name', 50)->unique()->comment('角色名称（运维/调度/站长/管理员/算法）');
            $table->string('code', 50)->unique()->comment('角色编码枚举');
            $table->string('remark', 255)->nullable()->comment('角色描述');
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
        Schema::dropIfExists('roles');
    }
};
