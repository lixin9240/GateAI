<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ElButton, ElSlider, ElTag, ElMessage } from 'element-plus'
import GlassPanel3D from '@/components/cockpit/GlassPanel3D.vue'
import WaterColumn2D from '@/components/cockpit/WaterColumn2D.vue'
import FlowGauge2D from '@/components/cockpit/FlowGauge2D.vue'
import DamSectionDiagram from '@/views/dispatch/components/DamSectionDiagram.vue'
import { useVirtualSimulationStore } from '@/stores/virtualSimulation'
import { useDispatchStore } from '@/stores/dispatch'
import { storeToRefs } from 'pinia'
import { isGateOnline } from '@/utils/gateControl'
import { fetchRealtimeKpi } from '@/api/monitoring'

const router = useRouter()
const simStore = useVirtualSimulationStore()
const dispatchStore = useDispatchStore()

const { active, locked, upstreamLevel, downstreamLevel, rainfall, derived } = storeToRefs(simStore)
const { gates, selectedGateId, status, canManualControl } = storeToRefs(dispatchStore)

const aggregateOpening = computed(() => derived.value.aggregateOpening)

const displayGates = computed(() => {
  void derived.value.aggregateOpening
  return active.value ? simStore.overlayGates(gates.value) : gates.value
})

const waterTrendLabel = computed(() => {
  if (derived.value.waterTrend === 'up') return '趋升'
  if (derived.value.waterTrend === 'down') return '趋降'
  return '平衡'
})

const PRESETS = [
  { key: 'normal', label: '正常蓄水', up: 380.0, down: 278.4, rain: 0 },
  { key: 'flood', label: '汛限偏高', up: 381.5, down: 280.0, rain: 35 },
  { key: 'dry', label: '接近死水位', up: 374.5, down: 276.0, rain: 0 },
] as const

async function refreshPreview() {
  await dispatchStore.refreshGates()
}

function onParamChange() {
  if (!locked.value) {
    simStore.applySimulation()
    refreshPreview()
  }
}

function applyPreset(up: number, down: number, rain: number) {
  upstreamLevel.value = up
  downstreamLevel.value = down
  rainfall.value = rain
  if (!locked.value) {
    simStore.applySimulation()
    refreshPreview()
  }
  ElMessage.success('已套用预设工况')
}

function handleApply() {
  simStore.applySimulation()
  refreshPreview()
  ElMessage.success('仿真参数已应用，全系统数据已联动')
}

function handleReset() {
  simStore.resetSimulation()
  refreshPreview()
  ElMessage.info('已重置为实时监测基准值')
}

function toggleLock() {
  simStore.toggleLock()
  ElMessage.info(locked.value ? '参数已锁定' : '参数已解锁')
}

function goNodeControl() {
  if (!ensureManualMode()) return
  router.push('/dispatch/gates')
}

function goTwin() {
  router.push('/simulation')
}

function ensureManualMode() {
  if (!canManualControl.value) {
    ElMessage.warning('节点控制需手动模式，请先在运行控制页切换')
    router.push('/dispatch/control')
    return false
  }
  return true
}

watch([upstreamLevel, downstreamLevel, rainfall], () => {
  if (active.value && !locked.value) refreshPreview()
})

onMounted(async () => {
  await dispatchStore.refreshCore()
  try {
    const kpi = await fetchRealtimeKpi(1)
    simStore.initBaselineFromKpi(kpi)
  } catch {
    /* 无 KPI 时沿用向家坝默认基准 */
  }
  if (!selectedGateId.value && gates.value.length) {
    selectedGateId.value =
      gates.value.find((g) => isGateOnline(g.status))?.id ?? gates.value[0].id
  }
})
</script>

<template>
  <div class="vsim-page">
    <div class="vsim-guide">
      <div class="vsim-guide__main">
        <strong>虚拟仿真</strong>
        <span>手动调参预览（非 AI 训练）。调节上下游水位/降雨 → 预览联动 → 应用后同步至节点控制、监控大屏（数字孪生页保持独立）。</span>
      </div>
      <div class="vsim-guide__tags">
        <ElTag v-if="active" type="success" size="large">仿真生效中</ElTag>
        <ElTag v-else type="info" size="large">待应用</ElTag>
        <ElTag v-if="locked" type="warning" size="large">参数已锁定</ElTag>
      </div>
    </div>

    <div class="vsim-steps">
      <div class="vsim-step">
        <b>1</b>
        <span>左侧调上/下游水位与降雨</span>
      </div>
      <div class="vsim-step">
        <b>2</b>
        <span>右侧看水位柱、流量、坝体剖面</span>
      </div>
      <div class="vsim-step">
        <b>3</b>
        <span>点「一键应用仿真」全系统联动</span>
      </div>
      <div class="vsim-step vsim-step--links">
        <ElButton size="small" @click="goNodeControl">节点控制</ElButton>
        <ElButton size="small" type="primary" plain @click="goTwin">数字孪生</ElButton>
      </div>    </div>

    <div class="vsim-layout">
      <GlassPanel3D title="虚拟仿真参数调节" fill class="vsim-panel vsim-panel--ctrl">
        <p class="vsim-hint">拖动滑块即时预览；锁定后参数不可改。应用后其他页面读取同一套仿真状态。</p>

        <div class="vsim-presets">
          <span class="vsim-presets__label">快捷工况</span>
          <div class="vsim-presets__row">
            <ElButton
              v-for="p in PRESETS"
              :key="p.key"
              size="small"
              :disabled="locked"
              @click="applyPreset(p.up, p.down, p.rain)"
            >
              {{ p.label }}
            </ElButton>
          </div>
        </div>

        <div class="vsim-param">
          <div class="vsim-param__head">
            <span class="vsim-param__label">上游水位</span>
            <strong class="vsim-param__value">{{ upstreamLevel.toFixed(1) }} <small>m</small></strong>
          </div>
          <ElSlider
            v-model="upstreamLevel"
            :min="370"
            :max="383.5"
            :step="0.1"
            :disabled="locked"
            show-input
            @change="onParamChange"
          />
        </div>

        <div class="vsim-param">
          <div class="vsim-param__head">
            <span class="vsim-param__label">下游水位</span>
            <strong class="vsim-param__value">{{ downstreamLevel.toFixed(1) }} <small>m</small></strong>
          </div>
          <ElSlider
            v-model="downstreamLevel"
            :min="260"
            :max="290"
            :step="0.1"
            :disabled="locked"
            show-input
            @change="onParamChange"
          />
        </div>

        <div class="vsim-param">
          <div class="vsim-param__head">
            <span class="vsim-param__label">降雨量</span>
            <strong class="vsim-param__value">{{ rainfall.toFixed(1) }} <small>mm/h</small></strong>
          </div>
          <ElSlider
            v-model="rainfall"
            :min="0"
            :max="120"
            :step="0.5"
            :disabled="locked"
            show-input
            @change="onParamChange"
          />
        </div>

        <div class="vsim-ref">
          <div><span>基准上游</span><strong>{{ status.upstreamLevel.toFixed(1) }} m</strong></div>
          <div><span>基准下游</span><strong>{{ simStore.baseline.downstreamLevel.toFixed(1) }} m</strong></div>
          <div><span>基准入库</span><strong>{{ status.flowRate }} m³/s</strong></div>
        </div>

        <div class="vsim-actions">
          <ElButton size="large" @click="handleReset">重置参数</ElButton>
          <ElButton size="large" :type="locked ? 'warning' : 'default'" @click="toggleLock">
            {{ locked ? '解锁调节' : '锁定模拟' }}
          </ElButton>
          <ElButton size="large" type="primary" @click="handleApply">一键应用仿真</ElButton>
        </div>
      </GlassPanel3D>

      <GlassPanel3D title="实时仿真预览" fill class="vsim-panel vsim-panel--preview">
        <div class="vsim-columns">
          <WaterColumn2D
            label="上游水位"
            :value="derived.upstreamLevel"
            unit="m"
            :min="110"
            :max="200"
            size="lg"
          />
          <WaterColumn2D
            label="下游水位"
            :value="derived.downstreamLevel"
            unit="m"
            :min="80"
            :max="180"
            size="lg"
          />
          <FlowGauge2D
            label="入库流量"
            :value="derived.inflowRate"
            unit="m³/s"
            :min="0"
            :max="3500"
            variant="inflow"
            size="lg"
          />
          <FlowGauge2D
            label="出库流量"
            :value="derived.outflowRate"
            unit="m³/s"
            :min="0"
            :max="3500"
            variant="outflow"
            size="lg"
          />
        </div>

        <div class="vsim-metrics">
          <div class="vsim-metric">
            <span>聚合总开度</span>
            <strong>{{ aggregateOpening }}%</strong>
          </div>
          <div class="vsim-metric">
            <span>水头差</span>
            <strong>{{ (derived.upstreamLevel - derived.downstreamLevel).toFixed(1) }} <small>m</small></strong>
          </div>
          <div class="vsim-metric">
            <span>流量平衡</span>
            <strong>{{ derived.inflowRate - derived.outflowRate > 0 ? '+' : '' }}{{ derived.inflowRate - derived.outflowRate }} <small>m³/s</small></strong>
          </div>
          <div class="vsim-metric">
            <span>水位趋势</span>
            <strong>{{ waterTrendLabel }}</strong>
          </div>
        </div>

        <div class="vsim-dam-wrap">
          <DamSectionDiagram
            :gates="displayGates"
            :selected-gate-id="selectedGateId"
            :upstream-level="derived.upstreamLevel"
            :downstream-level="derived.downstreamLevel"
            :inflow-rate="derived.inflowRate"
            :outflow-rate="derived.outflowRate"
            @select="(id) => (selectedGateId = id)"
          />
        </div>
      </GlassPanel3D>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;

.vsim-page {
  @include cockpit-page-base;
  @include cockpit-typography;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px 24px 28px;
  min-height: calc(100vh - 110px);
}

.vsim-guide {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px 20px;
  padding: 14px 20px;
  background: rgba(24, 144, 255, 0.08);
  border: 1px solid rgba(24, 144, 255, 0.18);
  border-radius: 12px;
  font-size: $cockpit-font-sm;
  color: $cockpit-text;

  &__main {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
  }

  strong { font-size: $cockpit-font-md; color: #1890ff; }

  &__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
}

.vsim-steps {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px 20px;
  padding: 12px 16px;
  background: #f8fafc;
  border: 1px solid #eef2f6;
  border-radius: 12px;
}

.vsim-step {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: $cockpit-font-sm;
  color: $cockpit-text-dim;

  b {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #1890ff;
    color: #fff;
    font-size: 12px;
  }

  &--links {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 8px;

    :deep(.el-button) {
      min-width: 96px;
      margin: 0;
    }
  }
}

.vsim-layout {
  flex: 1;
  display: grid;
  grid-template-columns: 360px 1fr;
  gap: 20px;
  min-height: 520px;

  @media (max-width: 1100px) { grid-template-columns: 1fr; }
}

.vsim-panel--ctrl :deep(.glass-panel__body) {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: 24px;
  min-height: 520px;
}

.vsim-panel--preview :deep(.glass-panel__body) {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 16px 20px 20px;
  min-height: 0;
}

.vsim-dam-wrap {
  flex: 1;
  min-height: 420px;
  margin-top: 4px;
  padding-top: 10px;
  border-top: 1px solid #eef2f6;

  :deep(.dam-section) {
    height: 100%;
    min-height: 420px;
  }
}

.vsim-columns {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  padding: 8px 12px 4px;
  background: linear-gradient(180deg, #f8fbff 0%, #fff 100%);
  border: 1px solid #eef2f6;
  border-radius: 14px;

  @media (max-width: 1100px) { grid-template-columns: repeat(2, 1fr); }
  @media (max-width: 600px) { grid-template-columns: 1fr; max-width: 280px; margin: 0 auto; }
}

.vsim-hint {
  margin: 0;
  font-size: $cockpit-font-sm;
  color: $cockpit-text-dim;
  line-height: 1.6;
}

.vsim-presets {
  &__label {
    display: block;
    font-size: $cockpit-font-sm;
    font-weight: 600;
    color: $cockpit-text;
    margin-bottom: 8px;
  }

  &__row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;

    :deep(.el-button) {
      width: 100%;
      margin: 0;
    }
  }
}

.vsim-ref {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  padding: 12px 14px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid #eef2f6;
  font-size: $cockpit-font-sm;

  div {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  span { color: $cockpit-text-dim; }
  strong { color: #1890ff; font-family: monospace; }
}

.vsim-param__head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 12px;
}

.vsim-param__label {
  font-size: $cockpit-font-md;
  font-weight: 600;
  color: $cockpit-text;
}

.vsim-param__value {
  font-size: 32px;
  font-weight: 700;
  font-family: monospace;
  color: #1890ff;

  small { font-size: 14px; color: $cockpit-text-dim; }
}

.vsim-actions {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: auto;
  padding-top: 8px;

  .el-button {
    width: 100%;
    height: 44px;
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    border-radius: 10px;
  }

  .el-button--primary {
    height: 48px;
    box-shadow: 0 4px 14px rgba(24, 144, 255, 0.28);
  }
}

.vsim-metrics {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;

  @media (max-width: 900px) { grid-template-columns: repeat(2, 1fr); }
}

.vsim-metric {
  padding: 16px 18px;
  background: #f8fafc;
  border: 1px solid #eef2f6;
  border-radius: 12px;

  span {
    display: block;
    font-size: 13px;
    color: $cockpit-text-dim;
    margin-bottom: 6px;
  }

  strong {
    font-size: 24px;
    font-weight: 700;
    font-family: monospace;
    color: $cockpit-text;

    small { font-size: 12px; font-weight: 500; color: $cockpit-text-dim; }
  }
}
</style>
