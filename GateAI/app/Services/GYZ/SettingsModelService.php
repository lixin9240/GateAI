<?php

namespace App\Services\Gyz;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\SettingsModel;
use App\Models\SettingsModelDeployment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SettingsModelService
{
    /**
     * 模型列表
     */
    public function list(?string $type, ?string $status, ?string $keyword, int $page, int $pageSize): array
    {
        $query = SettingsModel::query()
            ->select([
                'id', 'name', 'version', 'type', 'framework', 'status',
                'accuracy', 'training_date', 'size', 'is_active', 'deployed_nodes',
            ]);

        if ($type !== null) {
            $query->where('type', $type);
        }
        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($keyword !== null) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('version', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->orderByDesc('created_at')->paginate($pageSize, ['*'], 'page', $page);

        return [
            'total' => $paginator->total(),
            'list'  => $paginator->items(),
        ];
    }

    /**
     * 上传模型
     */
    public function upload(array $data, int $userId): SettingsModel
    {
        $file = $data['file'];

        // 存储模型文件
        $path = $file->storeAs(
            'models/' . date('Ymd'),
            date('His') . '_' . $file->getClientOriginalName(),
            'local'
        );

        if (! $path) {
            throw new BusinessException('模型文件上传失败', ResponseCode::UPLOAD_FAILED);
        }

        $fullPath = Storage::disk('local')->path($path);
        $md5 = md5_file($fullPath);

        // 检查 name+version 唯一性
        if (SettingsModel::where('name', $data['name'])->where('version', $data['version'])->exists()) {
            Storage::disk('local')->delete($path);
            throw new BusinessException('该名称+版本号的模型已存在，请修改版本号后重新上传', ResponseCode::DATA_DUPLICATE);
        }

        return DB::transaction(function () use ($data, $path, $md5, $userId) {
            $model = SettingsModel::create([
                'name'            => $data['name'],
                'version'         => $data['version'],
                'type'            => $data['type'],
                'framework'       => $data['framework'] ?? null,
                'status'          => 'uploaded',
                'accuracy'        => $data['accuracy'] ?? null,
                'training_dataset' => $data['description'] ?? null,
                'size'            => (int) round(($data['size'] ?? 0) / 1048576),
                'file_path'       => $path,
                'md5'             => $md5,
                'created_by'      => $userId,
            ]);

            Log::channel('business')->info('AI模型已上传', [
                'model_id' => $model->id,
                'name'     => $model->name,
                'version'  => $model->version,
                'user_id'  => $userId,
            ]);

            return $model;
        });
    }

    /**
     * 激活模型
     */
    public function activate(int $id, bool $force, bool $rollbackOnFailure, int $userId): SettingsModel
    {
        return DB::transaction(function () use ($id, $force, $rollbackOnFailure, $userId) {
            $model = SettingsModel::find($id);

            if (! $model) {
                throw new BusinessException('模型不存在', ResponseCode::DATA_NOT_FOUND);
            }

            if ($model->status !== 'ready' && $model->status !== 'uploaded' && ! $force) {
                throw new BusinessException('模型状态不允许激活，当前状态：' . $model->status, ResponseCode::STATUS_CANNOT_OPERATE);
            }

            // 将同类型的旧激活模型标记为 deprecated
            SettingsModel::query()
                ->where('type', $model->type)
                ->where('is_active', 1)
                ->where('id', '!=', $model->id)
                ->update([
                    'status'    => 'deprecated',
                    'is_active' => 0,
                ]);

            // 激活当前模型
            $model->update([
                'status'         => 'active',
                'is_active'      => 1,
                'previous_model_id' => SettingsModel::query()
                    ->where('type', $model->type)
                    ->where('is_active', 0)
                    ->where('status', 'deprecated')
                    ->where('id', '!=', $model->id)
                    ->orderByDesc('updated_at')
                    ->value('id'),
            ]);

            // 真实校验：用 Python 加载模型文件，确保文件存在且可被 PyTorch 识别
            $fullPath = storage_path($model->file_path);
            if (! file_exists($fullPath)) {
                throw new BusinessException('模型文件不存在：' . $model->file_path, ResponseCode::FILE_READ_WRITE_FAILED);
            }

            $verified = $this->verifyModelFile($fullPath, $model->type);
            if (! $verified) {
                throw new BusinessException('模型文件加载失败，PyTorch 无法识别该文件', ResponseCode::PROGRAM_ERROR);
            }

            // 拷贝模型文件到 models/ 目录（infer_cli.py 只从这里加载）
            $targetDir = storage_path('ai/models');
            $targetFile = $targetDir . '/' . basename($fullPath);
            if ($fullPath !== $targetFile) {
                copy($fullPath, $targetFile);
            }

            // 更新 deploy_config.json，让 infer_cli.py 知道用哪个模型
            $this->switchDeployConfig($model, $fullPath);

            Log::channel('business')->info('AI模型已激活并验证', [
                'model_id' => $model->id,
                'name'     => $model->name,
                'version'  => $model->version,
                'user_id'  => $userId,
            ]);

            return $model->fresh();
        });
    }

    /**
     * 用 Python 验证模型文件能否被 PyTorch 加载
     */
    private function verifyModelFile(string $filePath, string $type): bool
    {
        $pythonBin = env('AI_PYTHON_BIN', 'python');
        $workDir = storage_path('ai');

        // 写临时验证脚本，避免 shell 转义问题
        $tmpScript = $workDir . '/_verify_model.py';
        $escapedPath = str_replace('\\', '/', $filePath);
        file_put_contents($tmpScript, <<<PYTHON
import json, torch
try:
    ckpt = torch.load('{$escapedPath}', map_location='cpu', weights_only=False)
    print(json.dumps({'ok': True}))
except Exception as e:
    print(json.dumps({'ok': False, 'error': str(e)}))
PYTHON);

        $process = proc_open(
            "{$pythonBin} {$tmpScript}",
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $workDir
        );

        if (! is_resource($process)) {
            @unlink($tmpScript);
            return false;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        @unlink($tmpScript);

        $result = json_decode($stdout, true);
        return ($result['ok'] ?? false) === true;
    }

    /**
     * 更新 deploy_config.json，切换推理引擎使用的模型文件
     */
    private function switchDeployConfig(SettingsModel $model, string $fullPath): void
    {
        $configPath = storage_path('ai/deploy_config.json');
        if (! file_exists($configPath)) {
            Log::warning('deploy_config.json 不存在，跳过模型切换');
            return;
        }

        $config = json_decode(file_get_contents($configPath), true);
        $fileBasename = basename($fullPath);

        if ($model->type === 'lstm_prediction') {
            $config['models']['lstm']['file'] = 'release/' . $fileBasename;
            $config['models']['lstm']['version'] = $model->version;
        } elseif ($model->type === 'dqn_decision') {
            $config['models']['dqn']['file'] = 'release/' . $fileBasename;
            $config['models']['dqn']['version'] = $model->version;
        }

        $config['version'] = $model->version . '-activated';
        $config['activated_at'] = now()->toDateTimeString();
        $config['active_models'] = [
            'lstm' => SettingsModel::query()
                ->where('type', 'lstm_prediction')->where('is_active', 1)
                ->select('name', 'version')->first()?->toArray(),
            'dqn'  => SettingsModel::query()
                ->where('type', 'dqn_decision')->where('is_active', 1)
                ->select('name', 'version')->first()?->toArray(),
        ];

        file_put_contents($configPath, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Log::channel('business')->info('deploy_config.json 已更新', ['type' => $model->type, 'file' => $fileBasename]);
    }

    /**
     * 回滚模型
     */
    public function rollback(int $id, ?string $reason, int $userId): SettingsModel
    {
        return DB::transaction(function () use ($id, $reason, $userId) {
            $model = SettingsModel::find($id);

            if (! $model) {
                throw new BusinessException('模型不存在', ResponseCode::DATA_NOT_FOUND);
            }

            if (! $model->is_active) {
                throw new BusinessException('只能回滚当前激活的模型', ResponseCode::STATUS_CANNOT_OPERATE);
            }

            // 查找上一个版本
            $previous = SettingsModel::query()
                ->where('type', $model->type)
                ->where('id', '!=', $model->id)
                ->whereIn('status', ['deprecated', 'ready'])
                ->orderByDesc('created_at')
                ->first();

            if (! $previous) {
                throw new BusinessException('没有可回滚的历史版本', ResponseCode::DATA_NOT_FOUND);
            }

            // 回退当前模型
            $model->update([
                'status'    => 'deprecated',
                'is_active' => 0,
            ]);

            // 激活上一版本
            $previous->update([
                'status'    => 'active',
                'is_active' => 1,
            ]);

            Log::channel('business')->warning('AI模型已回滚', [
                'from_model_id' => $model->id,
                'from_version'  => $model->version,
                'to_model_id'   => $previous->id,
                'to_version'    => $previous->version,
                'reason'        => $reason,
                'user_id'       => $userId,
            ]);

            return $previous->fresh();
        });
    }

    /**
     * 删除模型
     */
    public function delete(int $id): void
    {
        $model = SettingsModel::find($id);

        if (! $model) {
            throw new BusinessException('模型不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if ($model->is_active) {
            throw new BusinessException('已激活模型不可删除，请先切换或回滚', ResponseCode::STATUS_CANNOT_OPERATE);
        }

        DB::transaction(function () use ($model) {
            // 删除模型文件
            if ($model->file_path && Storage::disk('local')->exists($model->file_path)) {
                Storage::disk('local')->delete($model->file_path);
            }

            $model->delete();

            Log::channel('business')->info('AI模型已删除', [
                'model_id' => $model->id,
                'name'     => $model->name,
                'version'  => $model->version,
            ]);
        });
    }

    /**
     * 下发模型至边缘端
     */
    public function deploy(int $id, array $edgeNodeIds, ?string $strategy, int $userId): array
    {
        $model = SettingsModel::find($id);

        if (! $model) {
            throw new BusinessException('模型不存在', ResponseCode::DATA_NOT_FOUND);
        }

        if ($model->status !== 'active' && $model->status !== 'ready') {
            throw new BusinessException('模型状态不允许下发', ResponseCode::STATUS_CANNOT_OPERATE);
        }

        $results = [];

        DB::transaction(function () use ($model, $edgeNodeIds, $strategy, $userId, &$results) {
            foreach ($edgeNodeIds as $nodeId) {
                $deployment = SettingsModelDeployment::create([
                    'model_id'     => $model->id,
                    'edge_node_id' => (int) $nodeId,
                    'status'       => 'queued',
                    'strategy'     => $strategy ?? 'immediate',
                    'deployed_by'  => $userId,
                ]);

                $results[] = $deployment->toArray();
            }

            // 更新模型下发状态
            $model->update([
                'deploy_status'  => 'deploying',
                'deployed_nodes' => SettingsModelDeployment::where('model_id', $model->id)->count(),
            ]);

            Log::channel('business')->info('AI模型开始下发', [
                'model_id'      => $model->id,
                'name'          => $model->name,
                'edge_node_ids' => $edgeNodeIds,
                'strategy'      => $strategy,
                'user_id'       => $userId,
            ]);
        });

        // 真实下发：将模型文件同步到边缘节点部署目录（模拟 scp/rsync 到 Jetson）
        $sourcePath = storage_path($model->file_path);
        if (file_exists($sourcePath)) {
            foreach ($results as &$result) {
                $nodeId = $result['edge_node_id'];
                $targetDir = storage_path("ai/deployed/node_{$nodeId}");
                if (! is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                $targetFile = $targetDir . '/' . basename($model->file_path);
                $copied = copy($sourcePath, $targetFile);

                if ($copied) {
                    SettingsModelDeployment::where('id', $result['id'])->update([
                        'status'       => 'completed',
                        'md5_verified' => md5_file($targetFile) === $model->md5 ? 1 : 0,
                        'completed_at' => now(),
                    ]);
                    $result['status']        = 'completed';
                    $result['md5_verified']  = (int) (md5_file($targetFile) === $model->md5);
                    Log::channel('business')->info('模型文件已同步至边缘节点', [
                        'deployment_id' => $result['id'],
                        'edge_node_id'  => $nodeId,
                        'target'        => $targetFile,
                        'md5_match'     => (int) (md5_file($targetFile) === $model->md5),
                    ]);
                } else {
                    SettingsModelDeployment::where('id', $result['id'])->update([
                        'status'    => 'failed',
                        'error_msg' => '文件复制失败',
                    ]);
                    $result['status'] = 'failed';
                    Log::channel('exception')->error('模型文件下发失败', [
                        'deployment_id' => $result['id'],
                        'source'        => $sourcePath,
                        'target'        => $targetFile,
                    ]);
                }
            }
            unset($result);

            // 更新模型下发完成状态
            $completedCount = SettingsModelDeployment::where('model_id', $model->id)
                ->where('status', 'completed')->count();
            $model->update([
                'deploy_status'  => $completedCount > 0 ? 'deployed' : 'failed',
                'deployed_nodes' => $completedCount,
                'deploy_nodes'   => json_encode($edgeNodeIds),
                'deployed_at'    => $completedCount > 0 ? now() : null,
            ]);
        }

        return $results;
    }
}
