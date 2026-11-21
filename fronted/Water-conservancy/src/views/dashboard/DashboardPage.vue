<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import AiModelHealthRing from './components/AiModelHealthRing.vue'
import PhysicsGuardSummaryCard from './components/PhysicsGuardSummaryCard.vue'
import InterlockSummaryCard from './components/InterlockSummaryCard.vue'
import { fetchPhysicsGuardSummary } from '@/api/dispatchPage'
import { fetchRealtimeKpi, fetchDashboardAlarms } from '@/api/monitoring'
import { fetchInterlockDashboardSummary } from '@/api/gateaiSettings'
import { useVirtualSimulationStore } from '@/stores/virtualSimulation'
import { storeToRefs } from 'pinia'
import type { RealtimeKpi, DashboardAlarm } from '@/types/monitoring'
import type { PhysicsGuardSummary } from '@/types/dispatch'

const router = useRouter()
const physicsGuard = ref<PhysicsGuardSummary | null>(null)

const simStore = useVirtualSimulationStore()
const { active: simActive, derived: simDerived } = storeToRefs(simStore)

const data = ref<RealtimeKpi>({
  upstreamLevel: 378.52,
  downstreamLevel: 269.18,
  inflowRate: 6350,
  outflowRate: 5820,
  powerOutput: 4119,
  gateOpening: 34,
  capacity: 48.36,
  timestamp: '',
})

/** 虚拟仿真激活时，叠加仿真数据到 KPI */
const kpi = computed<RealtimeKpi>(() => {
  if (!simActive.value) return data.value
  const d = simDerived.value
  return {
    ...data.value,
    upstreamLevel: d.upstreamLevel,
    downstreamLevel: d.downstreamLevel,
    inflowRate: d.inflowRate,
    outflowRate: d.outflowRate,
    gateOpening: d.aggregateOpening,
  }
})

async function refreshKpi() {
  data.value = await fetchRealtimeKpi(1)
}
let kpiTimer: ReturnType<typeof setInterval>
onMounted(async () => {
  physicsGuard.value = (await fetchPhysicsGuardSummary()).data
  refreshKpi()
  kpiTimer = setInterval(refreshKpi, 5000)
  refreshInterlock()
  interlockTimer = setInterval(refreshInterlock, 60000)
  refreshAlarms()
})
onUnmounted(() => {
  clearInterval(kpiTimer)
  if (interlockTimer) clearInterval(interlockTimer)
})
const fmt = (v: number, d = 2) =>
  v.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d })

const expanded = ref<string | null>(null)
function toggle(id: string) {
  expanded.value = expanded.value === id ? null : id
}

const sections = computed(() => {
  const d = kpi.value
  return [
    {
      id: 'hydrology',
      title: '水情监测',
      path: '/dashboard/hydrology',
      preview: [
        { l: '上游水位', v: `${d.upstreamLevel.toFixed(2)} m` },
        { l: '入库流量', v: `${fmt(d.inflowRate, 0)} m³/s` },
      ],
      detail: [
        { l: '上游水位', v: d.upstreamLevel.toFixed(2), u: 'm' },
        { l: '下游水位', v: d.downstreamLevel.toFixed(2), u: 'm' },
        { l: '入库流量', v: fmt(d.inflowRate, 0), u: 'm³/s' },
        { l: '出库流量', v: fmt(d.outflowRate, 0), u: 'm³/s' },
        { l: '库容', v: d.capacity.toFixed(2), u: '亿m³' },
        { l: '水头', v: (d.upstreamLevel - d.downstreamLevel).toFixed(2), u: 'm' },
      ],
    },
    {
      id: 'gate',
      title: '阀门检测',
      path: '/dashboard/gate',
      preview: [
        { l: '平均开度', v: `${d.gateOpening.toFixed(1)} %` },
        { l: '运行中', v: '8 扇' },
      ],
      detail: [
        { l: '表孔 1-8#', v: d.gateOpening.toFixed(1), u: '%' },
        { l: '中孔 1-2#', v: '22.0', u: '%' },
        { l: '底孔 1-2#', v: '0.0', u: '%' },
        { l: '最近动作', v: '14:08', u: '' },
        { l: '动作耗时', v: '12', u: 's' },
        { l: '操作模式', v: 'AI-DQN', u: '' },
      ],
    },
    {
      id: 'power',
      title: '发电检测',
      path: '/dashboard/power',
      preview: [
        { l: '总出力', v: `${fmt(d.powerOutput, 0)} MW` },
        { l: '今日发电', v: '1,248 万kWh' },
      ],
      detail: [
        { l: '1#机组', v: '685', u: 'MW' },
        { l: '2#机组', v: '692', u: 'MW' },
        { l: '4#机组', v: '678', u: 'MW' },
        { l: '5#机组', v: '688', u: 'MW' },
        { l: '7#机组', v: '695', u: 'MW' },
        { l: '8#机组', v: '681', u: 'MW' },
      ],
    },
    {
      id: 'security',
      title: '安防检测',
      path: '/dashboard/security',
      preview: [
        { l: '摄像头', v: '5/6 在线' },
        { l: '今日告警', v: '2 条' },
      ],
      detail: [
        { l: '摄像头在线', v: '5/6', u: '路' },
        { l: '门禁已锁', v: '3/4', u: '扇' },
        { l: '今日告警', v: '2', u: '条' },
        { l: '巡检完成', v: '2/3', u: '条' },
        { l: '周界安全', v: '5/6', u: '区' },
        { l: '最近事件', v: '12:05', u: '' },
      ],
    },
  ]
})

// D-101: 阀门互锁约束状态（从 API 轮询）
const gateInterlocked = ref(false)
const interlockRuleName = ref('')
const alarms = ref<DashboardAlarm[]>([
  { time: '14:32', level: 'warning', msg: '1#边缘网关 CPU 使用率 88%', status: '未处理' },
  { time: '12:05', level: 'warning', msg: '下游河道周界入侵告警', status: '已确认' },
  { time: '08:22', level: 'critical', msg: '开关站门禁异常开启', status: '已处理' },
])
let interlockTimer: ReturnType<typeof setInterval> | null = null

async function refreshInterlock() {
  const summary = await fetchInterlockDashboardSummary(1)
  gateInterlocked.value = summary.trigger_24h > 0
  if (summary.recent_rule) {
    interlockRuleName.value = summary.recent_rule.name
  }
}

async function refreshAlarms() {
  alarms.value = await fetchDashboardAlarms()
}
</script>

<template>
  <div class="dp">
    <div class="hd">
      <div>
        <p class="hd__subtitle">
          向家坝水电站 ·
          {{
            new Date().toLocaleDateString('zh-CN', {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
              weekday: 'long',
            })
          }}
        </p>
      </div>
      <span class="hd__status"><i /> 系统运行中</span>
    </div>

    <div v-if="simActive" class="dp-sim-banner">
      <span>🔬 虚拟仿真联动中 — 水位 {{ simDerived.upstreamLevel.toFixed(1) }} m · 开度 {{ simDerived.aggregateOpening.toFixed(1) }}% · 入库 {{ simDerived.inflowRate }} m³/s</span>
    </div>

    <div class="hero">
      <div class="kpi" :class="{ 'kpi--sim': simActive }">
        <span>上游水位</span><b>{{ fmt(kpi.upstreamLevel) }}<small>m</small></b>
      </div>
      <div class="kpi" :class="{ 'kpi--sim': simActive }">
        <span>入库流量</span><b>{{ fmt(kpi.inflowRate, 0) }}<small>m³/s</small></b>
      </div>
      <div class="kpi">
        <span>发电功率</span
        ><b style="color: #3b82f6">{{ fmt(kpi.powerOutput, 0) }}<small>MW</small></b>
      </div>
      <div class="kpi" :class="{ 'kpi--sim': simActive }">
        <span>阀门开度</span
        ><b
          >{{ fmt(kpi.gateOpening, 1) }}<small>%</small
          ><span
            v-if="gateInterlocked"
            class="kpi__interlock"
            :title="'该阀门受互锁规则约束：' + interlockRuleName"
            >🔗</span
          ></b
        >
      </div>
      <div class="kpi">
        <span>库容</span><b>{{ fmt(kpi.capacity) }}<small>亿m³</small></b>
      </div>
    </div>

    <div class="main">
      <div class="col col--wide">
        <div class="list">
          <div
            v-for="s in sections"
            :key="s.id"
            class="sec"
            :class="{ on: expanded === s.id }"
          >
            <div class="sec__top" @click="toggle(s.id)">
              <span class="sec__t">{{ s.title }}</span>
              <span class="sec__pre" v-if="expanded !== s.id">
                <span v-for="p in s.preview" :key="p.l"
                  >{{ p.l }} <b>{{ p.v }}</b></span
                >
              </span>
              <span class="sec__arr">{{ expanded === s.id ? '收起' : '展开' }}</span>
            </div>
            <div class="sec__body" v-if="expanded === s.id">
              <div class="sec__grid">
                <div v-for="d in s.detail" :key="d.l" class="st">
                  <span class="st__l">{{ d.l }}</span>
                  <span class="st__v"
                    >{{ d.v }}<small>{{ d.u }}</small></span
                  >
                </div>
              </div>
              <button class="sec__btn" @click.stop="router.push(s.path)">
                进入 {{ s.title }} 完整页面 →
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="col col--side">
        <AiModelHealthRing />
        <PhysicsGuardSummaryCard v-if="physicsGuard" :data="physicsGuard" />
        <InterlockSummaryCard />
        <div class="panel">
          <h2>实时告警</h2>
          <div v-for="a in alarms" :key="a.time" class="al">
            <span class="al__d" :class="a.level" />
            <div>
              <div class="al__m">{{ a.msg }}</div>
              <div class="al__f">{{ a.time }} · {{ a.status }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.dp {
  height: calc(100vh - 56px);
  display: flex;
  flex-direction: column;
  background: #fff;
}

.hd {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 32px 40px 28px;
  background: #fff;
  border-bottom: 1px solid #eef0f2;
  flex-shrink: 0;
}

.hd__subtitle {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
  color: #1e293b;
  letter-spacing: 0.2px;
}

.hd__status {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 500;
  color: #16a34a;
}

.hd__status i {
  display: block;
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #22c55e;
  animation: pulse 2s infinite;
}

.hero {
  display: flex;
  padding: 0 40px;
  background: #fff;
  border-bottom: 1px solid #eef0f2;
  flex-shrink: 0;
}

.kpi {
  flex: 1;
  padding: 22px 0;
  text-align: center;
  border-right: 1px solid #f1f5f9;
}

.kpi:last-child {
  border-right: none;
}

.kpi span {
  display: block;
  margin-bottom: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #64748b;
}

.kpi b {
  font-size: 34px;
  font-weight: 700;
  line-height: 1;
  color: #0f172a;
  font-family: 'SF Mono', 'Cascadia Code', monospace;

  small {
    margin-left: 4px;
    font-size: 14px;
    font-weight: 400;
    color: #64748b;
  }
}

.kpi--sim {
  position: relative;
  &::after {
    content: '仿真';
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 2px 7px;
    font-size: 10px;
    font-weight: 600;
    color: #fff;
    background: #22c55e;
    border-radius: 4px;
    letter-spacing: 0.04em;
  }
}

.kpi--sim b {
  color: #16a34a !important;
}

.dp-sim-banner {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px 16px;
  margin: 0 40px;
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #15803d;
}

.kpi__interlock {
  margin-left: 6px;
  font-size: 14px;
  cursor: help;
  opacity: 0.85;
  filter: grayscale(0.2);

  &:hover {
    opacity: 1;
    filter: none;
  }
}

.main {
  flex: 1;
  display: flex;
  gap: 20px;
  padding: 20px 40px;
  min-height: 0;
  overflow: hidden;
}

.col--wide {
  flex: 1.5;
  overflow-y: auto;
}

.col--side {
  flex: 1;
  overflow-y: auto;
}

.list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.sec {
  overflow: hidden;
  background: #fff;
  border: 1px solid #eef0f4;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.15s;

  &:hover {
    border-color: #dde4f0;
  }

  &.on {
    border-color: #d0d8e8;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.03);
  }
}

.sec__top {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 22px 24px;
}

.sec__t {
  min-width: 88px;
  font-size: 17px;
  font-weight: 600;
  color: #1e293b;
}

.sec__pre {
  display: flex;
  flex: 1;
  gap: 24px;
  span {
    font-size: 14px;
    color: #64748b;
  }
  b {
    margin-left: 6px;
    font-weight: 600;
    color: #334155;
  }
}

.sec__arr {
  font-size: 14px;
  font-weight: 500;
  color: #64748b;
  white-space: nowrap;
}

.sec.on .sec__arr {
  color: #3b82f6;
}

.sec__body {
  padding: 0 24px 22px 112px;
}

.sec__grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-bottom: 14px;
}

.st {
  padding: 14px 8px;
  text-align: center;
  background: #f8fafc;
  border-radius: 6px;
}

.st__l {
  display: block;
  margin-bottom: 6px;
  font-size: 13px;
  color: #64748b;
}

.st__v {
  font-size: 22px;
  font-weight: 700;
  color: #1e293b;
  font-family: 'SF Mono', monospace;
  small {
    margin-left: 2px;
    font-size: 13px;
    font-weight: 400;
    color: #64748b;
  }
}

.sec__btn {
  padding: 10px 18px;
  font-size: 14px;
  font-weight: 500;
  color: #64748b;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  cursor: pointer;
  &:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
  }
}

.panel {
  padding: 22px 24px;
  background: #fff;
  border: 1px solid #eef0f4;
  border-radius: 8px;
  & + .panel {
    margin-top: 12px;
  }
}

.panel h2 {
  margin: 0 0 16px;
  font-size: 17px;
  font-weight: 600;
  color: #1e293b;
}

.al {
  display: flex;
  gap: 10px;
  padding: 12px 0;
  line-height: 1.45;

  & + & {
    border-top: 1px solid #f1f5f9;
  }
}

.al__d {
  width: 8px;
  height: 8px;
  margin-top: 6px;
  border-radius: 50%;
  flex-shrink: 0;
  background: #f59e0b;

  &.critical {
    background: #ef4444;
  }

  &.warning {
    background: #f59e0b;
  }
}

.al__m {
  font-size: 15px;
  font-weight: 500;
  color: #1e293b;
}

.al__f {
  margin-top: 4px;
  font-size: 14px;
  color: #64748b;
}

@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }

  50% {
    opacity: 0.3;
  }
}
</style>

<style lang="scss">
.main-layout__content:has(.dp) {
  padding: 0 !important;
  overflow: hidden !important;
}
</style>
