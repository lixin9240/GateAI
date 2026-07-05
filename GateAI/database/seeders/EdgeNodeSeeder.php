<?php

namespace Database\Seeders;

use App\Models\EdgeNode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EdgeNodeSeeder extends Seeder
{
    public function run(): void
    {
        $nodes = [
            // 三峡水库 - 3个节点
            [
                'name'            => '三峡-坝前监测节点',
                'code'            => 'SX_EDGE_001',
                'reservoir_id'    => 1,
                'status'          => 'online',
                'location'        => '坝前水位监测站',
                'ip'              => '10.23.1.101',
                'last_heartbeat'  => now(),
                'edge_version'    => '2.1.0',
                'model_version'   => 'LSTM_v3.2',
                'autonomy_mode'   => 0,
                'cpu_usage'       => 35.2,
                'memory_usage'    => 52.8,
                'plc_status'      => 'online',
            ],
            [
                'name'            => '三峡-泄洪闸控制节点',
                'code'            => 'SX_EDGE_002',
                'reservoir_id'    => 1,
                'status'          => 'online',
                'location'        => '泄洪闸PLC控制柜',
                'ip'              => '10.23.1.102',
                'last_heartbeat'  => now(),
                'edge_version'    => '2.1.0',
                'model_version'   => 'LSTM_v3.2',
                'autonomy_mode'   => 1,
                'cpu_usage'       => 42.1,
                'memory_usage'    => 61.5,
                'plc_status'      => 'online',
            ],
            [
                'name'            => '三峡-下游监测节点',
                'code'            => 'SX_EDGE_003',
                'reservoir_id'    => 1,
                'status'          => 'fault',
                'location'        => '下游河道监测站',
                'ip'              => '10.23.1.103',
                'last_heartbeat'  => now()->subHours(2),
                'edge_version'    => '2.0.8',
                'model_version'   => 'LSTM_v3.1',
                'autonomy_mode'   => 0,
                'cpu_usage'       => 88.9,
                'memory_usage'    => 93.2,
                'plc_status'      => 'fault',
            ],
            // 溪洛渡水库 - 2个节点
            [
                'name'            => '溪洛渡-主监测节点',
                'code'            => 'XLD_EDGE_001',
                'reservoir_id'    => 2,
                'status'          => 'online',
                'location'        => '大坝主体监测中心',
                'ip'              => '10.24.1.101',
                'last_heartbeat'  => now(),
                'edge_version'    => '2.1.0',
                'model_version'   => 'LSTM_v3.2',
                'autonomy_mode'   => 0,
                'cpu_usage'       => 28.7,
                'memory_usage'    => 45.3,
                'plc_status'      => 'online',
            ],
            [
                'name'            => '溪洛渡-备用监测节点',
                'code'            => 'XLD_EDGE_002',
                'reservoir_id'    => 2,
                'status'          => 'offline',
                'location'        => '右岸备用监测站',
                'ip'              => '10.24.1.102',
                'last_heartbeat'  => now()->subDay(),
                'edge_version'    => '2.0.5',
                'model_version'   => 'LSTM_v2.9',
                'autonomy_mode'   => 0,
                'cpu_usage'       => 0,
                'memory_usage'    => 0,
                'plc_status'      => 'offline',
            ],
            // 向家坝水库 - 1个节点
            [
                'name'            => '向家坝-综合监测节点',
                'code'            => 'XJB_EDGE_001',
                'reservoir_id'    => 3,
                'status'          => 'online',
                'location'        => '综合监测控制中心',
                'ip'              => '10.25.1.101',
                'last_heartbeat'  => now(),
                'edge_version'    => '2.1.0',
                'model_version'   => 'LSTM_v3.2',
                'autonomy_mode'   => 0,
                'cpu_usage'       => 32.4,
                'memory_usage'    => 48.9,
                'plc_status'      => 'online',
            ],
            // 上游模拟水库 - 1个节点
            [
                'name'            => '模拟平台-上游控制节点',
                'code'            => 'SIM_EDGE_UP',
                'reservoir_id'    => 4,
                'status'          => 'online',
                'location'        => '模拟平台控制柜',
                'ip'              => '192.168.1.201',
                'last_heartbeat'  => now(),
                'edge_version'    => '1.0.0',
                'model_version'   => 'LSTM_v1.0',
                'autonomy_mode'   => 1,
                'cpu_usage'       => 45.2,
                'memory_usage'    => 62.1,
                'plc_status'      => 'online',
            ],
            // 下游模拟水库 - 1个节点
            [
                'name'            => '模拟平台-下游监测节点',
                'code'            => 'SIM_EDGE_DOWN',
                'reservoir_id'    => 5,
                'status'          => 'online',
                'location'        => '模拟平台下游侧',
                'ip'              => '192.168.1.202',
                'last_heartbeat'  => now(),
                'edge_version'    => '1.0.0',
                'model_version'   => 'LSTM_v1.0',
                'autonomy_mode'   => 0,
                'cpu_usage'       => 28.7,
                'memory_usage'    => 40.3,
                'plc_status'      => 'online',
            ],
        ];

        $existing = EdgeNode::pluck('code')->all();

        foreach ($nodes as $data) {
            if (!in_array($data['code'], $existing)) {
                $data['api_secret'] = hash('sha256', Str::random(32));
                EdgeNode::create($data);
            }
        }
    }
}
