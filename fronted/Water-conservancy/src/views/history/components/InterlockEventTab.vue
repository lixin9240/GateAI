<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElTag, ElDrawer, ElMessage } from 'element-plus'
import { fetchInterlockLogs, fetchModelHealthOverview } from '@/api/gateaiSettings'
import type { GateInterlockLog } from '@/types/gateai'

interface InterlockEvent {
  id: number
  time: string
  reservoir: string
  rule: string
  upstreamLevel: number
  downstreamLevel: number
  inflowRate?: number
  gatesBefore: number[]
  gatesAfter: number[]
  modifiedGate: number
  decisionId: number | null
  actionDetail: { trigger: string; constraint: string; change: string }
}

const reservoirId = ref(1)
const reservoirOptions = ref<{ value: number; label: string }[]>([])
const loading = ref(false)
const events = ref<InterlockEvent[]>([])
const filterRule = ref('')
const ruleOptions = ref<string[]>([])
const drawerVisible = ref(false)
const drawerEvent = ref<InterlockEvent | null>(null)

function defaultDateRange() {
  const end = new Date()
  const start = new Date()
  start.setDate(start.getDate() - 7)
  return {
    start: start.toISOString().slice(0, 10),
    end: end.toISOString().slice(0, 10),
  }
}

const dateRange = ref(defaultDateRange())
const todayISO = computed(() => new Date().toISOString().slice(0, 10))
const endMin = computed(() => dateRange.value.start || undefined)

function mapLogToEvent(log: GateInterlockLog): InterlockEvent {
  const before = log.openings_before ?? []
  const after = log.openings_after ?? []
  const changedIndex =
    log.changed_gates?.[0] ?? before.findIndex((value, index) => value !== after[index])
  const modifiedGate = changedIndex >= 0 ? changedIndex : 0

  return {
    id: log.id,
    time: log.trigger_time?.replace('T', ' ').slice(0, 19) ?? '--',
    reservoir: log.reservoir_name ?? '--',
    rule: log.rule_name ?? log.rule_code ?? '--',
    upstreamLevel: log.upstream_level ?? 0,
    downstreamLevel: log.downstream_level ?? 0,
    inflowRate: log.inflow_rate,
    gatesBefore: before,
    gatesAfter: after,
    modifiedGate,
    decisionId: log.decision_id ?? null,
    actionDetail: {
      trigger: log.reason ?? '阀门开度变化触发',
      constraint: log.rule_name ?? '互锁约束',
      change:
        changedIndex >= 0 && before.length > 0
          ? `阀门${changedIndex + 1} ${before[changedIndex]}% → ${after[changedIndex]}%`
          : log.reason ?? '—',
    },
  }
}

async function loadReservoirs() {
  try {
    const overview = await fetchModelHealthOverview()
    if (overview.length > 0) {
      reservoirOptions.value = overview.map((r) => ({
        value: Number(r.reservoir_id),
        label: r.reservoir_name || `水库 #${r.reservoir_id}`,
      }))
      if (!reservoirOptions.value.some((r) => r.value === Number(reservoirId.value))) {
        reservoirId.value = reservoirOptions.value[0]?.value ?? 1
      }
    }
  } catch {
    if (reservoirOptions.value.length === 0) {
      reservoirOptions.value = [{ value: 1, label: '水库 #1' }]
    }
  }
}

async function loadEvents() {
  loading.value = true
  try {
    const logs = await fetchInterlockLogs({
      reservoirId: Number(reservoirId.value),
      startTime: dateRange.value.start,
      endTime: dateRange.value.end,
      pageSize: 100,
    })
    events.value = logs.map(mapLogToEvent)
    ruleOptions.value = [...new Set(events.value.map((item) => item.rule))]
    filterRule.value = ''
  } catch (err) {
    console.warn('[InterlockEventTab] load failed:', err)
    ElMessage.error('加载互锁日志失败')
    events.value = []
    ruleOptions.value = []
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await loadReservoirs()
  await loadEvents()
})

const filtered = computed(() => {
  if (!filterRule.value) return events.value
  return events.value.filter((item) => item.rule === filterRule.value)
})

function showDetail(event: InterlockEvent) {
  drawerEvent.value = event
  drawerVisible.value = true
}

function barWidth(value: number) {
  return Math.max(4, value) + 'px'
}
</script>

<template>
  <div class="ie" v-loading="loading">
    <div class="ie__filters">
      <select v-model="reservoirId" class="ie__sel" @change="loadEvents">
        <option v-for="r in reservoirOptions" :key="r.value" :value="r.value">
          {{ r.label }}
        </option>
      </select>
      <input v-model="dateRange.start" type="date" class="ie__date" :max="todayISO" @change="loadEvents" />
      <span class="ie__sep">—</span>
      <input v-model="dateRange.end" type="date" class="ie__date" :max="todayISO" :min="endMin" @change="loadEvents" />
      <select v-model="filterRule" class="ie__sel">
        <option value="">全部规则</option>
        <option v-for="r in ruleOptions" :key="r" :value="r">{{ r }}</option>
      </select>
      <button class="ie__btn" :disabled="loading" @click="loadEvents">刷新</button>
    </div>

    <div class="ie__table-wrap">
      <table class="ie__tbl">
        <thead>
          <tr>
            <th>触发时间</th>
            <th>水库</th>
            <th>规则名称</th>
            <th>水位快照</th>
            <th>阀门开度变化</th>
            <th>关联调度</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in filtered" :key="e.id">
            <td>{{ e.time }}</td>
            <td>{{ e.reservoir }}</td>
            <td>
              <ElTag size="small" type="warning">{{ e.rule }}</ElTag>
            </td>
            <td>
              上{{ e.upstreamLevel }}m / 下{{ e.downstreamLevel }}m
              <span v-if="e.inflowRate" class="ie__flow"> · {{ e.inflowRate }}m³/s</span>
            </td>
            <td>
              <div class="ie__gates">
                <div v-for="(b, i) in e.gatesBefore" :key="i" class="ie__gate">
                  <div class="ie__gate-bar" :class="{ mod: i === e.modifiedGate }">
                    <span class="ie__gate-before" :style="{ width: barWidth(b) }" />
                    <span class="ie__gate-arrow">→</span>
                    <span class="ie__gate-after" :style="{ width: barWidth(e.gatesAfter[i]) }" />
                  </div>
                  <span v-if="i === e.modifiedGate" class="ie__gate-diff">
                    {{ e.gatesAfter[i] - e.gatesBefore[i] > 0 ? '+' : ''
                    }}{{ (e.gatesAfter[i] - e.gatesBefore[i]).toFixed(1) }}%
                  </span>
                </div>
              </div>
            </td>
            <td>
              <span v-if="e.decisionId" class="ie__link">#{{ e.decisionId }}</span>
              <span v-else class="ie__na">—</span>
            </td>
            <td><button class="ie__detail-btn" @click="showDetail(e)">详情</button></td>
          </tr>
          <tr v-if="!loading && filtered.length === 0">
            <td colspan="7" class="ie__empty">暂无互锁触发记录</td>
          </tr>
        </tbody>
      </table>
    </div>

    <ElDrawer v-model="drawerVisible" title="互锁事件详情" size="480px">
      <template v-if="drawerEvent">
        <div class="ie__drawer">
          <div class="ie__drawer-row">
            <span>触发时间</span><b>{{ drawerEvent.time }}</b>
          </div>
          <div class="ie__drawer-row">
            <span>水库</span><b>{{ drawerEvent.reservoir }}</b>
          </div>
          <div class="ie__drawer-row">
            <span>规则</span><ElTag size="small" type="warning">{{ drawerEvent.rule }}</ElTag>
          </div>
          <div class="ie__drawer-row">
            <span>上游水位</span><b>{{ drawerEvent.upstreamLevel }}m</b>
          </div>
          <div class="ie__drawer-row">
            <span>下游水位</span><b>{{ drawerEvent.downstreamLevel }}m</b>
          </div>
          <div v-if="drawerEvent.inflowRate" class="ie__drawer-row">
            <span>入库流量</span><b>{{ drawerEvent.inflowRate }}m³/s</b>
          </div>
          <div class="ie__drawer-row">
            <span>触发原因</span><b>{{ drawerEvent.actionDetail.trigger }}</b>
          </div>
          <div class="ie__drawer-row">
            <span>约束动作</span><b>{{ drawerEvent.actionDetail.constraint }}</b>
          </div>
          <div class="ie__drawer-row">
            <span>变化</span><b style="color: #f97316">{{ drawerEvent.actionDetail.change }}</b>
          </div>
          <div class="ie__drawer-row">
            <span>关联调度决策</span>
            <b v-if="drawerEvent.decisionId">#{{ drawerEvent.decisionId }}</b>
            <span v-else>—</span>
          </div>
        </div>
      </template>
    </ElDrawer>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/text-mixins.scss' as *;
.ie {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 18px 24px;
  gap: 12px;

  &__filters {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
    flex-wrap: wrap;
  }
  &__sel {
    padding: 8px 12px;
    font-size: 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
  }
  &__date {
    padding: 8px 12px;
    font-size: 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
  }
  &__sep {
    color: #94a3b8;
    font-size: 14px;
  }
  &__btn {
    padding: 8px 16px;
    font-size: 14px;
    color: #fff;
    background: #3b82f6;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  }
  &__table-wrap {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #eef0f2;
  }
  &__tbl {
    width: 100%;
    border-collapse: collapse;
    th {
      padding: 10px 12px;
      @include text-table-header;
      text-align: left;
      border-bottom: 1px solid #eef0f2;
      position: sticky;
      top: 0;
      background: #fff;
      z-index: 1;
    }
    td {
      padding: 10px 12px;
      @include text-table-cell;
      border-bottom: 1px solid #f8fafc;
      vertical-align: middle;
    }
    td:first-child {
      @include text-timestamp-mono;
    }
    tr:hover td {
      background: #f8fafc;
    }
  }
  &__flow {
    color: #94a3b8;
    font-size: 13px;
  }
  &__gates {
    display: flex;
    gap: 12px;
  }
  &__gate {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
  }
  &__gate-bar {
    display: flex;
    align-items: center;
    gap: 4px;
    &.mod {
      padding: 1px 4px;
      background: #fff7ed;
      border-radius: 4px;
    }
  }
  &__gate-before {
    display: block;
    height: 10px;
    background: #cbd5e1;
    border-radius: 3px;
    min-width: 4px;
  }
  &__gate-arrow {
    font-size: 12px;
    color: #94a3b8;
  }
  &__gate-after {
    display: block;
    height: 10px;
    background: #3b82f6;
    border-radius: 3px;
    min-width: 4px;
  }
  &__gate-diff {
    font-size: 11px;
    font-weight: 700;
    color: #f97316;
  }
  &__link {
    color: #3b82f6;
    font-weight: 600;
    cursor: pointer;
    &:hover {
      text-decoration: underline;
    }
  }
  &__na {
    color: #94a3b8;
  }
  &__empty {
    text-align: center;
    color: #94a3b8;
    padding: 24px !important;
  }
  &__detail-btn {
    padding: 4px 12px;
    font-size: 13px;
    color: #3b82f6;
    background: #eff6ff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    &:hover {
      background: #dbeafe;
    }
  }

  &__drawer-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 14px;
    > span {
      color: #64748b;
    }
    > b {
      font-weight: 600;
      color: #1e293b;
      text-align: right;
      max-width: 62%;
    }
  }
}
</style>
