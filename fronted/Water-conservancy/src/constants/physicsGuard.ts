// ============================================================
// 物理防护配置 — 字典常量
// 按需求文档 5.7.2 / 8.4 节定义
// ============================================================

// ---------- 8 个配置分组 ----------
export const PHYSICS_GUARD_SECTIONS = [
  {
    key: 'upstream',
    label: '上游水位阈值',
    fields: [
      { key: 'upstream_danger', label: '危险水位 (m)', defaultValue: 190, unit: 'm' },
      { key: 'upstream_emergency', label: '紧急水位 (m)', defaultValue: 193, unit: 'm' },
      { key: 'upstream_warning', label: '预警水位 (m)', defaultValue: 188, unit: 'm' },
      { key: 'upstream_min', label: '死水位保护线 (m)', defaultValue: 167, unit: 'm' },
      { key: 'ideal_min', label: '理想区间下限 (m)', defaultValue: 178, unit: 'm' },
      { key: 'ideal_max', label: '理想区间上限 (m)', defaultValue: 188, unit: 'm' },
    ],
  },
  {
    key: 'downstream',
    label: '下游水位阈值',
    fields: [
      { key: 'downstream_danger', label: '危险水位 (m)', defaultValue: 128, unit: 'm' },
      { key: 'downstream_max', label: '最大水位 (m)', defaultValue: 130, unit: 'm' },
      { key: 'downstream_min', label: '最小水位 (m)', defaultValue: 115, unit: 'm' },
    ],
  },
  {
    key: 'eco',
    label: '生态流量',
    fields: [{ key: 'eco_flow_min', label: '最小生态流量 (m³/s)', defaultValue: 20, unit: 'm³/s' }],
  },
  {
    key: 'physics',
    label: '物理参数',
    fields: [
      { key: 'reservoir_area', label: '水库水面面积 (m²)', defaultValue: 15_000_000, unit: 'm²' },
      {
        key: 'max_level_change_per_hour',
        label: '水位最大变化率 (m/h)',
        defaultValue: 2.0,
        unit: 'm/h',
      },
    ],
  },
  {
    key: 'shadow',
    label: '影子水位模型',
    fields: [
      { key: 'shadow_lookahead_steps', label: '前瞻步数', defaultValue: 3, unit: '步' },
      { key: 'shadow_danger_offset', label: '危险线偏移量 (m)', defaultValue: 3.0, unit: 'm' },
    ],
  },
  {
    key: 'smoother',
    label: '指令平滑',
    fields: [
      { key: 'deadband_percent', label: '死区百分比 (%)', defaultValue: 2, unit: '%' },
      { key: 'max_rate_per_hour', label: '最大变化率 (%/h)', defaultValue: 10, unit: '%/h' },
    ],
  },
  {
    key: 'fusion',
    label: '熔断阈值',
    fields: [
      { key: 'fusion_l3_confidence', label: 'L3 置信度阈值', defaultValue: 0.7, unit: '' },
      { key: 'fusion_l3_risk', label: 'L3 风险概率阈值', defaultValue: 0.3, unit: '' },
      { key: 'fusion_l2_confidence', label: 'L2 置信度阈值', defaultValue: 0.5, unit: '' },
      { key: 'fusion_l2_risk', label: 'L2 风险概率阈值', defaultValue: 0.1, unit: '' },
    ],
  },
  {
    key: 'gate',
    label: '阀门参数',
    fields: [
      {
        key: 'gate_max_discharge_0',
        label: '溢洪道最大泄流量 (m³/s)',
        defaultValue: 300,
        unit: 'm³/s',
      },
      {
        key: 'gate_max_discharge_1',
        label: '泄洪洞最大泄流量 (m³/s)',
        defaultValue: 200,
        unit: 'm³/s',
      },
      {
        key: 'gate_max_discharge_2',
        label: '发电阀最大泄流量 (m³/s)',
        defaultValue: 250,
        unit: 'm³/s',
      },
    ],
  },
] as const

// ---------- 配置同步状态 ----------
export const SYNC_STATUS: Record<string, { label: string; color: string }> = {
  synced: { label: '正常', color: '#67C23A' },
  pending: { label: '待同步', color: '#E6A23C' },
  failed: { label: '失败', color: '#F56C6C' },
} as const
