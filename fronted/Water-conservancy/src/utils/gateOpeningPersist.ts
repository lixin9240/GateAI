// 节点控制开度浏览器持久化（开/关即时生效后，刷新仍保留）
import { GATE_OPENING_STORAGE_KEY } from '@/constants/dispatch'

export interface GateOpeningOverride {
  opening?: number
  target: number
}

type OverrideMap = Record<number, GateOpeningOverride>

function clamp(n: number): number {
  return Math.min(100, Math.max(0, Number.isFinite(n) ? n : 0))
}

function hasFiniteNumber(v: unknown): v is number {
  return v != null && v !== '' && Number.isFinite(Number(v))
}

export function loadGateOpeningOverrides(): OverrideMap {
  try {
    const raw = localStorage.getItem(GATE_OPENING_STORAGE_KEY)
    if (!raw) return {}
    const parsed = JSON.parse(raw) as Record<string, Partial<GateOpeningOverride>>
    const out: OverrideMap = {}
    for (const [k, v] of Object.entries(parsed)) {
      const id = Number(k)
      if (!Number.isFinite(id) || !v) continue
      // 旧草稿可能只有 target：绝不能把 opening 当成 0 盖掉现场开度
      const target = hasFiniteNumber(v.target)
        ? clamp(Number(v.target))
        : hasFiniteNumber(v.opening)
          ? clamp(Number(v.opening))
          : null
      if (target == null) continue
      const entry: GateOpeningOverride = { target }
      if (hasFiniteNumber(v.opening)) entry.opening = clamp(Number(v.opening))
      out[id] = entry
    }
    return out
  } catch {
    return {}
  }
}

export function saveGateOpeningOverrides(
  gates: Array<{ id: number; currentOpening: number; targetOpening: number }>,
): void {
  const map: OverrideMap = {}
  for (const g of gates) {
    map[g.id] = {
      opening: clamp(g.currentOpening),
      target: clamp(g.targetOpening),
    }
  }
  localStorage.setItem(GATE_OPENING_STORAGE_KEY, JSON.stringify(map))
}

export function clearGateOpeningOverrides(): void {
  localStorage.removeItem(GATE_OPENING_STORAGE_KEY)
}

export function applyGateOpeningOverrides<
  T extends { id: number; opening: number; target_opening: number },
>(list: T[]): T[] {
  const overrides = loadGateOpeningOverrides()
  if (!Object.keys(overrides).length) return list
  return list.map((g) => {
    const o = overrides[g.id]
    if (!o) return g
    return {
      ...g,
      // 仅当本地明确存了 opening 才覆盖实际开度（开/关即时生效）
      opening: o.opening != null ? o.opening : g.opening,
      target_opening: o.target,
    }
  })
}
