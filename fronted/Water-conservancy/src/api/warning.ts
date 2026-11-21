// ============================================================
// 告警管理 — API 接口层（真实接口优先，失败回退 Mock）
// ============================================================
import http from './request'
import type { ApiResponse, PageResult } from '@/shared/types'
import type {
  AlarmRecord,
  AlarmFilterParams,
  AlarmExceedLog,
  AlarmStatsResult,
  AlarmPushMessage,
} from '@/types/alarm'
import type { PhysicsGuardSummary } from '@/types/dispatch'
import type { BackendPhysicsGuardRaw } from './physicsGuardAdapter'
import { mapBackendPhysicsGuardSummary } from './physicsGuardAdapter'
import { mockApi } from './mockStore'
import {
  mapBackendAlarm,
  mapBackendExceedLog,
  filterAlarmsByKeyword,
  toBackendAlarmQuery,
  type BackendAlarmItem,
  type BackendAlarmListData,
  type BackendExceedLogItem,
} from './alarmAdapter'

const V1_PREFIX = import.meta.env.VITE_API_V1_PREFIX ?? '/v1'
const ALARMS_BASE = `${V1_PREFIX}/alarms`

function unwrap<T>(res: { data: ApiResponse<T> }): ApiResponse<T> | null {
  if (res.data?.code === 0) return res.data
  return null
}

export async function getAlarmList(
  params: AlarmFilterParams,
): Promise<ApiResponse<PageResult<AlarmRecord> & { pendingCount: number }>> {
  try {
    const hasKeyword = Boolean(params.keyword?.trim())
    const fetchParams = hasKeyword
      ? { ...params, pageNum: 1, pageSize: 100 }
      : params
    const query = toBackendAlarmQuery(fetchParams)
    const [listRes, pendingRes] = await Promise.all([
      http.get<ApiResponse<BackendAlarmListData>>(ALARMS_BASE, { params: query }),
      http.get<ApiResponse<BackendAlarmListData>>(ALARMS_BASE, {
        params: { page: 1, page_size: 1, status: 'unhandled' },
      }),
    ])
    const body = unwrap(listRes)
    if (!body?.data) throw new Error('alarm list failed')
    const raw = body.data
    const pendingBody = unwrap(pendingRes)
    let list = (raw.list ?? []).map(mapBackendAlarm)
    let total = raw.total ?? list.length
    if (hasKeyword) {
      list = filterAlarmsByKeyword(list, params.keyword)
      total = list.length
      const start = (params.pageNum - 1) * params.pageSize
      list = list.slice(start, start + params.pageSize)
    }
    return {
      ...body,
      data: {
        list,
        total,
        pendingCount:
          raw.pending_count ?? raw.unhandled_count ?? pendingBody?.data?.total ?? 0,
      },
    }
  } catch {
    return mockApi.getAlarmList(params)
  }
}

export async function getAlarmDetail(id: number): Promise<ApiResponse<AlarmRecord>> {
  try {
    const res = await http.get<ApiResponse<BackendAlarmItem>>(`${ALARMS_BASE}/${id}`)
    const body = unwrap(res)
    if (!body?.data) throw new Error('alarm detail failed')
    return { ...body, data: mapBackendAlarm(body.data) }
  } catch {
    return mockApi.getAlarmDetail(id)
  }
}

export async function confirmAlarm(id: number): Promise<ApiResponse<null>> {
  try {
    const res = await http.put<ApiResponse<null>>(`${ALARMS_BASE}/${id}/acknowledge`)
    const body = unwrap(res)
    if (body) return body
    throw new Error('confirm failed')
  } catch {
    return mockApi.confirmAlarm(id)
  }
}

export async function handleAlarm(params: {
  id: number
  remark: string
}): Promise<ApiResponse<null>> {
  try {
    const res = await http.put<ApiResponse<null>>(`${ALARMS_BASE}/${params.id}/dispose`, {
      dispose_note: params.remark,
    })
    const body = unwrap(res)
    if (body) return body
    throw new Error('dispose failed')
  } catch {
    return mockApi.handleAlarm(params)
  }
}

export async function getAlarmExceedLogs(
  params: Record<string, unknown>,
): Promise<ApiResponse<PageResult<AlarmExceedLog>>> {
  try {
    const res = await http.get<ApiResponse<PageResult<BackendExceedLogItem>>>(
      `${ALARMS_BASE}/exceed-logs`,
      {
        params: {
          page: 1,
          page_size: 20,
          start_time: params.startTime,
          end_time: params.endTime,
        },
      },
    )
    const body = unwrap(res)
    if (!body?.data) throw new Error('exceed logs failed')
    return {
      ...body,
      data: {
        list: (body.data.list ?? []).map(mapBackendExceedLog),
        total: body.data.total ?? 0,
      },
    }
  } catch {
    return mockApi.getExceedLogs({ keyword: params.keyword as string | undefined })
  }
}

export async function getPendingAlarmCount(): Promise<ApiResponse<{ count: number }>> {
  try {
    const res = await http.get<ApiResponse<BackendAlarmListData>>(ALARMS_BASE, {
      params: { page: 1, page_size: 1, status: 'unhandled' },
    })
    const body = unwrap(res)
    if (body?.data) {
      return {
        code: 0,
        msg: 'ok',
        success: true,
        trace_id: body.trace_id,
        data: { count: body.data.total ?? 0 },
      }
    }
    throw new Error('pending count failed')
  } catch {
    const res = await mockApi.getAlarmStats()
    return { ...res, data: { count: res.data.pending } }
  }
}

export async function getAlarmStats(): Promise<ApiResponse<AlarmStatsResult>> {
  try {
    return await getAlarmStatsFromApi()
  } catch {
    return mockApi.getAlarmStats()
  }
}

async function getAlarmStatsFromApi(): Promise<ApiResponse<AlarmStatsResult>> {
  const res = await http.get<ApiResponse<BackendAlarmListData>>(ALARMS_BASE, {
    params: { page: 1, page_size: 1 },
  })
  const body = unwrap(res)
  if (!body?.data) throw new Error('stats failed')
  const pendingRes = await http.get<ApiResponse<BackendAlarmListData>>(ALARMS_BASE, {
    params: { page: 1, page_size: 1, status: 'unhandled' },
  })
  const pending = unwrap(pendingRes)?.data?.total ?? 0
  return {
    code: 0,
    msg: 'ok',
    success: true,
    trace_id: body.trace_id,
    data: {
      today: body.data.total ?? 0,
      pending,
      handled: Math.max(0, (body.data.total ?? 0) - pending),
      falseAlarm: 0,
      levelDistribution: { URGENT: 0, IMPORTANT: 0, NORMAL: 0 },
    },
  }
}

/** 轮询告警推送（WebSocket 未就绪时的兜底；后端 Apifox 无 poll 路径，避免反复 404） */
export async function pollAlarmPush(): Promise<ApiResponse<AlarmPushMessage | null>> {
  return mockApi.pollAlarmPush()
}

/**
 * 轮询真实后端，取最新一条未处理告警（用于全局新告警通知）。
 * 只走真实接口，后端不可用 / 无数据时返回 null（不 fallback mock）。
 */
export async function pollLatestUnhandledAlarm(): Promise<{
  alarm: AlarmRecord
  pendingCount: number
} | null> {
  try {
    const res = await http.get<ApiResponse<BackendAlarmListData>>(ALARMS_BASE, {
      params: { page: 1, page_size: 20, status: 'unhandled' },
    })
    const body = unwrap(res)
    const list = body?.data?.list
    if (!list?.length) return null
    // 后端排序未保证，前端按 id 降序取最新一条
    const latest = list.map(mapBackendAlarm).sort((a, b) => b.id - a.id)[0]
    return {
      alarm: latest,
      pendingCount:
        body.data.pending_count ?? body.data.unhandled_count ?? body.data.total ?? 0,
    }
  } catch {
    return null
  }
}

export async function getPhysicsGuardSummary(): Promise<ApiResponse<PhysicsGuardSummary>> {
  try {
    const res = await http.get<ApiResponse<BackendPhysicsGuardRaw>>(
      `${V1_PREFIX}/admin/physics-guard`,
      { params: { reservoir_id: 1 } },
    )
    const body = unwrap(res)
    if (body?.data) {
      return { ...body, data: mapBackendPhysicsGuardSummary(body.data) }
    }
    throw new Error('physics guard failed')
  } catch {
    return mockApi.getPhysicsGuardSummary()
  }
}
