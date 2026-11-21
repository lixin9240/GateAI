// ============================================================
// 天气接口 — 实时 / 小时 / 逐日预报（Apifox §14）
// ============================================================
import http from './request'
import type { ApiResponse } from '@/shared/types'
import type { WeatherBundle } from '@/types/weather'

/** GET /v1/weather — 获取天气总接口（实时 + 逐小时 + 逐日） */
export function getWeather(params?: { latitude?: number; longitude?: number }) {
  return http.get<ApiResponse<WeatherBundle>>('/v1/weather', { params })
}
