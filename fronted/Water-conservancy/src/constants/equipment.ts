// ============================================================
// 设备管理 — 字典常量
// 按需求文档 4.2 / 8.4 节定义
// ============================================================
import type { DictMap } from '@/shared/types'

// ---------- 设备类型（7 类） ----------
export const DEVICE_TYPE: DictMap = {
  sensor: { label: '传感器', value: 'sensor', color: '#409EFF' },
  plc: { label: 'PLC控制器', value: 'plc', color: '#E6A23C' },
  gateway: { label: '边缘网关', value: 'gateway', color: '#67C23A' },
  actuator: { label: '执行器', value: 'actuator', color: '#F56C6C' },
  power: { label: '电源', value: 'power', color: '#909399' },
  pump: { label: '水泵', value: 'pump', color: '#909399' },
  accessory: { label: '配件', value: 'accessory', color: '#909399' },
}

/** 设备类型筛选下拉选项 */
export const DEVICE_TYPE_OPTIONS = Object.values(DEVICE_TYPE).map((d) => ({
  label: d.label,
  value: d.value,
}))

// ---------- 设备状态 ----------
export const DEVICE_STATUS: DictMap = {
  online: { label: '在线', value: 'online', color: '#67C23A', icon: 'CircleCheckFilled' },
  offline: { label: '离线', value: 'offline', color: '#909399', icon: 'RemoveFilled' },
  active: { label: '在线', value: 'active', color: '#67C23A', icon: 'CircleCheckFilled' },
  inactive: { label: '离线', value: 'inactive', color: '#909399', icon: 'RemoveFilled' },
  fault: { label: '故障', value: 'fault', color: '#F56C6C', icon: 'CircleCloseFilled' },
  maintenance: { label: '维护中', value: 'maintenance', color: '#E6A23C', icon: 'WarningFilled' },
}

/** 下拉选项中只显示后端实际使用的规范值 */
const CANONICAL_STATUS_KEYS = new Set(['online', 'offline', 'fault', 'maintenance'])

/** 设备状态筛选下拉选项（去重） */
export const DEVICE_STATUS_OPTIONS = Object.values(DEVICE_STATUS)
  .filter((d) => CANONICAL_STATUS_KEYS.has(d.value as string))
  .map((d) => ({
    label: d.label,
    value: d.value,
  }))

// ---------- 设备分组 ----------
export const DEVICE_GROUP: DictMap = {
  water_level: { label: '水位监测组', value: 'water_level', color: '#409EFF' },
  flow: { label: '流量监测组', value: 'flow', color: '#67C23A' },
  control: { label: '控制执行组', value: 'control', color: '#E6A23C' },
  edge_compute: { label: '边缘计算组', value: 'edge_compute', color: '#9C27B0' },
}

/** 设备分组筛选下拉选项 */
export const DEVICE_GROUP_OPTIONS = Object.values(DEVICE_GROUP).map((d) => ({
  label: d.label,
  value: d.value,
}))

// ---------- 故障类型 ----------
export const FAULT_TYPE: DictMap = {
  comm_loss: { label: '通信中断', value: 'comm_loss', color: '#F56C6C' },
  reading_abnormal: { label: '读数异常', value: 'reading_abnormal', color: '#E6A23C' },
  hardware_damage: { label: '硬件损坏', value: 'hardware_damage', color: '#F56C6C' },
  power_failure: { label: '电源故障', value: 'power_failure', color: '#909399' },
  other: { label: '其他', value: 'other', color: '#909399' },
}

// ---------- 维护操作类型 ----------
export const MAINTENANCE_TYPE: DictMap = {
  param_change: { label: '参数修改', value: 'param_change' },
  restart: { label: '重启', value: 'restart' },
  firmware_upgrade: { label: '固件升级', value: 'firmware_upgrade' },
  regular_check: { label: '定期检修', value: 'regular_check' },
  replace_part: { label: '更换配件', value: 'replace_part' },
}

// ---------- 14 种硬件设备名称映射 ----------
export const HARDWARE_MODEL_MAP: Record<string, string> = {
  level_sensor_upstream: '超声波液位计（上游）',
  level_sensor_downstream: '超声波液位计（下游）',
  flow_sensor: '超声波流量计',
  actuator: '电动推杆（阀门驱动）',
  plc_s7200: 'PLC S7-200 SMART SR20',
  plc_analog: 'PLC 模拟量输入模块 EM AE04',
  edge_gateway: 'Jetson Orin Nano + 触摸屏',
  power_24v: '24V 导轨式开关电源',
  water_pump: '小型自吸循环水泵（12V）',
  usb_rs485: 'USB转RS485转换器',
  basin: '折叠式模拟蓄水池',
  step_down: '24V转12V降压模块',
  quick_connector: 'DN15不锈钢快速接头',
  silicone_hose: '透明硅胶软管',
  gate_board: '透明亚克力阀门板',
}

// ---------- 心跳配置 ----------
export const HEARTBEAT_INTERVAL = 5 // 秒
export const HEARTBEAT_TIMEOUT = 30 // 秒（超时判定离线）

// ---------- 兼容旧代码的别名（逐步迁移后移除）----------
export const EQUIPMENT_TYPE = DEVICE_TYPE
export const EQUIPMENT_TYPE_OPTIONS = DEVICE_TYPE_OPTIONS
export const EQUIPMENT_STATUS = DEVICE_STATUS
export const EQUIPMENT_STATUS_OPTIONS = DEVICE_STATUS_OPTIONS
