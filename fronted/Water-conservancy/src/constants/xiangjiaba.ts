// ============================================================
// 向家坝水电站 — 真实水文特征值（公开资料）
// ============================================================
import { GATE_SILL_Y } from '@/utils/gateKinematics'

/** 3D 场景 — 上游水面映射区间（阀口中上部，仍低于门楣/坝顶） */
export const UPSTREAM_SCENE_Y_MIN = GATE_SILL_Y + 1.6
export const UPSTREAM_SCENE_Y_MAX = GATE_SILL_Y + 9.2

/** 向家坝水电站关键高程与流量特征（单位：m / m³/s） */
export const XIANGJIABA_HYDRO = {
  /** 电站名称 */
  name: '向家坝水电站',
  /** 坝顶高程 */
  crestElevation: 384,
  /** 地理区位（坝址位于川滇交界，主体在四川省宜宾市境内） */
  locationLabel: '四川省宜宾市・向家坝水电站',
  longitude: '104°23′',
  latitude: '28°38′',
  /** 正常蓄水位 */
  normalPoolLevel: 380,
  /** 主汛期汛限水位（5–9 月） */
  floodLimitLevel: 380,
  /** 非主汛期汛限水位 */
  floodLimitLevelOffSeason: 375,
  /** 死水位 */
  deadLevel: 374,
  /** 设计洪水位 */
  designFloodLevel: 382.31,
  /** 预警水位（调度系统阈值） */
  warningLevel: 381.5,
  /** 下游尾水位（常年均值参考） */
  downstreamNormalLevel: 278.42,
  /** 额定入库流量参考 */
  normalInflow: 1850,
  /** 装机所在河流 */
  river: '金沙江',
} as const

/** 上游水位上限 — 严格低于坝顶，不漫坝 */
export function clampUpstreamLevel(level: number): number {
  const max = XIANGJIABA_HYDRO.crestElevation - 0.25
  const min = XIANGJIABA_HYDRO.deadLevel
  return Math.max(min, Math.min(max, level))
}

/** 将实际上游水位 (m) 映射到 3D 场景水面高度（对齐阀孔正面，不贴坝顶） */
export function upstreamLevelToSceneY(level: number): number {
  const clamped = clampUpstreamLevel(level)
  const { deadLevel, crestElevation } = XIANGJIABA_HYDRO
  const visualMax = crestElevation - 0.35
  const t = Math.max(0, Math.min(1, (clamped - deadLevel) / (visualMax - deadLevel)))
  return UPSTREAM_SCENE_Y_MIN + t * (UPSTREAM_SCENE_Y_MAX - UPSTREAM_SCENE_Y_MIN)
}

/** 水位相对特征值的差值描述 */
export function getLevelStatus(level: number): { label: string; color: string } {
  const h = XIANGJIABA_HYDRO
  if (level >= h.warningLevel) return { label: '超预警', color: '#ff4757' }
  if (level >= h.floodLimitLevel) return { label: '达汛限', color: '#ffa502' }
  if (level >= h.normalPoolLevel - 0.3) return { label: '正常蓄水', color: '#22c55e' }
  return { label: '正常运行', color: '#22c55e' }
}

/** 水位在特征标尺上的百分比位置 (0–100) */
export function levelGaugePercent(level: number): number {
  const { deadLevel, crestElevation } = XIANGJIABA_HYDRO
  return Math.max(0, Math.min(100, ((level - deadLevel) / (crestElevation - deadLevel)) * 100))
}
