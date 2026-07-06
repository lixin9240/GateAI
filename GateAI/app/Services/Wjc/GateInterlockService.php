<?php

namespace App\Services\Wjc;

use App\Models\EdgeNode;
use App\Models\GateInterlockLog;
use App\Models\GateInterlockRule;
use Illuminate\Support\Facades\DB;

class GateInterlockService
{
    /**
     * 获取某水库规则列表（全局默认 + 水库专属，按 priority 排序）
     */
    public function getRules(?int $reservoirId = null): array
    {
        $query = GateInterlockRule::query()
            ->where('enabled', true)
            ->where(function ($q) use ($reservoirId) {
                $q->whereNull('reservoir_id');
                if ($reservoirId !== null) {
                    $q->orWhere('reservoir_id', $reservoirId);
                }
            })
            ->orderBy('priority')
            ->orderBy('id');

        return $query->get()->toArray();
    }

    /**
     * 获取某水库所有规则（含禁用，用于管理页）
     */
    public function getAllRules(?int $reservoirId = null)
    {
        $query = GateInterlockRule::query()
            ->where(function ($q) use ($reservoirId) {
                $q->whereNull('reservoir_id');
                if ($reservoirId !== null) {
                    $q->orWhere('reservoir_id', $reservoirId);
                }
            })
            ->orderBy('priority')
            ->orderBy('id');

        return $query->get();
    }

    /**
     * 更新单条规则
     */
    public function updateRule(int $ruleId, array $data): GateInterlockRule
    {
        $rule = GateInterlockRule::findOrFail($ruleId);
        $rule->update($data);
        return $rule->fresh();
    }

    /**
     * 快速启用/禁用
     */
    public function toggleRule(int $ruleId, bool $enabled): GateInterlockRule
    {
        $rule = GateInterlockRule::findOrFail($ruleId);
        $rule->update(['enabled' => $enabled]);
        return $rule->fresh();
    }

    /**
     * 查询互锁触发日志
     */
    public function getRuleLogs(array $params)
    {
        $query = GateInterlockLog::query()
            ->with(['rule', 'reservoir']);

        if (!empty($params['reservoir_id'])) {
            $query->where('reservoir_id', $params['reservoir_id']);
        }
        if (!empty($params['rule_id'])) {
            $query->where('rule_id', $params['rule_id']);
        }
        if (!empty($params['start_time'])) {
            $query->where('trigger_time', '>=', $this->normalizeStartTime($params['start_time']));
        }
        if (!empty($params['end_time'])) {
            $query->where('trigger_time', '<=', $this->normalizeEndTime($params['end_time']));
        }

        $pageSize = $params['page_size'] ?? 20;
        return $query->orderByDesc('trigger_time')->paginate($pageSize);
    }

    /**
     * 统计各规则触发频次
     */
    public function getRuleStats(?int $reservoirId = null, int $days = 7): array
    {
        $query = GateInterlockLog::query()
            ->select('rule_id', DB::raw('count(*) as trigger_count'))
            ->where('trigger_time', '>=', now()->subDays($days));

        if ($reservoirId !== null) {
            $query->where('reservoir_id', $reservoirId);
        }

        return $query->groupBy('rule_id')
            ->with('rule:id,rule_code,rule_name')
            ->get()
            ->toArray();
    }

    /**
     * 导出供边缘端拉取的规则 JSON（合并全局 + 水库专属）
     */
    public function exportForEdge(int $edgeNodeId): array
    {
        $node = EdgeNode::with('reservoir')->findOrFail($edgeNodeId);
        $reservoirId = $node->reservoir_id;

        $rules = $this->getRules($reservoirId);

        return array_map(function ($rule) {
            return [
                'rule_code'          => $rule['rule_code'],
                'rule_name'          => $rule['rule_name'],
                'enabled'            => $rule['enabled'],
                'priority'           => $rule['priority'],
                'trigger_conditions' => $rule['trigger_conditions'],
                'constraint_action'  => $rule['constraint_action'],
            ];
        }, $rules);
    }

    /**
     * 接收边缘端上报的互锁触发事件
     */
    public function receiveInterlockLog(array $data): GateInterlockLog
    {
        return GateInterlockLog::create([
            'reservoir_id'         => $data['reservoir_id'],
            'rule_id'              => $data['rule_id'],
            'decision_id'          => $data['decision_id'] ?? null,
            'trigger_time'         => $data['trigger_time'] ?? now(),
            'gate1_opening_before' => $data['gate1_opening_before'],
            'gate2_opening_before' => $data['gate2_opening_before'],
            'gate3_opening_before' => $data['gate3_opening_before'],
            'upstream_level'       => $data['upstream_level'],
            'downstream_level'     => $data['downstream_level'],
            'inflow_rate'          => $data['inflow_rate'],
            'gate1_opening_after'  => $data['gate1_opening_after'],
            'gate2_opening_after'  => $data['gate2_opening_after'],
            'gate3_opening_after'  => $data['gate3_opening_after'],
            'action_detail'        => $data['action_detail'] ?? null,
        ]);
    }

    /**
     * 日期补全：只有日期无时间 → 自动补 00:00:00
     */
    private function normalizeStartTime(string $time): string
    {
        return strlen($time) === 10 ? "{$time} 00:00:00" : $time;
    }

    /**
     * 日期补全：只有日期无时间 → 自动补 23:59:59
     */
    private function normalizeEndTime(string $time): string
    {
        return strlen($time) === 10 ? "{$time} 23:59:59" : $time;
    }
}
