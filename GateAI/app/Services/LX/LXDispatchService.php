<?php

namespace App\Services\LX;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\ControlCommand;
use App\Models\GateAction;
use App\Support\LogHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LXDispatchService
{
    /**
     * 取消执行中的指令
     */
    public function cancelCommand(string $commandId): ControlCommand
    {
        $command = ControlCommand::where('command_id', $commandId)->first();

        if (! $command) {
            throw new BusinessException('指令不存在', ResponseCode::DATA_NOT_FOUND);
        }

        $cancellable = ['pending', 'sent'];
        if (! in_array($command->status, $cancellable)) {
            throw new BusinessException(
                "当前状态 [{$command->status}] 不可取消，仅 pending/sent 状态可取消",
                ResponseCode::STATUS_CANNOT_OPERATE
            );
        }

        $command->update(['status' => 'cancelled']);

        LogHelper::business('[调度] 取消执行指令', [
            'command_id'    => $commandId,
            'previous_status' => $command->getOriginal('status'),
            'user_id'       => auth()->id(),
        ], 'warning', 'DISPATCH_CANCEL');

        return $command->fresh();
    }

    /**
     * 单孔开度下发
     */
    public function gateExecute(array $data): string
    {
        $commandId = 'GATE-' . date('Ymd') . '-' . Str::random(6);

        DB::transaction(function () use ($data, $commandId) {
            $command = ControlCommand::create([
                'trace_id'        => request()->attributes->get('trace_id', (string) Str::uuid()),
                'edge_node_id'    => $data['edge_node_id'] ?? 1,
                'command_id'      => $commandId,
                'command_type'    => 'gate_adjust',
                'target_equipment' => $data['equipment_id'],
                'target_opening'  => $data['target_opening'],
                'payload'         => json_encode($data),
                'decision_id'     => $data['decision_id'] ?? null,
                'status'          => 'pending',
                'sent_at'         => now(),
                'nonce'           => Str::random(32),
                'sign'            => '',
                'expire_at'       => now()->addMinutes(5),
            ]);

            GateAction::create([
                'equipment_id'     => $data['equipment_id'],
                'decision_id'      => $data['decision_id'] ?? null,
                'command_id'       => $command->id,
                'previous_opening' => 0,
                'target_opening'   => $data['target_opening'],
                'action_type'      => 'manual_adjust',
                'action_source'    => 'manual',
                'acted_at'         => now(),
            ]);
        });

        LogHelper::business('[调度] 单孔开度下发', [
            'command_id'     => $commandId,
            'equipment_id'   => $data['equipment_id'],
            'target_opening' => $data['target_opening'],
            'user_id'        => auth()->id(),
        ], 'warning', 'GATE_EXECUTE');

        return $commandId;
    }

    /**
     * 批量孔开度下发
     */
    public function gateExecuteBatch(array $data): array
    {
        $results = [];

        DB::transaction(function () use ($data, &$results) {
            foreach ($data['gates'] as $gate) {
                $commandId = 'GATE-' . date('Ymd') . '-' . Str::random(6);

                $command = ControlCommand::create([
                    'trace_id'        => request()->attributes->get('trace_id', (string) Str::uuid()),
                    'edge_node_id'    => $data['edge_node_id'] ?? 1,
                    'command_id'      => $commandId,
                    'command_type'    => 'gate_adjust',
                    'target_equipment' => $gate['equipment_id'],
                    'target_opening'  => $gate['target_opening'],
                    'payload'         => json_encode($gate),
                    'decision_id'     => $data['decision_id'] ?? null,
                    'status'          => 'pending',
                    'sent_at'         => now(),
                    'nonce'           => Str::random(32),
                    'sign'            => '',
                    'expire_at'       => now()->addMinutes(5),
                ]);

                GateAction::create([
                    'equipment_id'     => $gate['equipment_id'],
                    'decision_id'      => $data['decision_id'] ?? null,
                    'command_id'       => $command->id,
                    'previous_opening' => 0,
                    'target_opening'   => $gate['target_opening'],
                    'action_type'      => 'batch_adjust',
                    'action_source'    => 'manual',
                    'acted_at'         => now(),
                ]);

                $results[] = [
                    'equipment_id'   => $gate['equipment_id'],
                    'target_opening' => $gate['target_opening'],
                    'command_id'     => $commandId,
                ];
            }
        });

        LogHelper::business('[调度] 批量孔开度下发', [
            'gate_count' => count($data['gates']),
            'user_id'    => auth()->id(),
        ], 'warning', 'GATE_EXECUTE_BATCH');

        return $results;
    }

    /**
     * 切换手动/自动模式
     */
    public function switchMode(array $data): array
    {
        LogHelper::business('[调度] 切换运行模式', [
            'reservoir_id' => $data['reservoir_id'],
            'mode'         => $data['mode'],
            'user_id'      => auth()->id(),
        ], 'info', 'DISPATCH_MODE_SWITCH');

        return [
            'reservoir_id' => $data['reservoir_id'],
            'mode'         => $data['mode'],
        ];
    }
}
