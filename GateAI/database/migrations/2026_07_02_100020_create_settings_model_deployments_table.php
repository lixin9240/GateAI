<?php
// 模型部署记录表
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 模型部署记录表
     */
    public function up(): void
    {
        Schema::create('settings_model_deployments', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('model_id')->index()->comment('模型ID');
            $table->foreign('model_id')->references('id')->on('settings_models')->onDelete('cascade');
            $table->index(['model_id', 'status'], 'idx_deployments_model_status');
            $table->unsignedBigInteger('edge_node_id')->index()->comment('边缘节点ID');
            $table->foreign('edge_node_id')->references('id')->on('edge_nodes')->onDelete('cascade');
            $table->unique(['model_id', 'edge_node_id'], 'uk_deployments_model_edge');
            $table->string('status', 20)->default('queued')->index()->comment('queued / deploying / completed / failed');
            $table->string('strategy', 20)->default('immediate')->comment('immediate / gradual / scheduled');
            $table->timestamp('scheduled_at')->nullable()->comment('定时下发时间');
            $table->unsignedInteger('batch_size')->nullable()->comment('灰度批次大小');
            $table->text('error_msg')->nullable()->comment('错误信息');
            $table->unsignedBigInteger('deployed_by')->nullable()->comment('下发操作人');
            $table->foreign('deployed_by')->references('id')->on('users')->onDelete('set null');
            $table->tinyInteger('md5_verified')->default(0)->comment('MD5校验是否通过');
            $table->unsignedBigInteger('rollback_to')->nullable()->comment('回滚目标版本ID');
            $table->foreign('rollback_to')->references('id')->on('settings_models')->onDelete('set null');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings_model_deployments');
    }
};
