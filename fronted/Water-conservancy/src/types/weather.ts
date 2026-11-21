// ============================================================
// 天气接口 — 类型定义（对齐 Apifox §14 气象模块）
// ============================================================

/** 实时天气 GET /v1/weather/current */
export interface WeatherCurrent {
  /** 实时温度（℃） */
  temperature: number
  /** 湿度（%） */
  humidity: number
  /** 天气图标代码（和风天气 icon） */
  weather_code: string
  /** 风速（km/h） */
  wind_speed: number
  /** 风向角度（°） */
  wind_direction: number
  /** 气压（hPa） */
  surface_pressure: number
  /** 降水量（mm） */
  precipitation: number
  /** 观测时间 */
  observed_at: string
  /** 数据来源：hefeng / caiyun / openmeteo */
  source: string
}

/** 小时天气数据点 */
export interface WeatherHourlyItem {
  /** 预报时间点 */
  forecast_time: string
  /** 温度（℃） */
  temperature: number
  /** 降水量（mm） */
  precipitation: number
  /** 降水概率（%） */
  precipitation_probability: number
  /** 湿度（%） */
  humidity: number
  /** 风速（km/h） */
  wind_speed: number
  /** 天气图标代码 */
  weather_code: string
  /** 气压（hPa） */
  surface_pressure: number
  /** 数据来源 */
  source: string
}

/** 每日天气数据点 */
export interface WeatherDailyItem {
  /** 预报日期 */
  forecast_date: string
  /** 天气图标代码 */
  weather_code: string
  /** 最高温度（℃） */
  temperature_max: number
  /** 最低温度（℃） */
  temperature_min: number
  /** 日总降水量（mm） */
  precipitation_sum: number
  /** 降水概率（%） */
  precipitation_probability: number
  /** 最大风速（km/h） */
  wind_speed_max: number
  /** 数据来源 */
  source: string
}

/** 天气总接口 GET /v1/weather — 实时 + 逐小时 + 逐日 汇总 */
export interface WeatherBundle {
  /** 实时天气 */
  current: WeatherCurrent
  /** 逐小时预报列表 */
  hourly: WeatherHourlyItem[]
  /** 逐日预报列表 */
  daily: WeatherDailyItem[]
}
