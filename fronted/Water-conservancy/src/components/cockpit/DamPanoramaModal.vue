<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import ThreeDamScene from '@/components/cockpit/ThreeDamScene.vue'
import {
  SIMULATION_SCENE_OPTIONS,
  SIMULATION_SCENE_MAP,
  SCENARIO_LIBRARY_LABEL,
  getSimulationSceneLabel,
  clampSimulationLevel,
  SIMULATION_LEVEL_MIN,
  SPEED_OPTIONS,
} from '@/constants/simulation'
import type { SimulationScene, SimulationSpeed, SimulationRealtimeData, SimulationScenarioItem } from '@/types/simulation'

const props = defineProps<{
  visible: boolean
  waterLevel: number
  downstreamLevel: number
  gateOpening: number
  gateOpenings: number[]
  flowRate: number
  simScene: SimulationScene
  simSpeed: SimulationSpeed
  simStatus: SimulationRealtimeData
  canStart: boolean
  canPause: boolean
  elapsedLabel: string
  sceneLabel: string
  statusLabel: string
  statusColor: string
  levelStatusColor: string
  levelStatusLabel: string
  selectedGateIndex?: number
  selectedGateOpening?: number
  selectedGateFlow?: number
  scenarios?: SimulationScenarioItem[]
  selectedScenarioId?: number | null
  /** 调度方案预览 — 仅展示 3D，隐藏仿真控制 */
  preview?: boolean
  previewPlanName?: string
  previewSafetyScore?: number
  rainfall?: number
}>()

const emit = defineEmits<{
  close: []
  start: []
  pause: []
  reset: []
  'update:simScene': [scene: SimulationScene]
  'update:simSpeed': [speed: SimulationSpeed]
  'update:gateOpening': [opening: number]
  'update:gate-opening-at': [index: number, opening: number]
  'update:water-level': [level: number]
  'update:rainfall': [mm: number]
  'control-active': [active: boolean]
  'select-scenario': [item: SimulationScenarioItem]
  'gate-select': [index: number]
}>()

function safeNum(v: number, fallback = 0) {
  return Number.isFinite(v) ? v : fallback
}

const safeGateOpening = computed(() => safeNum(props.gateOpening, 100))
const safeWaterLevel = computed(() => safeNum(props.waterLevel, 380))
const clampedModalWaterLevel = computed(() => clampSimulationLevel(safeWaterLevel.value))
const safeDownstreamLevel = computed(() => safeNum(props.downstreamLevel, 277))
const safeFlowRate = computed(() => safeNum(props.flowRate, 1911))
const safeGateOpenings = computed(() =>
  props.gateOpenings?.length
    ? props.gateOpenings.map((v) => safeNum(v, 100))
    : Array.from({ length: 5 }, () => safeGateOpening.value),
)
const hasGateSelected = computed(() => (props.selectedGateIndex ?? -1) >= 0)
const activeGateNo = computed(() => (props.selectedGateIndex ?? -1) + 1)
const activeGateOpening = computed(() => safeNum(props.selectedGateOpening ?? 0, 0))
const activeGateFlow = computed(() => safeNum(props.selectedGateFlow ?? 0, 0))
const safeRainfall = computed(() => safeNum(props.rainfall ?? 0, 0))

function onSliderPointer(active: boolean) {
  emit('control-active', active)
}

const sceneRef = ref<InstanceType<typeof ThreeDamScene> | null>(null)
const sceneInfo = computed(() => SIMULATION_SCENE_MAP[props.simScene])
const isScenarioLibrary = computed(() => props.simScene === 'custom')
const scenarioOptions = computed(() => props.scenarios ?? [])
const selectedScenarioName = computed(() =>
  scenarioOptions.value.find((s) => s.id === props.selectedScenarioId)?.name ?? '',
)
const simActive = computed(
  () =>
    props.simStatus.status === 'running' ||
    props.simStatus.status === 'paused' ||
    props.simStatus.status === 'finished',
)

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape' && props.visible) emit('close')
}

watch(
  () => props.visible,
  (v) => {
    document.body.style.overflow = v ? 'hidden' : ''
    if (v) {
      setTimeout(() => sceneRef.value?.resizeScene(), 80)
      setTimeout(() => sceneRef.value?.resizeScene(), 420)
    }
  },
)

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})

defineExpose({
  focusSimulationView: () => sceneRef.value?.focusSimulationView(),
  focusGateView: (index: number) => sceneRef.value?.focusGateView(index),
  resizeScene: () => sceneRef.value?.resizeScene(),
})
</script>

<template>
  <Teleport to="body">
    <Transition name="panorama-fade">
      <div
        v-if="visible"
        class="sim-modal"
        :class="{ 'sim-modal--running': simStatus.status === 'running' }"
        role="dialog"
        aria-modal="true"
        aria-label="向家坝 BIM 预览"
      >
        <div class="sim-modal__backdrop" @click="emit('close')" />

        <div class="sim-modal__panel">
          <!-- 左侧：仿真控制 / 方案预览 -->
          <aside class="sim-modal__sidebar">
            <div class="sim-modal__sidebar-head">
              <h2>{{ preview ? '方案 BIM 预览' : '仿真推演' }}</h2>
              <span class="sim-modal__status" :style="{ color: statusColor }">
                {{ preview ? '调度预览' : statusLabel }}
              </span>
            </div>

            <template v-if="preview">
              <div class="sim-modal__scene-brief">
                <h3>{{ previewPlanName || '当前方案' }}</h3>
                <p>基于 AI 调度方案预览阀门开度与上下游水位效果</p>
              </div>

              <div class="sim-modal__kpis">
                <div class="sim-modal__kpi">
                  <small>方案开度</small>
                  <strong>{{ gateOpening }}%</strong>
                </div>
                <div class="sim-modal__kpi">
                  <small>预期上游水位</small>
                  <strong :style="{ color: levelStatusColor }"
                    >{{ waterLevel.toFixed(2) }} m</strong
                  >
                  <em>{{ levelStatusLabel }}</em>
                </div>
                <div class="sim-modal__kpi">
                  <small>下游尾水</small>
                  <strong>{{ downstreamLevel.toFixed(2) }} m</strong>
                </div>
                <div class="sim-modal__kpi">
                  <small>入库流量</small>
                  <strong>{{ flowRate }} m³/s</strong>
                </div>
                <div v-if="previewSafetyScore != null" class="sim-modal__kpi">
                  <small>安全评分</small>
                  <strong :style="{ color: previewSafetyScore >= 90 ? '#22c55e' : '#f59e0b' }">
                    {{ previewSafetyScore }}
                  </strong>
                </div>
              </div>
              <p class="sim-modal__hint">拖动旋转视角 · 滚轮缩放 · Esc 关闭</p>
            </template>

            <template v-else>
              <div class="sim-modal__scene-brief">
                <h3>{{ isScenarioLibrary && selectedScenarioName ? selectedScenarioName : sceneInfo?.label }}</h3>
                <p>
                  {{
                    isScenarioLibrary
                      ? `从${SCENARIO_LIBRARY_LABEL}加载的工况参数，可在左侧列表切换场景`
                      : sceneInfo?.description
                  }}
                </p>
              </div>

              <div class="sim-modal__field">
                <label>仿真场景</label>
                <select
                  class="sim-modal__select"
                  :value="simScene"
                  :disabled="!canStart"
                  @change="
                    emit(
                      'update:simScene',
                      ($event.target as HTMLSelectElement).value as SimulationScene,
                    )
                  "
                >
                  <option v-for="s in SIMULATION_SCENE_OPTIONS" :key="s.value" :value="s.value">
                    {{ getSimulationSceneLabel(s.value) }}
                  </option>
                </select>
              </div>

              <div v-if="isScenarioLibrary" class="sim-modal__field">
                <label>{{ SCENARIO_LIBRARY_LABEL }}</label>
                <select
                  class="sim-modal__select"
                  :value="selectedScenarioId ?? ''"
                  :disabled="!canStart || !scenarioOptions.length"
                  @change="
                    (e) => {
                      const id = Number((e.target as HTMLSelectElement).value)
                      const item = scenarioOptions.find((s) => s.id === id)
                      if (item) emit('select-scenario', item)
                    }
                  "
                >
                  <option v-if="!scenarioOptions.length" value="" disabled>暂无场景，请先在左侧新建</option>
                  <option v-for="s in scenarioOptions" :key="s.id" :value="s.id">
                    #{{ s.id }} · {{ s.name }}
                  </option>
                </select>
              </div>

              <div class="sim-modal__field">
                <label>仿真倍速</label>
                <select
                  class="sim-modal__select"
                  :value="simSpeed"
                  :disabled="simStatus.status === 'running'"
                  @change="
                    emit(
                      'update:simSpeed',
                      Number(($event.target as HTMLSelectElement).value) as SimulationSpeed,
                    )
                  "
                >
                  <option v-for="s in SPEED_OPTIONS" :key="s.value" :value="s.value">
                    {{ s.label }}
                  </option>
                </select>
              </div>

              <div class="sim-modal__field">
                <label>上游水位 <strong>{{ clampedModalWaterLevel.toFixed(2) }} m</strong></label>
                <input
                  type="range"
                  class="sim-modal__slider"
                  :min="SIMULATION_LEVEL_MIN"
                  max="383.75"
                  step="0.1"
                  :value="clampedModalWaterLevel"
                  @pointerdown="onSliderPointer(true)"
                  @pointerup="onSliderPointer(false)"
                  @pointercancel="onSliderPointer(false)"
                  @input="emit('update:water-level', Number(($event.target as HTMLInputElement).value))"
                />
              </div>

              <div class="sim-modal__field">
                <label>降雨量 <strong>{{ safeRainfall.toFixed(1) }} mm/h</strong></label>
                <input
                  type="range"
                  class="sim-modal__slider"
                  min="0"
                  max="80"
                  step="1"
                  :value="safeRainfall"
                  @pointerdown="onSliderPointer(true)"
                  @pointerup="onSliderPointer(false)"
                  @pointercancel="onSliderPointer(false)"
                  @input="emit('update:rainfall', Number(($event.target as HTMLInputElement).value))"
                />
              </div>

              <Transition name="gate-panel" mode="out-in">
                <div v-if="hasGateSelected" :key="'gate-' + activeGateNo" class="sim-modal__gate-detail">
                  <h4>{{ activeGateNo }} 号表孔 · 细化调节</h4>
                  <div class="sim-modal__gate-flow">
                    <div>
                      <small>单孔泄流量</small>
                      <strong>{{ activeGateFlow }} m³/s</strong>
                    </div>
                    <div>
                      <small>五孔平均开度</small>
                      <strong>{{ safeGateOpening }}%</strong>
                    </div>
                  </div>
                  <div class="sim-modal__field">
                    <label>本孔开度 {{ activeGateOpening }}%</label>
                    <input
                      type="range"
                      class="sim-modal__slider"
                      min="0"
                      max="100"
                      step="1"
                      :value="activeGateOpening"
                      @pointerdown="onSliderPointer(true)"
                      @pointerup="onSliderPointer(false)"
                      @pointercancel="onSliderPointer(false)"
                      @input="
                        emit(
                          'update:gate-opening-at',
                          props.selectedGateIndex ?? 0,
                          Number(($event.target as HTMLInputElement).value),
                        )
                      "
                    />
                  </div>
                  <p class="sim-modal__hint">宽幅水幕随开度变化；0% 完全隐藏泄流。近全开时选中框会转到阀墩。</p>
                </div>
                <div v-else key="gate-avg" class="sim-modal__field">
                  <label>平均开度 {{ safeGateOpening }}%</label>
                  <input
                    type="range"
                    class="sim-modal__slider"
                    min="0"
                    max="100"
                    step="1"
                    :value="safeGateOpening"
                    @pointerdown="onSliderPointer(true)"
                    @pointerup="onSliderPointer(false)"
                    @pointercancel="onSliderPointer(false)"
                    @input="
                      emit('update:gateOpening', Number(($event.target as HTMLInputElement).value))
                    "
                  />
                  <p class="sim-modal__hint">点击右侧 3D 阀门，可单独调节每一孔开度与泄流量</p>
                </div>
              </Transition>

              <div class="sim-modal__actions">
                <button
                  type="button"
                  class="sim-modal__btn sim-modal__btn--primary"
                  :disabled="!canStart"
                  @click="emit('start')"
                >
                  开始仿真
                </button>
                <button
                  type="button"
                  class="sim-modal__btn"
                  :disabled="!canPause"
                  @click="emit('pause')"
                >
                  {{ simStatus.status === 'paused' ? '继续' : '暂停' }}
                </button>
                <button
                  type="button"
                  class="sim-modal__btn sim-modal__btn--ghost"
                  :disabled="simStatus.status === 'idle'"
                  @click="emit('reset')"
                >
                  重置
                </button>
              </div>

              <div class="sim-modal__kpis">
                <div class="sim-modal__kpi">
                  <small>仿真时间</small>
                  <strong>{{ elapsedLabel }}</strong>
                </div>
                <div class="sim-modal__kpi">
                  <small>上游水位</small>
                  <strong :style="{ color: levelStatusColor }"
                    >{{ clampedModalWaterLevel.toFixed(2) }} m</strong
                  >
                  <em>{{ levelStatusLabel }}</em>
                </div>
                <div class="sim-modal__kpi">
                  <small>下游尾水</small>
                  <strong>{{ safeDownstreamLevel.toFixed(2) }} m</strong>
                </div>
                <div class="sim-modal__kpi">
                  <small>入库流量</small>
                  <strong>{{ safeFlowRate }} m³/s</strong>
                </div>
                <div class="sim-modal__kpi">
                  <small>阀门开度</small>
                  <strong>{{ safeGateOpening }}%</strong>
                </div>
              </div>

              <div v-if="simStatus.historyLevels.length" class="sim-modal__history">
                <h4>水位变化（最近）</h4>
                <ul>
                  <li v-for="(p, i) in simStatus.historyLevels.slice(-6)" :key="i">
                    T+{{ p.time }}s · {{ p.value.toFixed(2) }} m
                  </li>
                </ul>
              </div>
              <p v-else class="sim-modal__hint">点击「开始仿真」后，左侧实时显示运行数据。</p>
            </template>
          </aside>

          <!-- 右侧：放大 3D 模型 -->
          <main class="sim-modal__viewport">
            <Transition name="gate-hud" mode="out-in">
              <div
                v-if="hasGateSelected"
                :key="activeGateNo"
                class="sim-modal__gate-hud"
              >
                <strong>{{ activeGateNo }} 号表孔</strong>
                <span>开度 {{ activeGateOpening }}%</span>
                <span>泄流 {{ activeGateFlow }} m³/s</span>
              </div>
            </Transition>
            <ThreeDamScene
              v-show="visible"
              ref="sceneRef"
              visual-mode="panorama"
              :water-level="safeWaterLevel"
              :downstream-level="safeDownstreamLevel"
              :gate-opening="safeGateOpening"
              :gate-openings="safeGateOpenings"
              :flow-rate="safeFlowRate"
              :sim-scene="simScene"
              :sim-running="simActive"
              :selected-gate-index="selectedGateIndex ?? -1"
              :rainfall="safeRainfall"
              :auto-rotate="false"
              @gate-select="emit('gate-select', $event)"
            />
            <button type="button" class="sim-modal__close" aria-label="关闭" @click="emit('close')">
              ✕
            </button>
          </main>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped lang="scss">
.sim-modal {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: none;

  &__backdrop {
    position: absolute;
    inset: 0;
    pointer-events: auto;
    background: rgba(8, 12, 20, 0.45);
  }

  &__panel {
    position: relative;
    z-index: 1;
    display: flex;
    width: min(92vw, 1400px);
    height: min(88vh, 860px);
    pointer-events: auto;
    border-radius: 18px;
    overflow: hidden;
    background: #f2f4f7;
    box-shadow: 0 20px 56px rgba(15, 34, 56, 0.18);
    transform-origin: center center;
  }

  &--running &__panel {
    box-shadow:
      0 16px 64px rgba(0, 0, 0, 0.28),
      0 0 0 2px rgba(34, 197, 94, 0.35);
  }

  &__sidebar {
    flex: 0 0 320px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 22px 20px;
    background: #eceff3;
    border-right: 1px solid rgba(30, 73, 118, 0.08);
    overflow-y: auto;
  }

  &__sidebar-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;

    h2 {
      margin: 0;
      font-size: 18px;
      font-weight: 700;
      color: #1e4976;
    }
  }

  &__status {
    font-size: 12px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid currentColor;
    opacity: 0.85;
  }

  &__scene-brief {
    h3 {
      margin: 0 0 4px;
      font-size: 14px;
      color: #1890ff;
    }

    p {
      margin: 0;
      font-size: 12px;
      color: #64748b;
      line-height: 1.5;
    }
  }

  &__field {
    display: flex;
    flex-direction: column;
    gap: 6px;

    label {
      font-size: 13px;
      font-weight: 600;
      color: #5a6a7a;

      strong {
        color: #1e4976;
        font-size: 15px;
        font-weight: 800;
      }
    }
  }

  &__select {
    padding: 8px 10px;
    font-size: 13px;
    font-weight: 600;
    color: #1e4976;
    background: #fff;
    border: 1px solid rgba(24, 144, 255, 0.25);
    border-radius: 8px;
    cursor: pointer;

    &:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
  }

  &__slider {
    width: 100%;
    accent-color: #1890ff;

    &:disabled {
      opacity: 0.45;
      cursor: not-allowed;
    }
  }

  &__actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  &__btn {
    padding: 10px 16px;
    font-size: 14px;
    font-weight: 600;
    color: #1890ff;
    background: rgba(230, 244, 255, 0.95);
    border: 1px solid rgba(24, 144, 255, 0.3);
    border-radius: 8px;
    cursor: pointer;
    transition:
      transform 0.2s ease,
      box-shadow 0.2s ease;

    &:hover:not(:disabled) {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(24, 144, 255, 0.2);
    }

    &:disabled {
      opacity: 0.45;
      cursor: not-allowed;
    }

    &--primary {
      color: #fff;
      background: linear-gradient(135deg, #1890ff, #096dd9);
      border-color: transparent;
    }

    &--ghost {
      background: transparent;
    }
  }

  &__kpis {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    padding: 14px;
    border-radius: 14px;
    background: #f7f9fb;
    border: 1px solid rgba(30, 73, 118, 0.08);
    box-shadow: 0 4px 14px rgba(15, 34, 56, 0.05);
  }

  &__kpi {
    small {
      display: block;
      font-size: 11px;
      color: #8a9aaa;
    }

    strong {
      display: block;
      font-size: 17px;
      color: #1e4976;
      font-weight: 800;
    }

    em {
      display: block;
      font-size: 10px;
      font-style: normal;
      color: #64748b;
    }
  }

  &__history {
    h4 {
      margin: 0 0 6px;
      font-size: 12px;
      color: #64748b;
    }

    ul {
      margin: 0;
      padding: 0;
      list-style: none;
      font-size: 11px;
      color: #475569;
      line-height: 1.7;
    }
  }

  &__hint {
    margin: 0;
    font-size: 12px;
    color: #94a3b8;
    line-height: 1.5;
  }

  &__gate-detail {
    padding: 12px;
    border-radius: 10px;
    background: rgba(24, 144, 255, 0.06);
    border: 1px solid rgba(24, 144, 255, 0.22);

    h4 {
      margin: 0 0 10px;
      font-size: 14px;
      color: #1890ff;
    }
  }

  &__gate-flow {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 10px;

    small {
      display: block;
      font-size: 10px;
      color: #94a3b8;
    }

    strong {
      display: block;
      font-size: 16px;
      color: #1e4976;
      font-weight: 700;
    }
  }

  &__viewport {
    flex: 1;
    min-width: 0;
    min-height: 0;
    position: relative;
    display: flex;
    flex-direction: column;
    background: linear-gradient(180deg, #f0f2f5 0%, #e8ebef 50%, #e2e6ea 100%);

    :deep(.three-scene) {
      flex: 1;
      width: 100%;
      min-height: 0;
    }
  }

  &__gate-hud {
    position: absolute;
    top: 14px;
    left: 14px;
    z-index: 12;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 14px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.92);
    border: 1px solid rgba(24, 144, 255, 0.35);
    box-shadow: 0 4px 16px rgba(24, 144, 255, 0.15);
    font-size: 12px;
    color: #64748b;
    pointer-events: none;

    strong {
      color: #1890ff;
      font-size: 13px;
    }

    span b {
      color: #1e4976;
      font-weight: 700;
    }
  }

  &__geo-lines {
    display: none;
  }

  &__close {
    position: absolute;
    top: 14px;
    right: 14px;
    z-index: 20;
    width: 36px;
    height: 36px;
    border: 1px solid rgba(64, 200, 255, 0.45);
    border-radius: 8px;
    background: rgba(6, 14, 28, 0.75);
    color: #7ec8ff;
    font-size: 16px;
    cursor: pointer;

    &:hover {
      background: rgba(24, 144, 255, 0.25);
    }
  }
}

.panorama-fade-enter-active .sim-modal__panel,
.panorama-fade-leave-active .sim-modal__panel {
  transition:
    opacity 0.38s ease,
    transform 0.38s cubic-bezier(0.22, 1, 0.36, 1);
}

.panorama-fade-enter-active .sim-modal__backdrop,
.panorama-fade-leave-active .sim-modal__backdrop {
  transition: opacity 0.38s ease;
}

.panorama-fade-enter-from,
.panorama-fade-leave-to {
  .sim-modal__backdrop {
    opacity: 0;
  }
  .sim-modal__panel {
    opacity: 0;
    transform: scale(0.9);
  }
}

@keyframes geo-line-flow {
  0% {
    stroke-dashoffset: 0;
  }
  100% {
    stroke-dashoffset: -32;
  }
}

.gate-panel-enter-active,
.gate-panel-leave-active,
.gate-hud-enter-active,
.gate-hud-leave-active {
  transition:
    opacity 0.28s ease,
    transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

.gate-panel-enter-from,
.gate-panel-leave-to,
.gate-hud-enter-from,
.gate-hud-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

.gate-hud-enter-from,
.gate-hud-leave-to {
  transform: translateY(-6px);
}
</style>
