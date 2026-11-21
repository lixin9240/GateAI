// 泄洪阀叶启闭 — 3D 场景与泄流计算共用
export const GATE_SILL_Y = 4.5
export const LINTEL_BOTTOM_Y = 15.5
export const GATE_LEAF_BASE_Y = 6
export const GATE_LEAF_HALF_HEIGHT = 5
export const GATE_LEAF_FULL_LIFT = 11

export interface GateLeafTransform {
  positionY: number
  scaleY: number
  retracted: boolean
}

export function gateLeafTransform(
  openRatio: number,
  baseY = GATE_LEAF_BASE_Y,
  baseScaleY = 1,
): GateLeafTransform {
  const r = Math.min(1, Math.max(0, openRatio))
  if (r >= 0.995) {
    return { positionY: baseY + GATE_LEAF_FULL_LIFT, scaleY: baseScaleY * 0.06, retracted: true }
  }
  return {
    positionY: baseY + r * GATE_LEAF_FULL_LIFT,
    scaleY: baseScaleY * Math.max(0.1, 1 - r * 0.92),
    retracted: false,
  }
}

export function gateLeafBottomY(openRatio: number, baseY = GATE_LEAF_BASE_Y, baseScaleY = 1): number {
  const t = gateLeafTransform(openRatio, baseY, baseScaleY)
  if (t.retracted) return LINTEL_BOTTOM_Y - 0.06
  return t.positionY - GATE_LEAF_HALF_HEIGHT * t.scaleY
}

export function applyGateLeafTransform(
  object: { position: { y: number }; scale: { y: number }; visible?: boolean },
  openRatio: number,
  baseY: number,
  baseScaleY: number,
) {
  const t = gateLeafTransform(openRatio, baseY, baseScaleY)
  object.position.y = t.positionY
  object.scale.y = t.scaleY
  // 全开时仍保持可见（缩成门楣细条），便于点选/描边，避免“孔空了却显示阀门”
  if ('visible' in object) object.visible = true
}
