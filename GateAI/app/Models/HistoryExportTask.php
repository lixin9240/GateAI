<?php
// 历史导出任务模型
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoryExportTask extends Model
{
    protected $table = 'history_export_tasks';

    protected $fillable = [
        'task_no', // 任务编号
        'equipment_ids', // 设备ID列表
        'start_time', // 开始时间
        'end_time', // 结束时间
        'metrics', // 指标列表
        'format', // 导出格式
        'interval', // 时间间隔
        'file_name', // 文件名
        'email', // 邮箱
        'status', // 状态
        'progress', // 进度
        'file_size', // 文件大小
        'download_url', // 下载URL
        'expire_at', // 过期时间
        'completed_at', // 完成时间
        'error_msg', // 错误消息
        'estimated_size', // 预估文件大小
        'estimated_time', // 预估时间
        'created_by', // 创建人
    ];

    protected $casts = [
        'equipment_ids' => 'json',
        'metrics'       => 'json',
        'start_time'    => 'datetime',
        'end_time'      => 'datetime',
        'expire_at'     => 'datetime',
        'completed_at'  => 'datetime',
        'progress'      => 'float',
        'file_size'     => 'integer',
        'estimated_time' => 'integer',
    ];
}
