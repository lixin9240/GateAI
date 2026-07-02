<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 添加 gate_actions.command_id → control_commands.id 外键（因循环依赖延迟创建）
     */
    public function up(): void
    {
        Schema::table('gate_actions', function (Blueprint $table) {
            $table->foreign('command_id')->references('id')->on('control_commands')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gate_actions', function (Blueprint $table) {
            $table->dropForeign(['command_id']);
        });
    }
};
