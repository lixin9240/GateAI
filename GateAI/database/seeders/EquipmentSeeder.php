<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    /**
     * 水利模拟仿真平台 —— 硬件设备种子数据
     * 对应 Equipment 模型字段
     */
    public function run(): void
    {
        // 可重复运行：先清空再插入
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
        \App\Models\Equipment::truncate();
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $now = now();

        /* 上游水库 ID=1, 下游水库 ID=2 —— 与 ReservoirSeeder 顺序一致 */
        $UP   = 1;
        $DOWN = 2;

        $items = [

            // ═══════ 一、数据感知采集类传感器 ═══════
            [
                'name'                  => '智能超声波液位计（上游）',
                'code'                  => 'SEN-ULS-001',
                'type'                  => 'sensor',
                'reservoir_id'          => $UP,
                'status'                => 'active',
                'location'              => '上游库区水位测点',
                'manufacturer'          => null,
                'model'                 => null,
                'specs'                 => [
                    'range'        => '0-5m',
                    'output'       => '4-20mA',
                    'features'     => ['防爆', '防腐'],
                    'measurement'  => '上游水位',
                ],
                'communication_protocol' => '4-20mA',
                'heartbeat_interval'    => 10,
                'offline_threshold'     => 30,
                'tags'                  => ['水位', '模拟量', '超声波', '上游'],
            ],
            [
                'name'                  => '智能超声波液位计（下游）',
                'code'                  => 'SEN-ULS-002',
                'type'                  => 'sensor',
                'reservoir_id'          => $DOWN,
                'status'                => 'active',
                'location'              => '下游库区水位测点',
                'manufacturer'          => null,
                'model'                 => null,
                'specs'                 => [
                    'range'        => '0-5m',
                    'output'       => '4-20mA',
                    'features'     => ['防爆', '防腐'],
                    'measurement'  => '下游水位',
                ],
                'communication_protocol' => '4-20mA',
                'heartbeat_interval'    => 10,
                'offline_threshold'     => 30,
                'tags'                  => ['水位', '模拟量', '超声波', '下游'],
            ],
            [
                'name'                  => '超声波流量计',
                'code'                  => 'SEN-UFM-001',
                'type'                  => 'sensor',
                'reservoir_id'          => $DOWN,
                'status'                => 'active',
                'location'              => '模拟河道（闸门下游侧）',
                'manufacturer'          => null,
                'model'                 => 'DN15',
                'specs'                 => [
                    'specification' => 'DN15',
                    'interface'     => 'RS485',
                    'measurement'   => '过闸模拟流量',
                ],
                'communication_protocol' => 'Modbus RTU',
                'heartbeat_interval'    => 10,
                'offline_threshold'     => 30,
                'tags'                  => ['流量', 'RS485', '超声波'],
            ],

            // ═══════ 二、闸门动作执行设备 ═══════
            [
                'name'                  => '电动推杆',
                'code'                  => 'ACT-EP-001',
                'type'                  => 'actuator',
                'reservoir_id'          => $UP,
                'status'                => 'active',
                'location'              => '模拟闸门',
                'specs'                 => [
                    'stroke' => '100mm',
                    'thrust' => '100kg',
                    'drive'  => '电动',
                ],
                'heartbeat_interval'    => 30,
                'offline_threshold'     => 90,
                'tags'                  => ['闸门', '推杆', '开度调节'],
            ],
            [
                'name'                  => '透明亚克力闸门板',
                'code'                  => 'ACT-AGP-001',
                'type'                  => 'actuator',
                'reservoir_id'          => $UP,
                'status'                => 'active',
                'location'              => '模拟闸门',
                'specs'                 => [
                    'size'   => '30×30cm',
                    'material' => '亚克力',
                ],
                'heartbeat_interval'    => 60,
                'offline_threshold'     => 180,
                'tags'                  => ['闸门板', '亚克力'],
            ],
            [
                'name'                  => '透明亚克力隔板',
                'code'                  => 'ACT-AGP-002',
                'type'                  => 'actuator',
                'reservoir_id'          => $UP,
                'status'                => 'active',
                'location'              => '上下游隔断',
                'specs'                 => [
                    'size'   => '40×40cm',
                    'material' => '亚克力',
                ],
                'heartbeat_interval'    => 60,
                'offline_threshold'     => 180,
                'tags'                  => ['隔板', '亚克力'],
            ],

            // ═══════ 三、PLC 控制与信号转换硬件 ═══════
            [
                'name'                  => '西门子 S7-200 SMART SR20 PLC 控制器',
                'code'                  => 'PLC-S7-001',
                'type'                  => 'plc_controller',
                'reservoir_id'          => $UP,
                'status'                => 'active',
                'location'              => '控制柜',
                'manufacturer'          => '西门子',
                'model'                 => 'S7-200 SMART SR20',
                'specs'                 => [
                    'role' => '控制逻辑、下发闸门调度指令',
                ],
                'heartbeat_interval'    => 5,
                'offline_threshold'     => 15,
                'tags'                  => ['PLC', '西门子', '控制器'],
            ],
            [
                'name'                  => '艾莫迅 EM AE04 PLC 模拟量输入模块',
                'code'                  => 'PLC-AE04-001',
                'type'                  => 'plc_module',
                'reservoir_id'          => $UP,
                'status'                => 'active',
                'location'              => 'PLC 扩展槽',
                'manufacturer'          => '艾莫迅',
                'model'                 => 'EM AE04',
                'specs'                 => [
                    'channels' => 4,
                    'signal'   => '4-20mA',
                    'function' => '接入水位传感器信号',
                ],
                'heartbeat_interval'    => 10,
                'offline_threshold'     => 30,
                'tags'                  => ['PLC', '模拟量输入', '艾莫迅'],
            ],
            [
                'name'                  => '绿联 USB 转 RS485/RS232 串口转换器',
                'code'                  => 'COM-UG-001',
                'type'                  => 'converter',
                'reservoir_id'          => $UP,
                'status'                => 'active',
                'location'              => 'Jetson/电脑端',
                'manufacturer'          => '绿联',
                'specs'                 => [
                    'interfaces' => ['RS485', 'RS232'],
                    'function'   => 'Modbus 通信调试',
                ],
                'heartbeat_interval'    => 30,
                'offline_threshold'     => 90,
                'tags'                  => ['串口', 'RS485', 'RS232', '绿联'],
            ],

            // ═══════ 四、边缘 AI 计算设备 ═══════
            [
                'name'                  => 'NVIDIA Jetson Orin Nano 8GB',
                'code'                  => 'AI-JETSON-001',
                'type'                  => 'edge_gateway',
                'reservoir_id'          => $UP,
                'status'                => 'active',
                'location'              => '边缘计算节点',
                'manufacturer'          => 'NVIDIA',
                'model'                 => 'Orin Nano 8GB',
                'specs'                 => [
                    'memory'    => '8GB',
                    'algorithms' => ['DQN', 'LSTM'],
                    'function'  => '对接 PLC，本地智能推理调度',
                ],
                'heartbeat_interval'    => 5,
                'offline_threshold'     => 15,
                'tags'                  => ['Jetson', '边缘计算', 'AI', 'DQN', 'LSTM'],
                'ip_address'            => '192.168.1.100',
            ],

            // ═══════ 五、供电、电源转换设备 ═══════
            [
                'name'                  => '明纬 NDR-240-24 24V 导轨式开关电源',
                'code'                  => 'PWR-MW-001',
                'type'                  => 'power',
                'reservoir_id'          => $UP,
                'status'                => 'active',
                'location'              => '控制柜',
                'manufacturer'          => '明纬',
                'model'                 => 'NDR-240-24',
                'specs'                 => [
                    'input'     => 'AC220V',
                    'output'    => 'DC24V',
                    'form'      => '导轨式',
                    'function'  => '整机供电',
                ],
                'heartbeat_interval'    => 60,
                'offline_threshold'     => 180,
                'tags'                  => ['电源', '明纬', '24V', '导轨式'],
            ],
            [
                'name'                  => '24V 转 12V 降压模块',
                'code'                  => 'PWR-BUCK-001',
                'type'                  => 'power',
                'reservoir_id'          => $UP,
                'status'                => 'active',
                'location'              => '控制柜',
                'specs'                 => [
                    'input'    => 'DC24V',
                    'output'   => 'DC12V',
                    'function' => '为自吸水泵供电',
                ],
                'heartbeat_interval'    => 60,
                'offline_threshold'     => 180,
                'tags'                  => ['电源', '降压', '12V'],
            ],

            // ═══════ 六、水循环模拟配套设备 ═══════
            [
                'name'                  => '小型自吸循环水泵',
                'code'                  => 'WTR-PUMP-001',
                'type'                  => 'pump',
                'reservoir_id'          => $DOWN,
                'status'                => 'active',
                'location'              => '下游库区',
                'specs'                 => [
                    'voltage' => '12V',
                    'function' => '模拟水循环（下游抽水回上游）',
                ],
                'heartbeat_interval'    => 30,
                'offline_threshold'     => 90,
                'tags'                  => ['水泵', '自吸', '水循环'],
            ],
            [
                'name'                  => '折叠式帆布模拟蓄水池',
                'code'                  => 'WTR-TANK-001',
                'type'                  => 'water_tank',
                'reservoir_id'          => $UP,
                'status'                => 'active',
                'location'              => '模拟平台',
                'specs'                 => [
                    'size'     => '1.5m × 1m × 高 0.5m',
                    'material' => '帆布',
                    'function' => '分隔为上下游两个库区',
                ],
                'heartbeat_interval'    => 120,
                'offline_threshold'     => 300,
                'tags'                  => ['蓄水池', '帆布'],
            ],
            [
                'name'                  => 'DN15 不锈钢快速/宝塔接头',
                'code'                  => 'WTR-FIT-001',
                'type'                  => 'fitting',
                'reservoir_id'          => $UP,
                'status'                => 'active',
                'location'              => '模拟水路',
                'specs'                 => [
                    'specification' => 'DN15',
                    'material'      => '不锈钢',
                    'function'      => '水路拆装连接',
                ],
                'heartbeat_interval'    => 120,
                'offline_threshold'     => 300,
                'tags'                  => ['接头', '不锈钢', 'DN15'],
            ],
            [
                'name'                  => '透明硅胶软管',
                'code'                  => 'WTR-HOSE-001',
                'type'                  => 'fitting',
                'reservoir_id'          => $UP,
                'status'                => 'active',
                'location'              => '模拟水路',
                'specs'                 => [
                    'inner_diameter' => '16mm',
                    'outer_diameter' => '20mm',
                    'material'       => '透明硅胶',
                    'function'       => '连通水泵、流量计、蓄水池',
                ],
                'heartbeat_interval'    => 120,
                'offline_threshold'     => 300,
                'tags'                  => ['软管', '硅胶', '透明'],
            ],
        ];

        $equipment = [];

        // 按水库分组取第一个在线边缘节点，用于关联设备
        $edgeNodeMap = \App\Models\EdgeNode::where('status', 'online')
            ->select('id', 'reservoir_id')
            ->get()
            ->groupBy('reservoir_id')
            ->map(fn ($nodes) => $nodes->first()->id);

        foreach ($items as $item) {
            $specs = $item['specs'] ?? [];
            $tags  = $item['tags']  ?? [];
            $rid   = $item['reservoir_id'] ?? null;

            $equipment[] = [
                'name'                  => $item['name'],
                'code'                  => $item['code'],
                'type'                  => $item['type'],
                'reservoir_id'          => $rid,
                'status'                => $item['status'] ?? 'active',
                'location'              => $item['location'] ?? null,
                'manufacturer'          => $item['manufacturer'] ?? null,
                'model'                 => $item['model'] ?? null,
                'serial_number'         => null,
                'purchase_date'         => null,
                'warranty_expire'       => null,
                'specs'                 => json_encode($specs, JSON_UNESCAPED_UNICODE),
                'current_metrics'       => null,
                'health_score'          => null,
                'tags'                  => json_encode($tags, JSON_UNESCAPED_UNICODE),
                'edge_node_id'          => $rid ? ($edgeNodeMap[$rid] ?? null) : null,
                'plc_register'          => null,
                'communication_protocol'=> $item['communication_protocol'] ?? null,
                'heartbeat_interval'    => $item['heartbeat_interval'] ?? null,
                'offline_threshold'     => $item['offline_threshold'] ?? null,
                'firmware_version'      => null,
                'maintenance_count'     => 0,
                'last_maintenance_at'   => null,
                'next_maintenance_at'   => null,
                'total_runtime'         => 0,
                'ip_address'            => $item['ip_address'] ?? null,
                'port'                  => null,
                'last_online'           => null,
                'created_by'            => null,
                'updated_by'            => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ];
        }

        Equipment::insert($equipment);
    }
}
