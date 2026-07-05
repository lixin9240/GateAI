<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_interlock_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reservoir_id')->comment('水库ID');
            $table->foreign('reservoir_id')->references('id')->on('reservoirs')->onDelete('cascade');
            $table->unsignedBigInteger('rule_id')->comment('触发的规则ID');
            $table->foreign('rule_id')->references('id')->on('gate_interlock_rules')->onDelete('cascade');
            $table->unsignedBigInteger('decision_id')->nullable()->comment('关联调度决策ID');
            $table->foreign('decision_id')->references('id')->on('dispatch_decisions')->onDelete('set null');
            $table->timestamp('trigger_time')->comment('触发时间');

            $table->decimal('gate1_opening_before', 5, 4)->comment('闸门1互锁前开度');
            $table->decimal('gate2_opening_before', 5, 4);
            $table->decimal('gate3_opening_before', 5, 4);
            $table->decimal('upstream_level', 6, 2)->comment('触发时上游水位');
            $table->decimal('downstream_level', 6, 2)->comment('触发时下游水位');
            $table->decimal('inflow_rate', 8, 2)->comment('触发时入库流量');

            $table->decimal('gate1_opening_after', 5, 4)->comment('闸门1互锁后开度');
            $table->decimal('gate2_opening_after', 5, 4);
            $table->decimal('gate3_opening_after', 5, 4);
            $table->json('action_detail')->nullable()->comment('详细约束过程描述');

            $table->timestamp('created_at')->nullable();

            $table->index(['reservoir_id', 'trigger_time']);
            $table->index('rule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_interlock_logs');
    }
};
