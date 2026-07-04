<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_actions', function (Blueprint $table) {
            $table->unsignedBigInteger('interlock_rule_id')->nullable()->after('command_id')->comment('触发的互锁规则ID');
            $table->foreign('interlock_rule_id')->references('id')->on('gate_interlock_rules')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('gate_actions', function (Blueprint $table) {
            $table->dropForeign(['interlock_rule_id']);
            $table->dropColumn('interlock_rule_id');
        });
    }
};
