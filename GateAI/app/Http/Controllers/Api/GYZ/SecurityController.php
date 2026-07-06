<?php

namespace App\Http\Controllers\Api\GYZ;

use App\Http\Controllers\Controller;
use App\Support\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    // 总数据池
    private array $cameras = [
        ['id'=>1, 'name'=>'大坝上游全景',   'location'=>'坝顶左岸',   'ip'=>'192.168.10.11', 'status'=>'online',  'resolution'=>'4K',   'fps'=>25, 'last_frame'=>'2026-07-06 15:30:00'],
        ['id'=>2, 'name'=>'大坝下游全景',   'location'=>'坝顶右岸',   'ip'=>'192.168.10.12', 'status'=>'online',  'resolution'=>'4K',   'fps'=>25, 'last_frame'=>'2026-07-06 15:30:01'],
        ['id'=>3, 'name'=>'溢洪道监控',     'location'=>'溢洪道闸室', 'ip'=>'192.168.10.13', 'status'=>'online',  'resolution'=>'1080P', 'fps'=>15, 'last_frame'=>'2026-07-06 15:29:58'],
        ['id'=>4, 'name'=>'发电厂房入口',   'location'=>'厂房大门',   'ip'=>'192.168.10.14', 'status'=>'online',  'resolution'=>'1080P', 'fps'=>15, 'last_frame'=>'2026-07-06 15:30:00'],
        ['id'=>5, 'name'=>'开关站区域',     'location'=>'GIS室',     'ip'=>'192.168.10.15', 'status'=>'offline', 'resolution'=>'1080P', 'fps'=>15, 'last_frame'=>'2026-07-06 14:55:00'],
        ['id'=>6, 'name'=>'尾水渠监控',     'location'=>'尾水出口',   'ip'=>'192.168.10.16', 'status'=>'online',  'resolution'=>'1080P', 'fps'=>15, 'last_frame'=>'2026-07-06 15:29:59'],
    ];
    private array $doors = [
        ['id'=>1, 'name'=>'主厂房大门',       'location'=>'厂房1F', 'type'=>'双开电动门', 'status'=>'locked',   'last_access'=>'2026-07-06 14:30:00', 'last_user'=>'王站长'],
        ['id'=>2, 'name'=>'中控室门禁',       'location'=>'中控室',  'type'=>'磁力锁',    'status'=>'locked',   'last_access'=>'2026-07-06 15:00:00', 'last_user'=>'李运维'],
        ['id'=>3, 'name'=>'GIS室门禁',        'location'=>'GIS室',   'type'=>'磁力锁',    'status'=>'locked',   'last_access'=>'2026-07-06 10:00:00', 'last_user'=>'张调度'],
        ['id'=>4, 'name'=>'大坝巡检通道门',   'location'=>'坝顶',    'type'=>'防火门',    'status'=>'unlocked', 'last_access'=>'2026-07-06 15:15:00', 'last_user'=>'巡检员'],
    ];
    private array $patrols = [
        ['id'=>1, 'route'=>'大坝廊道巡检路线A', 'patrol_user'=>'巡检员-刘工', 'start_time'=>'2026-07-06 09:00:00', 'end_time'=>'2026-07-06 10:30:00', 'status'=>'completed', 'checkpoints'=>12, 'abnormal'=>0, 'summary'=>'设备运行正常，无异常'],
        ['id'=>2, 'route'=>'厂房巡检路线B',     'patrol_user'=>'巡检员-陈工', 'start_time'=>'2026-07-06 14:00:00', 'end_time'=>'2026-07-06 15:20:00', 'status'=>'completed', 'checkpoints'=>8,  'abnormal'=>1, 'summary'=>'3#机组油位偏低，已上报运维'],
        ['id'=>3, 'route'=>'溢洪道巡检路线C',   'patrol_user'=>'巡检员-刘工', 'start_time'=>'2026-07-06 16:00:00', 'end_time'=>null,                     'status'=>'in_progress', 'checkpoints'=>5, 'abnormal'=>0, 'summary'=>'巡检进行中...'],
    ];
    private array $alarms = [
        ['id'=>1, 'alarm_no'=>'SA-20260706-001', 'type'=>'unauthorized_entry', 'level'=>'urgent',    'message'=>'开关站区域红外探测触发',     'location'=>'GIS室南侧围栏', 'status'=>'unhandled',    'trigger_time'=>'2026-07-06 14:52:00', 'acknowledge_time'=>null],
        ['id'=>2, 'alarm_no'=>'SA-20260706-002', 'type'=>'camera_offline',      'level'=>'important', 'message'=>'开关站摄像头离线超30分钟',   'location'=>'GIS室',        'status'=>'unhandled',    'trigger_time'=>'2026-07-06 14:55:00', 'acknowledge_time'=>null],
        ['id'=>3, 'alarm_no'=>'SA-20260706-003', 'type'=>'door_forced',         'level'=>'warning',   'message'=>'巡检通道门未按时关闭',       'location'=>'坝顶',         'status'=>'acknowledged', 'trigger_time'=>'2026-07-06 15:16:00', 'acknowledge_time'=>'2026-07-06 15:18:00'],
    ];

    /**
     * GET /api/v1/security/cameras
     * Query: status (online/offline), resolution (4K/1080P)
     */
    public function cameras(Request $request): JsonResponse
    {
        $list = $this->cameras;
        if ($s = $request->query('status'))   $list = array_values(array_filter($list, fn($c) => $c['status'] === $s));
        if ($r = $request->query('resolution')) $list = array_values(array_filter($list, fn($c) => $c['resolution'] === $r));

        return Result::success('获取成功', [
            'total'  => count($list),
            'online' => count(array_filter($list, fn($c) => $c['status'] === 'online')),
            'list'   => $list,
        ]);
    }

    /**
     * GET /api/v1/security/doors
     * Query: status (locked/unlocked)
     */
    public function doors(Request $request): JsonResponse
    {
        $list = $this->doors;
        if ($s = $request->query('status')) $list = array_values(array_filter($list, fn($d) => $d['status'] === $s));

        return Result::success('获取成功', [
            'total'  => count($list),
            'locked' => count(array_filter($list, fn($d) => $d['status'] === 'locked')),
            'list'   => $list,
        ]);
    }

    /**
     * GET /api/v1/security/patrols
     * Query: status (completed/in_progress), keyword (路线/人员搜索)
     */
    public function patrols(Request $request): JsonResponse
    {
        $list = $this->patrols;
        if ($s = $request->query('status'))  $list = array_values(array_filter($list, fn($p) => $p['status'] === $s));
        if ($k = $request->query('keyword')) $list = array_values(array_filter($list, fn($p) => str_contains($p['route'].$p['patrol_user'], $k)));

        return Result::success('获取成功', [
            'total' => count($list),
            'list'  => $list,
        ]);
    }

    /**
     * GET /api/v1/security/alarms
     * Query: status (unhandled/acknowledged), level (urgent/important/warning)
     */
    public function alarms(Request $request): JsonResponse
    {
        $list = $this->alarms;
        if ($s = $request->query('status')) $list = array_values(array_filter($list, fn($a) => $a['status'] === $s));
        if ($l = $request->query('level'))  $list = array_values(array_filter($list, fn($a) => $a['level'] === $l));

        return Result::success('获取成功', [
            'total'     => count($list),
            'unhandled' => count(array_filter($list, fn($a) => $a['status'] === 'unhandled')),
            'list'      => $list,
        ]);
    }
}
