<script setup lang="ts">
// ============================================================
// 水情监测 — 全屏地图 + 顶部浮层 KPI + 右侧折叠图表抽屉
// ============================================================
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent, LegendComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import { fetchRealtimeKpi } from '@/api/monitoring'
import { fetchPrediction } from '@/api/dispatchPage'
import type { RealtimeKpi } from '@/types/monitoring'
import type { PredictionPoint } from '@/types/dispatch'

use([LineChart, GridComponent, TooltipComponent, LegendComponent, CanvasRenderer])

const snapshot = ref<RealtimeKpi>({
  upstreamLevel: 378.52, downstreamLevel: 269.18, inflowRate: 6350,
  outflowRate: 5820, gateOpening: 34, powerOutput: 4119, capacity: 48.36,
  timestamp: new Date().toISOString(),
})
const trend = ref<RealtimeKpi[]>([])
const connected = ref(false)
const useMock = ref(true)
const prediction = ref<{ water: PredictionPoint[]; flow: PredictionPoint[] }>({ water: [], flow: [] })

let fetchTimer: ReturnType<typeof setInterval> | null = null
async function refreshKpi() {
  try {
    const [kpi, pred] = await Promise.all([fetchRealtimeKpi(1), fetchPrediction(1)])
    snapshot.value = kpi
    useMock.value = false
    connected.value = true
    trend.value.push(kpi)
    if (trend.value.length > 1440) trend.value.shift()
    if (pred.data) {
      prediction.value = {
        water: pred.data.water_seq ?? [],
        flow: pred.data.flow_seq ?? [],
      }
    }
  } catch {
    useMock.value = true
    connected.value = false
  }
}

const fmt = (v: number | undefined, d = 2) =>
  v != null
    ? v.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d })
    : '--'

const NORMAL = 380
const drawerOpen = ref(false)

const T_IMG =
  'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
const T_LBL =
  'https://services.arcgisonline.com/arcgis/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}'

type Status = 'normal' | 'warning' | 'critical'
const SC: Record<Status, string> = { normal: '#22c55e', warning: '#f59e0b', critical: '#ef4444' }

interface Station {
  id: string
  name: string
  lat: number
  lng: number
  cat: '水位' | '流量' | '雨量'
  key: 'up' | 'dn' | 'in' | 'out' | 'rain'
}
const SS: Station[] = [
  { id: 'ws1', name: '坝前水位', lat: 28.644, lng: 104.389, cat: '水位', key: 'up' },
  { id: 'ws2', name: '坝后水位', lat: 28.64, lng: 104.398, cat: '水位', key: 'dn' },
  { id: 'fs1', name: '入库流量', lat: 28.665, lng: 104.362, cat: '流量', key: 'in' },
  { id: 'fs2', name: '出库流量', lat: 28.633, lng: 104.408, cat: '流量', key: 'out' },
  { id: 'rn1', name: '雨量站 A', lat: 28.672, lng: 104.415, cat: '雨量', key: 'rain' },
  { id: 'rn2', name: '雨量站 B', lat: 28.618, lng: 104.372, cat: '雨量', key: 'rain' },
]
function sv(s: Station) {
  const m: Record<string, number> = {
    up: snapshot.value.upstreamLevel,
    dn: snapshot.value.downstreamLevel,
    in: snapshot.value.inflowRate,
    out: snapshot.value.outflowRate,
    rain: 0,
  }
  return m[s.key] ?? 0
}
function su(c: string) {
  return c === '水位' ? 'm' : c === '流量' ? 'm³/s' : 'mm'
}
function status(s: Station): Status {
  if (s.cat === '雨量') return 'normal'
  const v = sv(s)
  const base: Record<string, number> = { up: 378.5, dn: 269.2, in: 6350, out: 5820 }
  const d = Math.abs(v - (base[s.key] ?? 0))
  if (s.cat === '水位') return d >= 0.8 ? 'critical' : d >= 0.4 ? 'warning' : 'normal'
  return d >= 600 ? 'critical' : d >= 300 ? 'warning' : 'normal'
}

// ═══ 趋势图：双Y轴 + 实测/AI预测/置信区间/预警线 + 时段切换 ═══
const chartRange = ref<'1h' | '6h' | '24h'>('1h')
const RANGE_HOURS: Record<'1h' | '6h' | '24h', number> = { '1h': 1, '6h': 6, '24h': 24 }
const CHART_RANGES = [
  { value: '1h' as const, label: '一小时' },
  { value: '6h' as const, label: '六小时' },
  { value: '24h' as const, label: '二十四小时' },
]

type TrendPoint = (typeof trend.value)[number]

function filterTrendPoints(all: readonly TrendPoint[], range: '1h' | '6h' | '24h') {
  const cutoff = Date.now() - RANGE_HOURS[range] * 3600_000
  let pts = all.filter((p) => new Date(p.timestamp).getTime() >= cutoff)
  if (!pts.length) pts = [...all]

  const maxDisplay = range === '1h' ? 48 : range === '6h' ? 72 : 96
  if (pts.length <= maxDisplay) return pts

  const step = Math.ceil(pts.length / maxDisplay)
  return pts.filter((_, i) => i % step === 0 || i === pts.length - 1)
}

function formatAxisLabel(ts: string, range: '1h' | '6h' | '24h', compact = false) {
  const d = new Date(ts)
  const hh = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  if (range === '24h') {
    return compact ? `${d.getMonth() + 1}/${d.getDate()}` : `${d.getMonth() + 1}/${d.getDate()} ${hh}:00`
  }
  if (range === '6h' && compact) return `${hh}:${mm}`
  return `${hh}:${mm}`
}

// 生成置信区间（后端暂不返回，客户端估算）
function estimateBounds(vals: number[], base: number): { upper: number[]; lower: number[] } {
  let v = base
  return {
    upper: vals.map((val, i) => { v = val + Math.abs(val - base) * (i + 1) * 0.3; return +v.toFixed(2) }),
    lower: vals.map((val, i) => { const lo = val - Math.abs(val - base) * (i + 1) * 0.3; return +lo.toFixed(2) }),
  }
}

const trendOpt = computed(() => {
  const range = chartRange.value
  const pts = filterTrendPoints(trend.value, range)
  const compactLabels = pts.length > 20 || range === '24h'
  const labels = pts.map((p) => formatAxisLabel(p.timestamp, range, compactLabels))
  const levelData = pts.map((p) => p.upstreamLevel)
  const outflowData = pts.map((p) => p.outflowRate)
  const lastLevel = levelData[levelData.length - 1] || 378.5

  // 使用后端 LSTM 预测数据
  const predWaterVals = prediction.value.water.map((p) => p.value)
  const predFlowVals = prediction.value.flow.map((p) => p.value)
  const predLabels = prediction.value.water.map((p) => {
    const d = new Date(p.time)
    return isNaN(d.getTime())
      ? formatAxisLabel(p.time, range, compactLabels)
      : formatAxisLabel(d.toISOString(), range, compactLabels)
  })
  const n = Math.max(predWaterVals.length, 16)
  const pw = predWaterVals.length > 0 ? predWaterVals : Array.from({ length: 16 }, (_, i) => { const v = lastLevel + (Math.random() - 0.48) * 0.08 * (i + 1); return +v.toFixed(2) })
  const pf = predFlowVals.length > 0 ? predFlowVals : Array.from({ length: 16 }, (_, i) => { const v = (outflowData[outflowData.length - 1] || 5820) + (Math.random() - 0.48) * 80 * (i + 1); return +Math.round(v) })
  const pl = predLabels.length > 0 ? predLabels : pw.map((_, i) => {
    const d = new Date(Date.now() + (i + 1) * 5 * 60000)
    return formatAxisLabel(d.toISOString(), range, compactLabels)
  })
  const { upper: wUpper, lower: wLower } = estimateBounds(pw, lastLevel)

  const xData = [...labels, ...pl]
  const totalLabels = xData.length
  const maxTicks = range === '24h' ? 8 : range === '6h' ? 7 : 6
  const labelRotate = totalLabels > 12 ? (range === '24h' ? 40 : 30) : totalLabels > 8 ? 20 : 0
  const axisInterval =
    totalLabels <= maxTicks ? 0 : Math.max(1, Math.floor(totalLabels / maxTicks))
  const lvlA = [...levelData, ...Array(n).fill(null)]
  const lvlP = [...Array(labels.length).fill(null), ...pw]
  const lvlU = [...Array(labels.length).fill(null), ...wUpper]
  const lvlL = [...Array(labels.length).fill(null), ...wLower]
  const flwA = [...outflowData, ...Array(n).fill(null)]
  const flwP = [...Array(labels.length).fill(null), ...pf]

  const lvlMin = Math.min(...levelData, 377.5)
  const lvlMax = Math.max(...levelData, 380.5)
  const flowMin = Math.min(...outflowData, 5000)
  const flowMax = Math.max(...outflowData, 7000)

  return {
    backgroundColor: 'transparent',
    grid: { left: 52, right: 62, top: 44, bottom: labelRotate > 0 ? 56 : 36 },
    legend: {
      data: ['实测水位', 'AI水位预测', '出库流量', 'AI出库预测'],
      textStyle: { color: '#475569', fontSize: 14 },
      top: 4,
      right: 0,
      itemGap: 16,
      itemWidth: 18,
      itemHeight: 10,
    },
    xAxis: {
      type: 'category',
      data: xData,
      axisLine: { lineStyle: { color: '#e5e7eb' } },
      axisLabel: {
        color: '#64748b',
        fontSize: 12,
        interval: axisInterval,
        hideOverlap: true,
        rotate: labelRotate,
        margin: labelRotate > 0 ? 10 : 8,
      },
    },
    yAxis: [
      {
        type: 'value',
        min: +(lvlMin - 0.3).toFixed(1),
        max: +(lvlMax + 0.3).toFixed(1),
        splitLine: { lineStyle: { color: '#f1f5f9' } },
        axisLabel: { color: '#64748b', fontSize: 13, formatter: (v: number) => v + ' m' },
      },
      {
        type: 'value',
        min: Math.floor(flowMin - 200),
        max: Math.ceil(flowMax + 200),
        splitLine: { show: false },
        axisLabel: {
          color: '#64748b',
          fontSize: 13,
          formatter: (v: number) => (v / 1000).toFixed(1) + 'k',
        },
      },
    ],
    series: [
      {
        name: '实测水位',
        type: 'line',
        yAxisIndex: 0,
        data: lvlA,
        smooth: true,
        symbol: 'none',
        lineStyle: { color: '#3b82f6', width: 2.5 },
        areaStyle: {
          color: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 0,
            y2: 1,
            colorStops: [
              { offset: 0, color: 'rgba(59,130,246,0.12)' },
              { offset: 1, color: 'rgba(59,130,246,0.01)' },
            ],
          },
        },
      },
      {
        name: 'AI水位预测',
        type: 'line',
        yAxisIndex: 0,
        data: lvlP,
        smooth: true,
        symbol: 'none',
        lineStyle: { color: '#3b82f6', width: 2, type: 'dashed' },
      },
      {
        name: '置信',
        type: 'line',
        yAxisIndex: 0,
        data: lvlU,
        smooth: true,
        symbol: 'none',
        lineStyle: { color: 'transparent', width: 0 },
        areaStyle: { color: 'rgba(59,130,246,0.05)' },
        stack: 'ci',
        silent: true,
        legendHoverLink: false,
      },
      {
        name: '置信',
        type: 'line',
        yAxisIndex: 0,
        data: lvlL,
        smooth: true,
        symbol: 'none',
        lineStyle: { color: 'transparent', width: 0 },
        areaStyle: { color: 'rgba(255,255,255,1)' },
        stack: 'ci',
        silent: true,
        legendHoverLink: false,
      },
      {
        name: '预警线',
        type: 'line',
        yAxisIndex: 0,
        data: [],
        symbol: 'none',
        lineStyle: { color: 'transparent', width: 0 },
        markLine: {
          silent: true,
          symbol: 'none',
          label: { show: true, formatter: '预警{c}m', fontSize: 12, color: '#f59e0b' },
          lineStyle: { color: '#f59e0b', type: 'dashed', width: 1 },
          data: [{ yAxis: 380.5 }],
        },
        legendHoverLink: false,
      },
      {
        name: '出库流量',
        type: 'line',
        yAxisIndex: 1,
        data: flwA,
        smooth: true,
        symbol: 'none',
        lineStyle: { color: '#22c55e', width: 2 },
      },
      {
        name: 'AI出库预测',
        type: 'line',
        yAxisIndex: 1,
        data: flwP,
        smooth: true,
        symbol: 'none',
        lineStyle: { color: '#22c55e', width: 1.5, type: 'dashed' },
      },
    ],
    tooltip: {
      trigger: 'axis',
      backgroundColor: '#fff',
      borderColor: '#e5e7eb',
      textStyle: { color: '#1e293b', fontSize: 14 },
      padding: [10, 14],
    },
  }
})

const mapEl = ref<HTMLDivElement | null>(null)
let map: L.Map | null = null
let stationLayer: L.LayerGroup | null = null
const DAM: [number, number][] = [
  [28.647, 104.387],
  [28.641, 104.391],
]

function mkIcon(s: Station): L.DivIcon {
  const st = status(s)
  const c = SC[st]
  const pulse =
    st !== 'normal' ? `animation:sp ${st === 'critical' ? '0.7s' : '1.6s'} infinite` : ''
  return L.divIcon({
    className: 'st-icon',
    html: `<div class="st-wrap"><div class="st-ring" style="${pulse};border-color:${c}"></div><div class="st-dot" style="background:${c};box-shadow:0 0 6px ${c}"></div></div><div class="st-nm">${s.name}</div>`,
    iconSize: [60, 42],
    iconAnchor: [30, 36],
  })
}
function refresh() {
  if (!stationLayer) return
  stationLayer.clearLayers()
  SS.forEach((s) => {
    const m = L.marker([s.lat, s.lng], { icon: mkIcon(s) })
    const st = status(s),
      v = sv(s),
      u = su(s.cat)
    m.bindPopup(
      `<div style="text-align:center;min-width:100px;font-size:13px">${s.name}<br><span style="font-size:22px;font-weight:700;color:${SC[st]}">${fmt(v, s.cat === '流量' ? 0 : 2)}</span><small style="color:#9ca3af;font-size:11px"> ${u}</small></div>`,
      { offset: [0, -6], className: 'mpop', closeButton: false },
    )
    stationLayer!.addLayer(m)
  })
}
function init() {
  if (!mapEl.value || map) return
  map = L.map(mapEl.value, {
    center: [28.644, 104.389],
    zoom: 14,
    zoomControl: false,
    attributionControl: false,
  })
  L.control.zoom({ position: 'bottomright' }).addTo(map)
  L.tileLayer(T_IMG, { maxZoom: 19, minZoom: 1 }).addTo(map)
  L.tileLayer(T_LBL, { maxZoom: 19, minZoom: 1 }).addTo(map)
  L.polyline(DAM, { color: '#1f2937', weight: 4, opacity: 0.6 }).addTo(map)
  const dm = L.latLng((DAM[0][0] + DAM[1][0]) / 2, (DAM[0][1] + DAM[1][1]) / 2)
  L.marker(dm, {
    icon: L.divIcon({ className: 'dam-icon', html: '⬟', iconSize: [20, 20], iconAnchor: [10, 10] }),
  }).addTo(map)
  const arrows: [number, number, number][] = [
    [28.656, 104.368, 220],
    [28.637, 104.406, 50],
    [28.633, 104.412, 50],
  ]
  arrows.forEach(([lat, lng, rot]) =>
    L.marker([lat, lng], {
      icon: L.divIcon({
        className: 'farr',
        html: `<span style="display:block;transform:rotate(${rot}deg)">▶</span>`,
        iconSize: [14, 14],
        iconAnchor: [7, 7],
      }),
    }).addTo(map!),
  )
  stationLayer = L.layerGroup().addTo(map)
  refresh()
}
onMounted(() => {
  requestAnimationFrame(() => init())
  refreshKpi()
  fetchTimer = setInterval(refreshKpi, 5000)
})
onUnmounted(() => {
  if (fetchTimer) clearInterval(fetchTimer)
  if (map) {
    map.remove()
    map = null
  }
})
const tmr = setInterval(() => {
  if (map) refresh()
}, 2000)
onUnmounted(() => clearInterval(tmr))
watch(
  () => snapshot.value.upstreamLevel,
  () => refresh(),
)
</script>

<template>
  <div class="hydro">
    <div ref="mapEl" class="hydro__map" />
    <div class="hydro__top">
      <div
        class="kpi"
        v-for="k in [
          {
            l: '上游水位',
            v: snapshot.upstreamLevel,
            u: 'm',
            d: 2,
            w: snapshot.upstreamLevel > NORMAL + 0.4,
          },
          { l: '下游水位', v: snapshot.downstreamLevel, u: 'm', d: 2, w: false },
          { l: '入库流量', v: snapshot.inflowRate, u: 'm³/s', d: 0, w: false },
          { l: '出库流量', v: snapshot.outflowRate, u: 'm³/s', d: 0, w: false },
        ]"
        :key="k.l"
      >
        <span class="kpi__l">{{ k.l }}</span>
        <span class="kpi__v" :class="{ warn: k.w }"
          >{{ fmt(k.v, k.d) }}<small>{{ k.u }}</small></span
        >
      </div>
      <span class="hydro__badge" :class="{ mock: useMock, live: connected && !useMock }"
        ><span class="hydro__badge-dot" />{{ useMock ? '模拟' : '实时' }}</span
      >
    </div>
    <button class="hydro__btn" @click="drawerOpen = !drawerOpen">
      {{ drawerOpen ? '✕' : '☰' }}
    </button>
    <Transition name="dr">
      <div v-if="drawerOpen" class="hydro__dr">
        <div class="hydro__dr-tt">趋势图表</div>
        <div class="hydro__range">
          <button
            v-for="r in CHART_RANGES"
            :key="r.value"
            type="button"
            class="hydro__range-btn"
            :class="{ on: chartRange === r.value }"
            @click="chartRange = r.value"
          >
            {{ r.label }}
          </button>
        </div>
        <div class="hydro__cb">
          <v-chart class="hydro__ch" :option="trendOpt" autoresize />
        </div>
        <div class="hydro__dr-tt hydro__dr-tt--spaced">监测站点</div>
        <div v-for="s in SS" :key="s.id" class="hydro__stn">
          <span class="hydro__stn-d" :style="{ background: SC[status(s)] }" />
          <span class="hydro__stn-n">{{ s.name }}</span>
          <span class="hydro__stn-v"
            >{{ fmt(sv(s), s.cat === '流量' ? 0 : 2) }}<small>{{ su(s.cat) }}</small></span
          >
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped lang="scss">
.hydro {
  position: relative;
  width: 100%;
  height: calc(100vh - 56px);
  overflow: hidden;
  background: #fff;
}

.hydro__map {
  width: 100%;
  height: 100%;
}

.hydro__top {
  position: absolute;
  top: 12px;
  left: 12px;
  z-index: 600;
  display: flex;
  align-items: center;
  gap: 10px;
}

.hydro__badge {
  display: flex;
  align-items: center;
  gap: 5px;
  padding: 4px 10px;
  font-size: 10px;
  color: #6b7280;
  background: rgba(255, 255, 255, 0.9);
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  backdrop-filter: blur(8px);

  &.mock {
    color: inherit;
    background: rgba(255, 251, 235, 0.9);
    border-color: #fde68a;
  }

  &.live {
    background: rgba(240, 253, 244, 0.9);
    border-color: #bbf7d0;
  }
}

.hydro__badge-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.mock .hydro__badge-dot {
  background: #f59e0b;
  animation: kp 2s infinite;
}

.live .hydro__badge-dot {
  background: #22c55e;
}

.hydro__btn {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  font-size: 16px;
  color: #374151;
  background: rgba(255, 255, 255, 0.9);
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
  backdrop-filter: blur(8px);
  cursor: pointer;
  transition: all 0.15s;

  &:hover {
    background: #fff;
    border-color: #d1d5db;
  }
}

.hydro__dr {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  z-index: 650;
  display: flex;
  flex-direction: column;
  gap: 14px;
  width: min(720px, 65vw);
  padding: 56px 20px 24px;
  overflow-y: auto;
  background: #fff;
  border-left: 1px solid #e5e7eb;
  box-shadow: -4px 0 24px rgba(0, 0, 0, 0.08);

  &::-webkit-scrollbar {
    width: 6px;
  }

  &::-webkit-scrollbar-track {
    background: transparent;
  }

  &::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;

    &:hover {
      background: #9ca3af;
    }
  }
}

.hydro__dr-tt {
  font-size: 17px;
  font-weight: 600;
  color: #1e293b;

  &--spaced {
    margin-top: 6px;
  }
}

.hydro__cb {
  padding: 14px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.hydro__ch {
  width: 100%;
  height: 340px;
}

.hydro__range {
  display: flex;
  gap: 8px;
  width: 100%;
  margin-bottom: 4px;
}

.hydro__range-btn {
  flex: 1;
  padding: 7px 8px;
  font-size: 14px;
  font-weight: 500;
  color: #475569;
  background: #f8fafc;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;

  &:hover {
    color: #1e293b;
    background: #f1f5f9;
    border-color: #d1d5db;
  }

  &.on {
    font-weight: 600;
    color: #fff;
    background: #3b82f6;
    border-color: #3b82f6;
    box-shadow: 0 1px 4px rgba(59, 130, 246, 0.3);
  }
}

.hydro__stn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 10px;
  border-radius: 6px;

  &:hover {
    background: #f9fafb;
  }
}

.hydro__stn-d {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.hydro__stn-n {
  flex: 1;
  font-size: 15px;
  font-weight: 500;
  color: #1e293b;
}

.hydro__stn-v {
  font-size: 16px;
  font-weight: 700;
  color: #1f2937;

  small {
    margin-left: 3px;
    font-size: 14px;
    font-weight: 400;
    color: #64748b;
  }
}

.kpi {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.88);
  border: 1px solid rgba(0, 0, 0, 0.06);
  border-radius: 8px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  backdrop-filter: blur(8px);
}

.kpi__l {
  font-size: 12px;
  font-weight: 500;
  color: #64748b;
  letter-spacing: 0.3px;
}

.kpi__v {
  font-size: 18px;
  font-weight: 700;
  line-height: 1.2;
  color: #1f2937;

  small {
    margin-left: 3px;
    font-size: 12px;
    font-weight: 400;
    color: #64748b;
  }

  &.warn {
    color: #d97706;
  }
}

.dr-enter-active,
.dr-leave-active {
  transition: transform 0.25s ease;
}

.dr-enter-from,
.dr-leave-to {
  transform: translateX(100%);
}

@keyframes kp {
  0%,
  100% {
    opacity: 1;
  }

  50% {
    opacity: 0.3;
  }
}

@keyframes sp {
  0%,
  100% {
    opacity: 1;
  }

  50% {
    opacity: 0.4;
  }
}
</style>

<style lang="scss">
.main-layout__content:has(.hydro) {
  padding: 0 !important;
  overflow: hidden !important;
}
.leaflet-container {
  background: #e8ecf0;
}
.leaflet-control-zoom {
  z-index: 700 !important;
}
.st-icon {
  text-align: center;
}
.st-wrap {
  display: inline-block;
  position: relative;
  width: 24px;
  height: 24px;
}
.st-ring {
  position: absolute;
  inset: -2px;
  border-radius: 50%;
  border: 2px solid;
  animation: m-ring 2.5s ease-out infinite;
}
.st-dot {
  position: absolute;
  inset: 6px;
  border-radius: 50%;
}
.st-nm {
  font-size: 10px;
  font-weight: 600;
  color: #374151;
  margin-top: 2px;
  white-space: nowrap;
  text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
}
@keyframes m-ring {
  0% {
    transform: scale(1);
    opacity: 0.5;
  }
  100% {
    transform: scale(2.2);
    opacity: 0;
  }
}
.dam-icon {
  font-size: 16px !important;
  color: #1f2937 !important;
  text-align: center;
  line-height: 20px;
  filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.2));
}
.farr span {
  color: rgba(0, 0, 0, 0.2);
  font-size: 12px;
}
.mpop {
  .leaflet-popup-content-wrapper {
    background: #fff !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 10px !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1) !important;
  }
  .leaflet-popup-content {
    margin: 10px 14px !important;
  }
  .leaflet-popup-tip {
    background: #fff !important;
  }
  .leaflet-popup-close-button {
    color: #9ca3af !important;
  }
}
</style>
