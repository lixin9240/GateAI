// ============================================================
// 物理防护配置 — 后端字段 ↔ 前端类型映射
// ============================================================
import type { PhysicsGuardConfig } from '@/types/gateai'
import type {
  PhysicsGuardSummary,
  PhysicsGuardHistoryItem,
} from '@/types/dispatch'

export interface BackendPhysicsGuardRaw {
  id: number
  reservoir_id: number
  config_version: string
  is_active?: number | boolean
  upstream_danger?: string | number
  upstream_emergency?: string | number
  upstream_warning?: string | number
  upstream_min?: string | number
  ideal_min?: string | number
  ideal_max?: string | number
  downstream_danger?: string | number
  downstream_max?: string | number
  downstream_min?: string | number
  eco_flow_min?: string | number
  reservoir_area?: string | number
  max_level_change_per_hour?: string | number
  shadow_lookahead_steps?: number
  shadow_danger_offset?: string | number
  deadband_percent?: string | number
  max_rate_per_hour?: string | number
  fusion_l3_confidence?: string | number
  fusion_l3_risk?: string | number
  fusion_l2_confidence?: string | number
  fusion_l2_risk?: string | number
  gate_max_discharge?: Array<string | number>
  description?: string | null
  updated_by?: number | null
  created_at?: string
  updated_at?: string
  updater?: { id: number; realname: string } | null
  reservoir_name?: string
}

function toNum(v: string | number | null | undefined, fallback = 0): number {
  if (v == null || v === '') return fallback
  const n = typeof v === 'string' ? parseFloat(v) : v
  return Number.isFinite(n) ? n : fallback
}

function toNumArray(v: unknown): number[] {
  if (!Array.isArray(v)) return []
  return v.map((item) => toNum(item))
}

function isActive(v: number | boolean | undefined): boolean {
  return v === 1 || v === true
}

export function mapBackendPhysicsGuardConfig(raw: BackendPhysicsGuardRaw): PhysicsGuardConfig {
  return {
    id: raw.id,
    reservoir_id: raw.reservoir_id,
    upstream_danger: toNum(raw.upstream_danger),
    upstream_emergency: toNum(raw.upstream_emergency),
    upstream_warning: toNum(raw.upstream_warning),
    upstream_min: toNum(raw.upstream_min),
    ideal_min: toNum(raw.ideal_min),
    ideal_max: toNum(raw.ideal_max),
    downstream_danger: toNum(raw.downstream_danger),
    downstream_max: toNum(raw.downstream_max),
    downstream_min: toNum(raw.downstream_min),
    eco_flow_min: toNum(raw.eco_flow_min),
    max_level_change_per_hour: toNum(raw.max_level_change_per_hour),
    shadow_danger_offset: toNum(raw.shadow_danger_offset),
    deadband_percent: toNum(raw.deadband_percent),
    max_rate_per_hour: toNum(raw.max_rate_per_hour),
    fusion_l3_confidence: toNum(raw.fusion_l3_confidence),
    fusion_l3_risk: toNum(raw.fusion_l3_risk),
    fusion_l2_confidence: toNum(raw.fusion_l2_confidence),
    fusion_l2_risk: toNum(raw.fusion_l2_risk),
    gate_max_discharge: toNumArray(raw.gate_max_discharge),
    reservoir_area: raw.reservoir_area != null ? toNum(raw.reservoir_area) : undefined,
    shadow_lookahead_steps: raw.shadow_lookahead_steps,
    description: raw.description ?? undefined,
  }
}

export function mapBackendPhysicsGuardSummary(raw: BackendPhysicsGuardRaw): PhysicsGuardSummary {
  return {
    reservoir_id: raw.reservoir_id,
    reservoir_name: raw.reservoir_name ?? `水库 #${raw.reservoir_id}`,
    config_version: raw.config_version,
    is_active: isActive(raw.is_active),
    upstream_emergency: toNum(raw.upstream_emergency),
    upstream_danger: toNum(raw.upstream_danger),
    upstream_warning: toNum(raw.upstream_warning),
    fusion_l3_confidence: toNum(raw.fusion_l3_confidence),
    fusion_l3_risk: toNum(raw.fusion_l3_risk),
    last_sync_at: raw.updated_at ?? raw.created_at ?? null,
    sync_status: isActive(raw.is_active) ? 'synced' : 'stale',
  }
}

export function mapBackendPhysicsGuardHistoryItem(raw: BackendPhysicsGuardRaw): PhysicsGuardHistoryItem {
  return {
    id: raw.id,
    config_version: raw.config_version,
    changed_at: raw.updated_at ?? raw.created_at ?? '',
    changed_by_name: raw.updater?.realname ?? '—',
    description: raw.description ?? '',
    is_active: isActive(raw.is_active),
    changes: [],
  }
}

/** gateai 类型含 reservoir_id */
export function mapBackendPhysicsGuardHistoryItemGateai(
  raw: BackendPhysicsGuardRaw,
): import('@/types/gateai').PhysicsGuardHistoryItem {
  return {
    ...mapBackendPhysicsGuardHistoryItem(raw),
    reservoir_id: raw.reservoir_id,
  }
}

export function mapBackendPhysicsGuardHistory(
  list: BackendPhysicsGuardRaw[],
): PhysicsGuardHistoryItem[] {
  return list.map(mapBackendPhysicsGuardHistoryItem)
}

export function mapBackendPhysicsGuardHistoryGateai(
  list: BackendPhysicsGuardRaw[],
): import('@/types/gateai').PhysicsGuardHistoryItem[] {
  return list.map(mapBackendPhysicsGuardHistoryItemGateai)
}

/** PUT 请求体：数值字段保持 number，description 单独传 */
export function toBackendPhysicsGuardPayload(
  config: PhysicsGuardConfig,
  meta?: { description?: string },
): Record<string, unknown> {
  return {
    reservoir_id: config.reservoir_id,
    upstream_danger: config.upstream_danger,
    upstream_emergency: config.upstream_emergency,
    upstream_warning: config.upstream_warning,
    upstream_min: config.upstream_min,
    ideal_min: config.ideal_min,
    ideal_max: config.ideal_max,
    downstream_danger: config.downstream_danger,
    downstream_max: config.downstream_max,
    downstream_min: config.downstream_min,
    eco_flow_min: config.eco_flow_min,
    reservoir_area: config.reservoir_area,
    max_level_change_per_hour: config.max_level_change_per_hour,
    shadow_lookahead_steps: config.shadow_lookahead_steps,
    shadow_danger_offset: config.shadow_danger_offset,
    deadband_percent: config.deadband_percent,
    max_rate_per_hour: config.max_rate_per_hour,
    fusion_l3_confidence: config.fusion_l3_confidence,
    fusion_l3_risk: config.fusion_l3_risk,
    fusion_l2_confidence: config.fusion_l2_confidence,
    fusion_l2_risk: config.fusion_l2_risk,
    gate_max_discharge: config.gate_max_discharge,
    ...(meta?.description ? { description: meta.description } : {}),
  }
}
