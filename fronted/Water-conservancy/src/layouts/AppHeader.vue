<script setup lang="ts">
// ============================================================
// 顶栏 — 折叠 / 页面标题 / 时钟 / 天气 / 急停
// ============================================================
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import {
  Expand,
  Fold,
  Timer,
  Sunny,
  PartlyCloudy,
  Cloudy,
  MostlyCloudy,
  Lightning,
  Moon,
  MoonNight,
  WindPower,
} from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { APP_TITLE } from '@/constants'
import GlobalEmergencyStop from '@/components/GlobalEmergencyStop.vue'
import { getWeather } from '@/api/weather'
import type { WeatherCurrent, WeatherHourlyItem, WeatherDailyItem } from '@/types/weather'

defineProps<{
  collapsed: boolean
}>()

const emit = defineEmits<{
  toggleCollapse: []
}>()

const route = useRoute()

const now = ref('')
let timer: ReturnType<typeof setInterval> | null = null

const pageTitle = computed(() => (route.meta.title as string) || APP_TITLE)

function updateClock() {
  const date = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  now.value = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`
}

onMounted(() => {
  updateClock()
  timer = setInterval(updateClock, 1000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

// ── 天气弹窗 ──
const weatherVisible = ref(false)
const weatherLoading = ref(false)
const weatherCurrent = ref<WeatherCurrent | null>(null)
const weatherHourly = ref<WeatherHourlyItem[]>([])
const weatherDaily = ref<WeatherDailyItem[]>([])

async function openWeatherDialog() {
  weatherVisible.value = true
  weatherLoading.value = true
  try {
    const res = await getWeather()

    if (res.data?.code === 0 && res.data.data) {
      const { current, hourly, daily } = res.data.data
      weatherCurrent.value = current ?? null
      weatherHourly.value = hourly ?? []
      weatherDaily.value = daily ?? []
    }

    if (!weatherCurrent.value && !weatherHourly.value.length && !weatherDaily.value.length) {
      ElMessage.warning('天气数据暂不可用')
    }
  } catch {
    ElMessage.warning('天气数据获取失败，请稍后重试')
  } finally {
    weatherLoading.value = false
  }
}

// ── 天气图标映射（彩云 weather_code → Element Plus 图标 + 颜色） ──
const weatherIconMap: Record<string, { icon: any; color: string; label: string }> = {
  CLEAR_DAY: { icon: Sunny, color: '#f59e0b', label: '晴' },
  CLEAR_NIGHT: { icon: Moon, color: '#6366f1', label: '晴夜' },
  PARTLY_CLOUDY_DAY: { icon: PartlyCloudy, color: '#f59e0b', label: '多云' },
  PARTLY_CLOUDY_NIGHT: { icon: MoonNight, color: '#6366f1', label: '多云夜' },
  CLOUDY: { icon: Cloudy, color: '#94a3b8', label: '阴' },
  MOSTLY_CLOUDY: { icon: MostlyCloudy, color: '#94a3b8', label: '大部多云' },
  LIGHT_RAIN: { icon: PartlyCloudy, color: '#3b82f6', label: '小雨' },
  MODERATE_RAIN: { icon: Cloudy, color: '#2563eb', label: '中雨' },
  HEAVY_RAIN: { icon: MostlyCloudy, color: '#1d4ed8', label: '大雨' },
  STORM_RAIN: { icon: Lightning, color: '#7c3aed', label: '暴雨' },
  THUNDER_SHOWER: { icon: Lightning, color: '#6d28d9', label: '雷阵雨' },
  WIND: { icon: WindPower, color: '#64748b', label: '大风' },
  FOG: { icon: Cloudy, color: '#cbd5e1', label: '雾' },
  HAZE: { icon: MostlyCloudy, color: '#cbd5e1', label: '霾' },
  SLEET: { icon: PartlyCloudy, color: '#38bdf8', label: '雨夹雪' },
  LIGHT_SNOW: { icon: PartlyCloudy, color: '#bae6fd', label: '小雪' },
  MODERATE_SNOW: { icon: Cloudy, color: '#7dd3fc', label: '中雪' },
  HEAVY_SNOW: { icon: MostlyCloudy, color: '#e0f2fe', label: '大雪' },
}

function getWeatherIcon(code: string | undefined) {
  if (!code) return { icon: Sunny, color: '#94a3b8', label: '未知' }
  // 精确匹配
  if (weatherIconMap[code]) return weatherIconMap[code]
  // 模糊匹配：包含关键词
  const upper = code.toUpperCase()
  if (upper.includes('CLEAR')) return weatherIconMap.CLEAR_DAY
  if (upper.includes('PARTLY')) return weatherIconMap.PARTLY_CLOUDY_DAY
  if (upper.includes('MOSTLY')) return weatherIconMap.MOSTLY_CLOUDY
  if (upper.includes('CLOUDY')) return weatherIconMap.CLOUDY
  if (upper.includes('STORM')) return weatherIconMap.STORM_RAIN
  if (upper.includes('HEAVY_RAIN')) return weatherIconMap.HEAVY_RAIN
  if (upper.includes('MODERATE_RAIN')) return weatherIconMap.MODERATE_RAIN
  if (upper.includes('LIGHT_RAIN')) return weatherIconMap.LIGHT_RAIN
  if (upper.includes('RAIN')) return weatherIconMap.LIGHT_RAIN
  if (upper.includes('THUNDER') || upper.includes('LIGHTNING')) return weatherIconMap.THUNDER_SHOWER
  if (upper.includes('SNOW')) return weatherIconMap.LIGHT_SNOW
  if (upper.includes('SLEET')) return weatherIconMap.SLEET
  if (upper.includes('FOG')) return weatherIconMap.FOG
  if (upper.includes('HAZE')) return weatherIconMap.HAZE
  if (upper.includes('WIND')) return weatherIconMap.WIND
  return { icon: Sunny, color: '#94a3b8', label: code }
}

function windDirectionLabel(deg: number | undefined): string {
  if (deg === undefined || deg === null) return '—'
  const dirs = ['北', '东北', '东', '东南', '南', '西南', '西', '西北']
  return dirs[Math.round(deg / 45) % 8]
}

function fmtSource(s: string | undefined): string {
  const map: Record<string, string> = { hefeng: '和风天气', caiyun: '彩云天气', openmeteo: 'Open-Meteo' }
  return map[s ?? ''] || s || ''
}

/** 格式化 ISO 时间字符串为短时间 */
function fmtTime(isoStr: string | undefined): string {
  if (!isoStr) return '—'
  // 提取 HH:mm，兼容 +08:00 和 Z 后缀
  const match = isoStr.match(/T(\d{2}):(\d{2})/)
  return match ? `${match[1]}:${match[2]}` : isoStr
}

/** 格式化 ISO 日期为 MM-DD + 星期 */
function fmtDate(isoStr: string | undefined): string {
  if (!isoStr) return '—'
  const match = isoStr.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (!match) return isoStr
  const [, y, m, d] = match
  const date = new Date(+y, +m - 1, +d)
  const weekDays = ['周日', '周一', '周二', '周三', '周四', '周五', '周六']
  return `${m}/${d} ${weekDays[date.getDay()]}`
}

/** humidity 0~1 → 百分比显示 */
function fmtHumidity(v: number | undefined): string {
  if (v === undefined || v === null) return '—'
  // 如果值大于 1 说明后端已经返回百分比
  return v > 1 ? `${Math.round(v)}%` : `${Math.round(v * 100)}%`
}

/** surface_pressure Pa → hPa */
function fmtPressure(v: number | undefined): string {
  if (v === undefined || v === null) return '—'
  // 值 > 5000 说明单位是 Pa
  return v > 5000 ? `${(v / 100).toFixed(1)} hPa` : `${v.toFixed(1)} hPa`
}
</script>

<template>
  <header class="app-header">
    <div class="app-header__left">
      <button class="app-header__collapse" type="button" @click="emit('toggleCollapse')">
        <el-icon><Fold v-if="!collapsed" /><Expand v-else /></el-icon>
      </button>
      <span class="app-header__title">{{ pageTitle }}</span>
    </div>
    <div class="app-header__right">
      <span class="app-header__clock">{{ now }}</span>
      <button class="app-header__weather-btn" title="查看时间与天气" @click="openWeatherDialog">
        <el-icon><Timer /></el-icon>
        <span class="app-header__weather-label">天气</span>
      </button>
      <GlobalEmergencyStop placement="header" />
    </div>
  </header>

  <!-- 天气弹窗 -->
  <el-dialog
    v-model="weatherVisible"
    :title="'时间与天气 — ' + now"
    width="900px"
    top="8vh"
    destroy-on-close
    class="weather-dialog"
  >
    <div v-loading="weatherLoading" class="weather-wrap">
      <!-- 实时天气 -->
      <div v-if="weatherCurrent" class="weather-section">
        <h3 class="weather-section__title">
          当前天气
          <span v-if="weatherCurrent.observed_at" class="weather-update">
            观测于 {{ fmtTime(weatherCurrent.observed_at) }}
          </span>
          <span v-if="weatherCurrent.source" class="weather-source">{{ fmtSource(weatherCurrent.source) }}</span>
        </h3>
        <div class="weather-current">
          <div class="weather-current__main">
            <el-icon
              class="weather-current__icon"
              :size="56"
              :color="getWeatherIcon(weatherCurrent.weather_code).color"
            >
              <component :is="getWeatherIcon(weatherCurrent.weather_code).icon" />
            </el-icon>
            <div>
              <span class="weather-current__temp">{{ weatherCurrent.temperature }}°C</span>
              <span class="weather-current__desc">{{ getWeatherIcon(weatherCurrent.weather_code).label }}</span>
            </div>
          </div>
          <div class="weather-current__detail">
            <div class="weather-item">
              <span class="weather-item__label">湿度</span>
              <span class="weather-item__val">{{ fmtHumidity(weatherCurrent.humidity) }}</span>
            </div>
            <div class="weather-item">
              <span class="weather-item__label">风向风速</span>
              <span class="weather-item__val">{{ windDirectionLabel(weatherCurrent.wind_direction) }} {{ weatherCurrent.wind_speed }} km/h</span>
            </div>
            <div class="weather-item">
              <span class="weather-item__label">气压</span>
              <span class="weather-item__val">{{ fmtPressure(weatherCurrent.surface_pressure) }}</span>
            </div>
            <div class="weather-item">
              <span class="weather-item__label">降水量</span>
              <span class="weather-item__val">{{ weatherCurrent.precipitation }} mm</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 小时预报 -->
      <div v-if="weatherHourly.length" class="weather-section">
        <h3 class="weather-section__title">逐时预报</h3>
        <div class="weather-hourly-scroll">
          <div v-for="(h, idx) in weatherHourly" :key="idx" class="weather-hourly-item">
            <span class="weather-hourly-item__time">{{ fmtTime(h.forecast_time) }}</span>
            <el-icon
              class="weather-hourly-item__icon"
              :size="28"
              :color="getWeatherIcon(h.weather_code).color"
            >
              <component :is="getWeatherIcon(h.weather_code).icon" />
            </el-icon>
            <span class="weather-hourly-item__temp">{{ h.temperature }}°</span>
            <span class="weather-hourly-item__label">{{ getWeatherIcon(h.weather_code).label }}</span>
            <span class="weather-hourly-item__rain">降雨 {{ h.precipitation }}mm</span>
            <span class="weather-hourly-item__wind">风速 {{ h.wind_speed }} km/h</span>
          </div>
        </div>
      </div>

      <!-- 逐日预报 -->
      <div v-if="weatherDaily.length" class="weather-section">
        <h3 class="weather-section__title">逐日预报</h3>
        <div class="weather-daily-grid">
          <div v-for="(d, idx) in weatherDaily" :key="idx" class="weather-daily-item">
            <span class="weather-daily-item__date">{{ fmtDate(d.forecast_date) }}</span>
            <el-icon
              class="weather-daily-item__icon"
              :size="36"
              :color="getWeatherIcon(d.weather_code).color"
            >
              <component :is="getWeatherIcon(d.weather_code).icon" />
            </el-icon>
            <span class="weather-daily-item__desc">{{ getWeatherIcon(d.weather_code).label }}</span>
            <div class="weather-daily-item__temps">
              <span class="weather-daily-item__high">{{ d.temperature_max }}°</span>
              <span class="weather-daily-item__low">{{ d.temperature_min }}°</span>
            </div>
            <div class="weather-daily-item__extra">
              <span class="weather-daily-item__stat">降雨 {{ d.precipitation_sum }}mm</span>
              <span class="weather-daily-item__stat">风速 {{ d.wind_speed_max }} km/h</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 无数据兜底 -->
      <div
        v-if="!weatherLoading && !weatherCurrent && !weatherHourly.length && !weatherDaily.length"
        class="weather-empty"
      >
        <p>暂无天气数据</p>
        <p class="weather-empty__hint">请检查网络连接或稍后重试</p>
      </div>
    </div>
  </el-dialog>
</template>

<style scoped lang="scss">
.app-header {
  display: flex;
  flex: 1;
  align-items: center;
  justify-content: space-between;
  min-width: 0;
  height: 100%;
  padding: 0 var(--spacing-lg);
  background: transparent;
  border-bottom: 1px solid var(--color-layout-blue-border);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);

  &__left {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    min-width: 0;
  }

  &__collapse {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border: 1px solid var(--color-layout-blue-border);
    border-radius: var(--border-radius-sm);
    background: rgba(0, 212, 255, 0.06);
    color: var(--color-layout-blue-brand);
    cursor: pointer;
  }

  &__title {
    font-size: var(--font-size-lg);
    font-weight: 500;
    color: var(--color-layout-blue-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    letter-spacing: 0.5px;
  }

  &__right {
    display: flex;
    align-items: center;
    gap: var(--spacing-lg);
  }

  &__clock {
    font-family: 'Roboto Mono', 'SF Mono', monospace;
    font-size: 15px;
    color: var(--color-layout-blue-brand);
    opacity: 0.9;
    letter-spacing: 1px;
  }

  &__weather-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 12px;
    border: 1px solid var(--color-layout-blue-border);
    border-radius: var(--border-radius-sm);
    background: rgba(0, 212, 255, 0.08);
    color: var(--color-layout-blue-brand);
    cursor: pointer;
    font-size: 14px;
    transition: background 0.2s;

    &:hover {
      background: rgba(0, 212, 255, 0.18);
    }
  }

  &__weather-label {
    font-size: 13px;
  }
}
</style>

<style lang="scss">
.weather-dialog {
  .el-dialog__header {
    padding: 20px 28px 12px;
    border-bottom: 1px solid #f0f2f5;
    font-size: 18px;
    font-weight: 600;
  }

  .el-dialog__body {
    padding: 0;
    max-height: 70vh;
    overflow-y: auto;
  }
}

.weather-wrap {
  padding: 24px 28px;
  min-height: 120px;
}

.weather-section {
  & + & {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 1px solid #f0f2f5;
  }

  &__title {
    margin: 0 0 16px;
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: baseline;
    gap: 10px;
  }
}

.weather-update {
  font-size: 13px;
  font-weight: 400;
  color: #94a3b8;
}

.weather-source {
  font-size: 12px;
  font-weight: 400;
  color: #94a3b8;
  padding: 1px 6px;
  background: #f1f5f9;
  border-radius: 4px;
  margin-left: auto;
}

// 当前天气
.weather-current {
  display: flex;
  gap: 40px;
  align-items: center;

  &__main {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 160px;
  }

  &__icon {
    flex-shrink: 0;
  }

  &__temp {
    display: block;
    font-size: 48px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.1;
  }

  &__desc {
    display: block;
    font-size: 16px;
    color: #64748b;
    margin-top: 4px;
  }

  &__detail {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 6px 32px;
    flex: 1;
  }
}

.weather-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 0;

  &__label {
    font-size: 15px;
    color: #94a3b8;
  }

  &__val {
    font-size: 15px;
    font-weight: 600;
    color: #334155;
  }
}

// 小时预报（横向滚动）
.weather-hourly-scroll {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding-bottom: 6px;

  &::-webkit-scrollbar {
    height: 4px;
  }

  &::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
  }
}

.weather-hourly-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  min-width: 96px;
  padding: 14px 12px;
  background: #f8fafc;
  border-radius: 8px;
  flex-shrink: 0;

  &__time {
    font-size: 14px;
    color: #94a3b8;
    font-weight: 500;
  }

  &__icon {
    flex-shrink: 0;
  }

  &__temp {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
  }

  &__label {
    font-size: 13px;
    color: #64748b;
  }

  &__rain {
    font-size: 13px;
    color: #3b82f6;
  }

  &__wind {
    font-size: 13px;
    color: #94a3b8;
  }
}

// 逐日预报
.weather-daily-grid {
  display: flex;
  gap: 12px;
}

.weather-daily-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 18px 10px;
  background: #f8fafc;
  border-radius: 10px;
  text-align: center;

  &__date {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
  }

  &__icon {
    flex-shrink: 0;
  }

  &__desc {
    font-size: 14px;
    color: #64748b;
  }

  &__temps {
    display: flex;
    gap: 10px;
  }

  &__high {
    font-size: 20px;
    font-weight: 700;
    color: #ef4444;
  }

  &__low {
    font-size: 20px;
    font-weight: 700;
    color: #3b82f6;
  }

  &__extra {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 12px;
    font-size: 14px;
    color: #64748b;
  }

  &__stat {
    font-size: 14px;
    color: #64748b;
  }
}

// 空数据
.weather-empty {
  text-align: center;
  padding: 40px 0;
  color: #94a3b8;

  p {
    margin: 0;
    font-size: 15px;
  }

  &__hint {
    margin-top: 6px !important;
    font-size: 13px !important;
    color: #cbd5e1;
  }
}
</style>
