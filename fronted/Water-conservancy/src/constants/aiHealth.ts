// ============================================================
// AI 模型健康度 — 字典常量
// 按需求文档 5.6.1 / 8.4 节定义
// ============================================================

// ---------- 健康等级 ----------
export const HEALTH_GRADE: Record<
  string,
  { label: string; color: string; scoreRange: string; level: string }
> = {
  S: { label: 'S级·完全自主', color: '#00C853', scoreRange: '≥0.85', level: 'L3_AUTO' },
  A: { label: 'A级·自主为主', color: '#2196F3', scoreRange: '≥0.70', level: 'L3_AUTO' },
  B: { label: 'B级·建议模式', color: '#FFC107', scoreRange: '≥0.55', level: 'L2_SUGGEST' },
  C: { label: 'C级·强制人工', color: '#FF9800', scoreRange: '≥0.40', level: 'L1_MANUAL' },
  D: { label: 'D级·自动冻结', color: '#F44336', scoreRange: '<0.40', level: 'FROZEN' },
} as const

export const HEALTH_GRADE_OPTIONS = Object.entries(HEALTH_GRADE).map(([key, val]) => ({
  label: val.label,
  value: key,
  color: val.color,
}))

// ---------- 评估维度 ----------
export const EVAL_DIMENSIONS: Record<string, { label: string; weight: number; color: string }> = {
  prediction: { label: '预测准确性', weight: 0.4, color: '#5470C6' },
  decision: { label: '决策可靠性', weight: 0.35, color: '#91CC75' },
  compliance: { label: '物理合规性', weight: 0.25, color: '#FAC858' },
  safety_coverage: { label: '安全覆盖率', weight: 0.15, color: '#EE6666' },
  decision_auto_rate: { label: '决策自主率', weight: 0.15, color: '#73C0DE' },
} as const

// ---------- 雷达图维度（版本对比用，5维）----------
export const RADAR_DIMENSIONS = [
  { key: 'prediction', label: '预测准确性' },
  { key: 'decision', label: '决策可靠性' },
  { key: 'compliance', label: '物理合规性' },
  { key: 'safety_coverage', label: '安全覆盖率' },
  { key: 'decision_auto_rate', label: '决策自主率' },
] as const

// ---------- 自动刷新间隔（毫秒）----------
export const AI_HEALTH_REFRESH_INTERVAL = 5 * 60 * 1000 // 5 分钟
