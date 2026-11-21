// ============================================================
// 告警 API — 后端字段 ↔ 前端类型映射
// 后端：snake_case + urgent/unhandled；前端：camelCase + URGENT/pending
// ============================================================
import type {
  AlarmRecord,
  AlarmFilterParams,
  AlarmExceedLog,
  AlarmLevel,
  AlarmStatus,
  AlarmType,
  AlarmDeviceType,
} from '@/types/alarm'
import { ALARM_TYPE_MAP } from '@/constants/alarm'

/** 后端仅返回用户 ID 时的演示姓名映射 */
const ALARM_USER_NAMES: Record<number, string> = {
  1: '张调度',
  2: '李运维',
  3: '王巡检',
}

function resolveAlarmUserName(
  id: number | null | undefined,
  name: string | null | undefined,
): string | null {
  if (name?.trim()) return name.trim()
  if (id != null && ALARM_USER_NAMES[id]) return ALARM_USER_NAMES[id]
  return null
}

/** 后端列表项（GET /v1/alarms） */
export interface BackendAlarmItem {
  id: number
  alarm_no?: string
  reservoir_id?: number
  equipment_id?: number | null
  equipment_name?: string | null
  equipment?: { name?: string } | null
  reservoir?: { id?: number; name?: string } | null
  edge_node?: { name?: string } | null
  trace_id?: string | null
  updated_at?: string | null
  type: string
  level: string
  message: string
  metric_value?: number | string | null
  threshold_value?: number | string | null
  duration?: number | string | null
  duration_sec?: number | string | null
  status: string
  created_at: string
  exceed_start?: string | null
  acknowledged_at?: string | null
  acknowledged_by?: number | null
  acknowledged_by_name?: string | null
  disposed_at?: string | null
  disposed_by?: number | null
  disposed_by_name?: string | null
  dispose_note?: string | null
  confirmed_at?: string | null
  confirmed_by?: number | null
  confirmed_by_name?: string | null
  handled_at?: string | null
  handled_by?: number | null
  handled_by_name?: string | null
  snapshot?: {
    upstream_level?: number
    downstream_level?: number
    flow_rate?: number
    gate_opening?: number
    recorded_at?: string
  } | null
}

export interface BackendAlarmListData {
  list: BackendAlarmItem[]
  total: number
  page?: number
  page_size?: number
  pending_count?: number
  unhandled_count?: number
}

export interface BackendExceedLogItem {
  id: number
  equipment_name?: string
  point?: string
  metric?: string
  type?: string
  value?: number | string
  threshold_value?: number | string
  duration?: number | string
  duration_sec?: number | string
  created_at: string
}

const LEVEL_TO_BACKEND: Record<AlarmLevel, string> = {
  URGENT: 'urgent',
  IMPORTANT: 'important',
  NORMAL: 'normal',
}

const STATUS_TO_BACKEND: Record<AlarmStatus, string> = {
  pending: 'unhandled',
  confirmed: 'acknowledged',
  handled: 'disposed',
}

const TYPE_TO_BACKEND: Partial<Record<AlarmType, string>> = {
  HIGH_WATER: 'water_level',
  LOW_WATER: 'water_level',
  FLOW_SPIKE: 'flow',
  POWER: 'power',
  DEVICE_OFFLINE: 'equipment',
  EXEC_TIMEOUT: 'gate',
  EXEC_FAIL: 'gate',
  MODEL_HEALTH_DEGRADED: 'physics_violation',
}

const LEVEL_FROM_BACKEND: Record<string, AlarmLevel> = {
  urgent: 'URGENT',
  important: 'IMPORTANT',
  normal: 'NORMAL',
  URGENT: 'URGENT',
  IMPORTANT: 'IMPORTANT',
  NORMAL: 'NORMAL',
}

const STATUS_FROM_BACKEND: Record<string, AlarmStatus> = {
  unhandled: 'pending',
  acknowledged: 'confirmed',
  disposed: 'handled',
  pending: 'pending',
  confirmed: 'confirmed',
  handled: 'handled',
}

const TYPE_FROM_BACKEND: Record<string, AlarmType> = {
  water_level: 'HIGH_WATER',
  flow: 'FLOW_SPIKE',
  gate: 'EXEC_FAIL',
  power: 'POWER',
  equipment: 'DEVICE_OFFLINE',
  physics_violation: 'MODEL_HEALTH_DEGRADED',
  comm_loss: 'DEVICE_OFFLINE',
  HIGH_WATER: 'HIGH_WATER',
  LOW_WATER: 'LOW_WATER',
  FLOW_SPIKE: 'FLOW_SPIKE',
  POWER: 'POWER',
  DEVICE_OFFLINE: 'DEVICE_OFFLINE',
  EXEC_TIMEOUT: 'EXEC_TIMEOUT',
  EXEC_FAIL: 'EXEC_FAIL',
  MODEL_HEALTH_DEGRADED: 'MODEL_HEALTH_DEGRADED',
}

function parseNum(val: unknown, fallback = 0): number {
  if (val == null || val === '') return fallback
  const n = Number(val)
  return Number.isFinite(n) ? n : fallback
}

function inferDeviceType(type: string, equipmentName?: string): AlarmDeviceType {
  const t = type.toLowerCase()
  if (t === 'gate' || equipmentName?.includes('阀门')) return 'gate'
  if (t === 'water_level' || t === 'flow' || equipmentName?.includes('水文') || equipmentName?.includes('水位')) {
    return 'hydro'
  }
  return 'sensor'
}

export function mapBackendAlarm(item: BackendAlarmItem): AlarmRecord {
  const snap = item.snapshot
  const equipmentName =
    item.equipment_name ?? item.equipment?.name ?? undefined
  const reservoirName = item.reservoir?.name ?? null
  return {
    id: item.id,
    alarmNo: item.alarm_no,
    level: LEVEL_FROM_BACKEND[item.level] ?? 'NORMAL',
    type: TYPE_FROM_BACKEND[item.type] ?? 'DEVICE_OFFLINE',
    content: item.message,
    threshold: parseNum(item.threshold_value),
    currentValue: parseNum(item.metric_value),
    durationSec: parseNum(item.duration_sec ?? item.duration),
    status: STATUS_FROM_BACKEND[item.status] ?? 'pending',
    confirmedAt: item.confirmed_at ?? item.acknowledged_at ?? null,
    confirmedBy: item.confirmed_by ?? item.acknowledged_by ?? null,
    confirmedByName: resolveAlarmUserName(
      item.confirmed_by ?? item.acknowledged_by ?? null,
      item.confirmed_by_name ?? item.acknowledged_by_name ?? null,
    ),
    handledAt: item.handled_at ?? item.disposed_at ?? null,
    handledBy: item.handled_by ?? item.disposed_by ?? item.dispose_user_id ?? null,
    handledByName: resolveAlarmUserName(
      item.handled_by ?? item.disposed_by ?? item.dispose_user_id ?? null,
      item.handled_by_name
        ?? item.disposed_by_name
        ?? item.dispose_user_name
        ?? item.handler_name
        ?? null,
    ),
    remark: item.dispose_note ?? null,
    createdAt: item.created_at,
    exceedStart: item.exceed_start ?? null,
    updatedAt: item.updated_at ?? null,
    traceId: item.trace_id ?? null,
    reservoirId: item.reservoir_id ?? item.reservoir?.id ?? null,
    reservoirName,
    pointName:
      equipmentName ||
      reservoirName ||
      item.alarm_no ||
      `设备#${item.equipment_id ?? item.id}`,
    deviceType: inferDeviceType(item.type, equipmentName),
    snapshot: snap
      ? {
          upstreamLevel: parseNum(snap.upstream_level),
          downstreamLevel: parseNum(snap.downstream_level),
          flowRate: parseNum(snap.flow_rate),
          gateOpening: parseNum(snap.gate_opening),
          recordedAt: snap.recorded_at ?? item.created_at,
        }
      : null,
  }
}

export function mapBackendExceedLog(item: BackendExceedLogItem): AlarmExceedLog {
  return {
    id: item.id,
    point: item.point || item.equipment_name || '监测点',
    type: TYPE_FROM_BACKEND[item.type ?? item.metric ?? ''] ?? 'HIGH_WATER',
    value: parseNum(item.value),
    threshold: parseNum(item.threshold_value),
    durationSec: parseNum(item.duration_sec ?? item.duration),
    createdAt: item.created_at,
  }
}

export function filterAlarmsByKeyword(list: AlarmRecord[], keyword?: string): AlarmRecord[] {
  const kw = keyword?.trim()
  if (!kw) return list
  const lower = kw.toLowerCase()
  return list.filter((a) => matchAlarmKeyword(a, lower))
}

/** 仅匹配用户可见字段，避免 id 等内部字段误命中 */
function matchAlarmKeyword(alarm: AlarmRecord, kw: string): boolean {
  const fields = [
    alarm.content,
    alarm.pointName,
    alarm.alarmNo,
    alarm.reservoirName,
    ALARM_TYPE_MAP[alarm.type]?.label,
    String(alarm.currentValue),
    String(alarm.threshold),
  ]
    .filter((v) => v != null && v !== '')
    .map((v) => String(v).toLowerCase())

  const haystack = fields.join(' ')
  const tokens = kw.split(/\s+/).filter(Boolean)

  if (tokens.length > 1) {
    return tokens.every((t) => tokenMatches(haystack, t))
  }
  return tokenMatches(haystack, kw)
}

function tokenMatches(haystack: string, token: string): boolean {
  if (haystack.includes(token)) return true
  // 中文等 2 字及以上才启用字符顺序模糊匹配（如「高水」→「高水位」）
  if (token.length >= 2 && !/^\d+$/.test(token)) {
    return subsequenceMatch(haystack, token)
  }
  return false
}

function subsequenceMatch(text: string, pattern: string): boolean {
  let idx = 0
  for (const ch of pattern) {
    idx = text.indexOf(ch, idx)
    if (idx === -1) return false
    idx += 1
  }
  return true
}

/** 前端筛选参数 → 后端 Query */
export function toBackendAlarmQuery(params: AlarmFilterParams & { reservoir_id?: number }) {
  const query: Record<string, string | number> = {
    page: params.pageNum,
    page_size: params.pageSize,
  }
  if (params.reservoir_id) query.reservoir_id = params.reservoir_id
  if (params.level) query.level = LEVEL_TO_BACKEND[params.level]
  if (params.status) query.status = STATUS_TO_BACKEND[params.status]
  if (params.type && TYPE_TO_BACKEND[params.type]) query.type = TYPE_TO_BACKEND[params.type]!
  if (params.startTime) query.start_time = params.startTime
  if (params.endTime) query.end_time = params.endTime
  if (params.keyword?.trim()) query.keyword = params.keyword.trim()
  return query
}
