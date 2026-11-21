// 数字孪生 — 阀墩 / 阀叶 / 泄流视觉主题
import * as THREE from 'three'

export const PIER_COLOR = 0x2a323c
export const DAM_BODY_COLOR = 0x343d48
export const GATE_FRAME_COLOR = 0x3d4652

/** 阀墩坝体：哑光深炭灰 */
export const PIER_MAT = {
  color: PIER_COLOR,
  metalness: 0.22,
  roughness: 0.86,
  emissive: 0x080a0e,
  emissiveIntensity: 0.02,
}

export const DAM_BODY_MAT = {
  color: DAM_BODY_COLOR,
  metalness: 0.12,
  roughness: 0.9,
  emissive: 0x0a0c10,
  emissiveIntensity: 0.015,
}

export interface GateLeafVisual {
  color: number
  emissive: number
  emissiveIntensity: number
  edgeOpacity: number
  edgeColor: number
}

/**
 * 阀叶颜色：关=钢灰 → 开=略亮钢灰（不使用绿色）
 * 选中态由蓝色描边单独表达，避免工况色与选中色打架
 */
export function gateLeafVisualForOpening(openRatio: number): GateLeafVisual {
  const r = Math.min(1, Math.max(0, openRatio))

  if (r < 0.08) {
    return {
      color: 0x3f4856,
      emissive: 0x000000,
      emissiveIntensity: 0,
      edgeOpacity: 0.12,
      edgeColor: 0x5a6575,
    }
  }

  // 全行程保持钢色体系，开度越大略提亮，不做绿/琥珀色跳变
  const t = (r - 0.08) / 0.92
  return {
    color: lerpHex(0x3f4856, 0x6a7688, t),
    emissive: 0x10141a,
    emissiveIntensity: t * 0.1,
    edgeOpacity: 0.14 + t * 0.2,
    edgeColor: lerpHex(0x5a6575, 0x8a96a8, t),
  }
}

function lerpHex(a: number, b: number, t: number) {
  const ca = new THREE.Color(a)
  const cb = new THREE.Color(b)
  return ca.lerp(cb, Math.min(1, Math.max(0, t))).getHex()
}
