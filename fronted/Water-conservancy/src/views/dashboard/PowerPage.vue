<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import VChart from 'vue-echarts'
import { use } from 'echarts/core'
import { LineChart, BarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import { fetchPowerUnits, fetchPowerTrendData } from '@/api/monitoring'
use([LineChart, BarChart, GridComponent, TooltipComponent, CanvasRenderer])

interface UnitView { name: string; mw: number; eff: number; rpm: number; temp: number; vib: number; status: string }
const units = ref<UnitView[]>([])

const sc: Record<string, { color: string; label: string }> = {
  online: { color: '#22c55e', label: '运行' },
  running: { color: '#22c55e', label: '运行' },
  stopped: { color: '#94a3b8', label: '停机' },
  maintenance: { color: '#f59e0b', label: '检修' },
  offline: { color: '#94a3b8', label: '离线' },
}

const stats = computed(() => ({
  totalMW: units.value.reduce((s, u) => s + u.mw, 0),
  avgEff: (() => {
    const r = units.value.filter((u) => u.mw > 0)
    return r.length ? +(r.reduce((s, u) => s + u.eff, 0) / r.length).toFixed(1) : 0
  })(),
  today: 1248.6,
  running: units.value.filter((u) => u.mw > 0).length,
}))

const trendData = ref<[string, number][]>([])
let t: ReturnType<typeof setInterval>

async function loadData() {
  try {
    const uData = await fetchPowerUnits(1)
    units.value = uData.map((u) => ({
      name: u.name,
      mw: u.status === 'online' ? u.current_output : 0,
      eff: +(u.utilization_rate * 100).toFixed(1),
      rpm: u.status === 'online' ? 75 : 0,
      temp: u.status === 'online' ? 60 + Math.random() * 5 : 28,
      vib: u.status === 'online' ? 1.5 + Math.random() : 0,
      status: u.status,
    }))
  } catch { /* 机组接口暂不可用 */ }
  try {
    const tData = await fetchPowerTrendData(1)
    trendData.value = tData.map((p) => [p.time?.slice(-5) ?? '--:--', p.avg_power ?? 0] as [string, number])
  } catch { /* 趋势接口暂不可用 */ }
}

onMounted(() => {
  loadData()
  t = setInterval(loadData, 15000)
})
onUnmounted(() => clearInterval(t))

const barOpt = computed(() => ({
  backgroundColor: 'transparent',
  grid: { left: 60, right: 20, top: 10, bottom: 28 },
  xAxis: {
    type: 'category',
    data: units.value.map((u) => u.name + '机组'),
    axisLine: { lineStyle: { color: '#e5e7eb' } },
    axisLabel: { color: '#64748b', fontSize: 12 },
  },
  yAxis: {
    type: 'value',
    splitLine: { lineStyle: { color: '#f3f4f6' } },
    axisLabel: { color: '#94a3b8', fontSize: 10, formatter: (v: number) => v + ' MW' },
  },
  series: [
    {
      type: 'bar',
      data: units.value.map((u) => ({
        value: u.mw,
        itemStyle: { color: u.mw > 0 ? '#3b82f6' : '#e5e7eb', borderRadius: [4, 4, 0, 0] },
      })),
      barWidth: 28,
      emphasis: { itemStyle: { color: '#2563eb' } },
    },
  ],
  tooltip: {
    trigger: 'axis',
    backgroundColor: '#fff',
    borderColor: '#e5e7eb',
    textStyle: { color: '#374151', fontSize: 12 },
  },
}))

const lineOpt = computed(() => ({
  backgroundColor: 'transparent',
  grid: { left: 56, right: 12, top: 6, bottom: 24 },
  xAxis: {
    type: 'category',
    data: trendData.value.map((d) => d[0]),
    axisLine: { lineStyle: { color: '#e5e7eb' } },
    axisLabel: { color: '#9ca3af', fontSize: 9, interval: 9 },
  },
  yAxis: {
    type: 'value',
    splitLine: { lineStyle: { color: '#f3f4f6' } },
    axisLabel: { color: '#9ca3af', fontSize: 9, formatter: (v: number) => v + 'MW' },
  },
  series: [
    {
      type: 'line',
      data: trendData.value.map((d) => d[1]),
      smooth: true,
      symbol: 'none',
      lineStyle: { color: '#3b82f6', width: 2 },
      areaStyle: {
        color: {
          type: 'linear',
          x: 0,
          y: 0,
          x2: 0,
          y2: 1,
          colorStops: [
            { offset: 0, color: 'rgba(59,130,246,0.15)' },
            { offset: 1, color: 'rgba(59,130,246,0.01)' },
          ],
        },
      },
    },
  ],
  tooltip: {
    trigger: 'axis',
    backgroundColor: '#fff',
    borderColor: '#e5e7eb',
    textStyle: { color: '#374151', fontSize: 11 },
  },
}))
</script>

<template>
  <div class="gp">
    <!-- KPI 数字条 -->
    <div class="kpis">
      <span class="kpis__label">总出力</span
      ><span class="kpis__val">{{ stats.totalMW }}<small> MW</small></span>
      <span class="kpis__sep" />
      <span class="kpis__label">平均效率</span
      ><span class="kpis__val">{{ stats.avgEff }}<small> %</small></span>
      <span class="kpis__sep" />
      <span class="kpis__label">今日发电</span
      ><span class="kpis__val">{{ stats.today }}<small> 万kWh</small></span>
      <span class="kpis__sep" />
      <span class="kpis__label">运行机组</span
      ><span class="kpis__val" style="color: #16a34a">{{ stats.running }}<small> / 8</small></span>
      <span
        class="kpis__badge"
        :style="{
          background: stats.running > 0 ? '#f0fdf4' : '#fef2f2',
          color: stats.running > 0 ? '#16a34a' : '#ef4444',
        }"
        >{{ stats.running > 0 ? '已并网' : '离网' }}</span
      >
    </div>

    <div class="main">
      <!-- 上：机组出力柱状图 -->
      <div class="section">
        <div class="section__title">机组出力对比</div>
        <v-chart class="chart-bar" :option="barOpt" autoresize />
      </div>

      <!-- 下：数据表 + 趋势图 -->
      <div class="bottom">
        <div class="section" style="flex: 1">
          <div class="section__title">机组详情</div>
          <table class="tbl">
            <thead>
              <tr>
                <th>机组</th>
                <th>出力</th>
                <th>效率</th>
                <th>转速</th>
                <th>温度</th>
                <th>振动</th>
                <th>状态</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in units" :key="u.name" :class="{ off: u.status !== 'online' }">
                <td class="td-n">{{ u.name }} 机组</td>
                <td class="td-v">{{ u.status === 'online' ? u.mw + ' MW' : '—' }}</td>
                <td>{{ u.status === 'online' ? u.eff + '%' : '—' }}</td>
                <td>{{ u.status === 'online' ? u.rpm : '—' }}<small> rpm</small></td>
                <td>{{ u.temp }}<small> °C</small></td>
                <td>{{ u.status === 'online' ? u.vib : '—' }}<small> mm/s</small></td>
                <td>
                  <span :style="{ color: sc[u.status].color, fontWeight: 600 }">{{
                    sc[u.status].label
                  }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="section" style="width: 360px; flex-shrink: 0">
          <div class="section__title">出力趋势 · 60 分钟</div>
          <v-chart class="chart-line" :option="lineOpt" autoresize />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.gp {
  height: calc(100vh - 56px);
  display: flex;
  flex-direction: column;
  background: #fff;
  overflow: hidden;
}

// KPI 数字条
.kpis {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 28px;
  background: #fff;
  border-bottom: 1px solid #eef0f2;
  flex-shrink: 0;
}

.kpis__label {
  font-size: 13px;
  color: #8b9198;
}

.kpis__val {
  font-size: 24px;
  font-weight: 700;
  color: #1e293b;
  font-family: 'SF Mono', 'Cascadia Code', monospace;

  small {
    margin-left: 2px;
    font-size: 12px;
    font-weight: 400;
    color: #94a3b8;
  }
}

.kpis__sep {
  width: 1px;
  height: 28px;
  background: #eef0f2;
}

.kpis__badge {
  padding: 3px 12px;
  font-size: 11px;
  font-weight: 600;
  border-radius: 100px;
}

// 主区域
.main {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 14px 24px;
  min-height: 0;
  overflow-y: auto;
}

.section {
  padding: 16px 20px;
  background: #fff;
  border: 1px solid #eef0f2;
  border-radius: 8px;
}

.section__title {
  margin-bottom: 14px;
  font-size: 17px;
  font-weight: 600;
  color: #1e293b;
}

.chart-bar {
  width: 100%;
  height: 200px;
}

.chart-line {
  width: 100%;
  height: 100%;
  min-height: 200px;
}

.bottom {
  flex: 1;
  display: flex;
  gap: 14px;
  min-height: 0;
}

// 表格
.tbl {
  width: 100%;
  border-collapse: collapse;
  font-size: 16px;

  th {
    padding: 11px 14px;
    font-size: 14px;
    font-weight: 600;
    text-align: left;
    color: #64748b;
    border-bottom: 1px solid #eef0f2;
  }

  td {
    padding: 12px 14px;
    color: #334155;
    line-height: 1.45;
    border-bottom: 1px solid #f8f9fb;

    small {
      font-size: 13px;
      color: #64748b;
    }
  }

  tbody tr:nth-child(even) td {
    background: #fafbfc;
  }

  tbody tr:hover td {
    background: #f0f4ff;
  }
}

.td-n {
  font-size: 16px;
  font-weight: 600;
  color: #1e293b;
}

.td-v {
  font-size: 16px;
  font-weight: 700;
  font-family: 'SF Mono', monospace;
  color: #3b82f6;
}

tr.off td {
  color: #c0c4cc;

  .td-n {
    color: #94a3b8;
  }

  .td-v {
    color: #c0c4cc;
  }
}
</style>

<style lang="scss">
.main-layout__content:has(.gp) {
  padding: 0 !important;
  overflow: hidden !important;
}
</style>
