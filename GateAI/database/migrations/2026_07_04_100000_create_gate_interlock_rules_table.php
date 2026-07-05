<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_interlock_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reservoir_id')->nullable()->comment('关联水库，NULL=全局默认规则');
            $table->foreign('reservoir_id')->references('id')->on('reservoirs')->onDelete('cascade');
            $table->string('rule_code', 50)->comment('规则唯一标识');
            $table->string('rule_name', 100)->comment('规则中文名');
            $table->string('description', 255)->nullable()->comment('规则说明');
            $table->boolean('enabled')->default(true)->comment('是否启用');
            $table->unsignedInteger('priority')->default(0)->comment('优先级，数字越小越高');
            $table->json('trigger_conditions')->comment('触发条件参数');
            $table->json('constraint_action')->comment('约束动作参数');
            $table->timestamps();

            $table->index(['reservoir_id', 'enabled']);
            $table->unique(['reservoir_id', 'rule_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_interlock_rules');
    }
};
