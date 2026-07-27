<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_page_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('page_id', 64)->comment('页面标识');
            $table->string('role_name', 32)->comment('角色名称');
            $table->unique(['page_id', 'role_name'], 'uk_page_role');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_page_permissions');
    }
};
