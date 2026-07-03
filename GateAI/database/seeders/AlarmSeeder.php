<?php

namespace Database\Seeders;

use App\Models\Alarm;
use Illuminate\Database\Seeder;

class AlarmSeeder extends Seeder
{
    public function run(): void
    {
        $alarms = [
            [
                'alarm_no'        => 'ALM-' . date('Ymd') . '-001',
                'reservoir_id'    => 1,
                'equipment_id'    => null,
                'type'            => 'water_level',
                'level'           => 'urgent',
                'message'         => '三峡水库上游水位超汛限水位，当前176.8m，超出汛限145.0m',
                'metric_value'    => 176.8,
                'threshold_value' => 145.0,
                'duration'        => 3600,
                'exceed_start'    => now()->subHours(1),
                'status'          => 'unhandled',
                'trace_id'        => \Illuminate\Support\Str::uuid(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'alarm_no'        => 'ALM-' . date('Ymd') . '-002',
                'reservoir_id'    => 1,
                'equipment_id'    => null,
                'type'            => 'gate',
                'level'           => 'important',
                'message'         => '三峡2号泄洪闸门开度反馈异常，指令开度35%但实际开度28%',
                'metric_value'    => 28,
                'threshold_value' => 35,
                'duration'        => 900,
                'exceed_start'    => now()->subMinutes(15),
                'status'          => 'acknowledged',
                'acknowledged_at' => now()->subMinutes(10),
                'acknowledged_by' => 1,
                'trace_id'        => \Illuminate\Support\Str::uuid(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'alarm_no'        => 'ALM-' . date('Ymd') . '-003',
                'reservoir_id'    => 2,
                'equipment_id'    => null,
                'type'            => 'flow',
                'level'           => 'normal',
                'message'         => '溪洛渡入库流量异常增大，当前流量2850m³/s',
                'metric_value'    => 2850,
                'threshold_value' => 2000,
                'duration'        => 1800,
                'exceed_start'    => now()->subMinutes(30),
                'status'          => 'disposed',
                'disposed_at'     => now()->subMinutes(5),
                'disposed_by'     => 1,
                'dispose_note'    => '已确认因上游降雨导致，属正常来水，已调整泄洪方案',
                'trace_id'        => \Illuminate\Support\Str::uuid(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'alarm_no'        => 'ALM-' . date('Ymd') . '-004',
                'reservoir_id'    => 3,
                'equipment_id'    => null,
                'type'            => 'power',
                'level'           => 'important',
                'message'         => '向家坝3号机组功率骤降，当前出力420MW仅为额定65%',
                'metric_value'    => 420,
                'threshold_value' => 500,
                'duration'        => 600,
                'exceed_start'    => now()->subMinutes(10),
                'status'          => 'unhandled',
                'trace_id'        => \Illuminate\Support\Str::uuid(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ];

        foreach ($alarms as $data) {
            Alarm::create($data);
        }
    }
}
