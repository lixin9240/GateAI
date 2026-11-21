// ============================================================
// 阀门互锁规则 — 字典常量
// 按需求文档 5.8 / 8.4 节定义
// ============================================================

// ---------- 默认互锁规则 ----------
export const DEFAULT_INTERLOCK_RULES = [
  {
    code: 'spillway_intake_mutex',
    name: '泄洪-发电互斥',
    description: '溢洪道开度 > 80% 时，发电引水阀 ≤ 50%',
    trigger: '溢洪道 > 80%',
    action: '发电阀 ≤ 50%',
    priority: 1,
  },
  {
    code: 'downstream_impact_protect',
    name: '下游冲击保护',
    description: '任两阀门同时增开 > 30%，第三个阀门禁止同向动作',
    trigger: '两阀门同时增开 > 30%',
    action: '第三阀门禁止同向',
    priority: 2,
  },
  {
    code: 'symmetry_constraint',
    name: '对称性约束',
    description: '溢洪道与泄洪洞开度差 > 40%，强制调整至差值 ≤ 40%',
    trigger: '开度差 > 40%',
    action: '强制对齐 ≤ 40%',
    priority: 3,
  },
  {
    code: 'min_discharge_guarantee',
    name: '最小下泄保障',
    description: '三阀门总开度 < 5% 时禁止再关',
    trigger: '总开度 < 5%',
    action: '禁止再关',
    priority: 4,
  },
] as const

// ---------- 规则作用范围 ----------
export const RULE_SCOPE: Record<string, string> = {
  global: '全局默认',
  reservoir: '水库专属',
}

// ---------- 触发条件运算符 ----------
export const CONDITION_OPERATORS = [
  { label: '大于 (>)', value: 'gt' },
  { label: '小于 (<)', value: 'lt' },
  { label: '大于等于 (≥)', value: 'gte' },
  { label: '小于等于 (≤)', value: 'lte' },
  { label: '等于 (=)', value: 'eq' },
  { label: '变化率大于', value: 'rate_gt' },
] as const

// ---------- 条件字段选项 ----------
export const CONDITION_FIELDS = [
  { label: '溢洪道开度', value: 'spillway_opening' },
  { label: '泄洪洞开度', value: 'tunnel_opening' },
  { label: '发电阀开度', value: 'intake_opening' },
  { label: '总开度', value: 'total_opening' },
  { label: '上游水位', value: 'upstream_level' },
  { label: '下游水位', value: 'downstream_level' },
  { label: '入库流量', value: 'inflow_rate' },
] as const

// ---------- 约束方式 ----------
export const CONSTRAINT_TYPES = [
  { label: '强制限制 ≤', value: 'cap' },
  { label: '强制限制 ≥', value: 'floor' },
  { label: '禁止同向', value: 'block_same_direction' },
  { label: '禁止反向', value: 'block_opposite_direction' },
  { label: '强制对齐', value: 'align' },
] as const
