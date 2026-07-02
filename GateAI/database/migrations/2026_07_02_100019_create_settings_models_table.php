<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI模型管理表
     */
    public function up(): void
    {
        Schema::create('settings_models', function (Blueprint $table) {
            $table->id()->comment('主键');
            $table->string('name', 100)->comment('模型名称');
            $table->string('version', 30)->comment('版本号');
            $table->unique(['name', 'version'], 'uk_models_name_version');
            $table->string('type', 50)->index()->comment('模型类型：lstm_prediction / dqn_decision / fault_detection / general');
            $table->string('framework', 30)->nullable()->comment('框架：tensorflow / pytorch / onnx / custom');
            $table->string('status', 20)->default('uploaded')->index()->comment('uploaded / validating / ready / active / deprecated');
            $table->decimal('accuracy', 5, 2)->nullable()->comment('准确率（%）');
            $table->decimal('f1_score', 5, 4)->nullable()->comment('F1分数');
            $table->date('training_date')->nullable()->index()->comment('训练日期');
            $table->text('training_dataset')->nullable()->comment('训练数据集说明');
            $table->unsignedInteger('size')->default(0)->comment('模型大小（MB）');
            $table->string('file_path', 500)->comment('文件存储路径');
            $table->string('md5', 32)->nullable()->comment('文件MD5校验值');
            $table->json('tags')->nullable()->comment('标签');
            $table->tinyInteger('is_active')->default(0)->index()->comment('是否为当前激活版本');
            $table->unsignedInteger('deployed_nodes')->default(0)->comment('已下发边缘节点数');
            $table->unsignedBigInteger('previous_model_id')->nullable()->comment('上一个版本模型ID');
            $table->foreign('previous_model_id')->references('id')->on('settings_models')->onDelete('set null');
            $table->string('deploy_status', 20)->default('undeployed')->comment('undeployed / deploying / deployed / failed');
            $table->timestamp('deployed_at')->nullable()->comment('首次部署时间');
            $table->json('deploy_nodes')->nullable()->comment('已部署节点列表');
            $table->json('validation_report')->nullable()->comment('校验报告');
            $table->unsignedBigInteger('created_by')->nullable()->comment('创建人');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('created_at')->nullable()->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings_models');
    }
};
