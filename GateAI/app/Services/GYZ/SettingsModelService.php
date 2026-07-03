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

            Log::channel('business')->info('AI模型已激活', [
                'model_id' => $model->id,
                'name'     => $model->name,
                'version'  => $model->version,
                'user_id'  => $userId,
            ]);

            return $model->fresh();
        });
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

        return $results;
    }
}
