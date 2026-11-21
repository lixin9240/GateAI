// ============================================================
// 全局急停 — API 接口层（真实接口优先，失败回退 Mock）
// ============================================================
import type { ApiResponse } from '@/shared/types'
import { emergencyStop } from './dispatch'
import { mockApi } from './mockStore'

const DEFAULT_RESERVOIR_ID = 1

/** 全局急停 — 停止调度执行、暂停仿真、阀门开度归零 */
export async function globalEmergencyStop(): Promise<
  ApiResponse<{ stop_log_id: number; command_id: string }>
> {
  try {
    const res = await emergencyStop({
      reservoir_id: DEFAULT_RESERVOIR_ID,
      stop_reason: '人工触发急停',
    })
    if (res.data?.code === 0) return res.data
    throw new Error(res.data?.msg || 'emergency stop failed')
  } catch {
    return mockApi.globalEmergencyStop()
  }
}
