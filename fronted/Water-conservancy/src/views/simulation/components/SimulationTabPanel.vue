<script setup lang="ts">
import { computed, ref } from 'vue'
import { ElButton, ElEmpty, ElInput, ElTag } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import GlassPanel3D from '@/components/cockpit/GlassPanel3D.vue'
import { fuzzyMatch } from '@/utils/fuzzyMatch'
import {
  SIMULATION_SCENE_MAP,
  MODEL_STATUS_MAP,
  REVIEW_STATUS_MAP,
  SIMULATION_TABS,
  MODEL_REGISTRY_LABEL,
  getSimulationSceneLabel,
  type SimulationTab,
} from '@/constants/simulation'
import type {
  SimulationScene,
  SimulationRealtimeData,
  AiModel,
  SimulationReport,
  FaultReview,
} from '@/types/simulation'
import type { PhysicsGuardSummary } from '@/types/dispatch'

const props = defineProps<{
  activeTab: SimulationTab
  simScene: SimulationScene
  simStatus: SimulationRealtimeData
  physicsGuard?: PhysicsGuardSummary | null
  models: AiModel[]
  reports: SimulationReport[]
  reviews: FaultReview[]
  modelLoading?: boolean
  modelUploading?: boolean
  reportLoading?: boolean
  reviewLoading?: boolean
  reviewError?: string | null
  compact?: boolean
  /** 顶部导航已展示 Tab 时隐藏面板内重复 Tab 栏 */
  hideTabs?: boolean
}>()

const emit = defineEmits<{
  'tab-change': [tab: SimulationTab]
  activate: [id: number]
  'open-model-registry': []
  train: [modelId: number]
  generate: []
  'download-report': [id: number]
  'import-review': [id: number]
  'refresh-reviews': []
}>()

const tabTitle = computed(
  () => SIMULATION_TABS.find((t) => t.value === props.activeTab)?.label ?? '',
)
const sceneInfo = computed(() => SIMULATION_SCENE_MAP[props.simScene])
const searchKeyword = ref('')

const filteredModels = computed(() => {
  const kw = searchKeyword.value.trim()
  if (!kw || props.activeTab !== 'model') return props.models
  return props.models.filter((m) => fuzzyMatch(kw, m.type, m.version, m.remark, m.status))
})
const filteredReports = computed(() => {
  const kw = searchKeyword.value.trim()
  if (!kw || props.activeTab !== 'report') return props.reports
  return props.reports.filter((r) =>
    fuzzyMatch(kw, r.content, r.operatorName, r.scene, String(r.runId)),
  )
})
const filteredReviews = computed(() => {
  const kw = searchKeyword.value.trim()
  if (!kw || props.activeTab !== 'review') return props.reviews
  return props.reviews.filter((r) => fuzzyMatch(kw, r.faultType, r.impactScope, r.status))
})
const showSearch = computed(() => props.activeTab !== 'control')

const HEALTH_GRADE_COLOR: Record<string, string> = {
  S: '#16a34a',
  A: '#22c55e',
  B: '#f59e0b',
  C: '#f97316',
  D: '#dc2626',
}

function healthGradeColor(grade?: string) {
  return HEALTH_GRADE_COLOR[grade ?? ''] ?? '#6b7280'
}

function formatDuration(sec: number) {
  const m = Math.floor(sec / 60)
  const s = sec % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}
</script>

<template>
  <div class="sim-tab-panel" :class="{ 'sim-tab-panel--compact': compact }">
    <div v-if="!hideTabs" class="sim-tab-panel__tabs">
      <button
        v-for="t in SIMULATION_TABS"
        :key="t.value"
        type="button"
        class="sim-tab-panel__tab"
        :class="{ active: activeTab === t.value }"
        @click="emit('tab-change', t.value)"
      >
        {{ t.label }}
      </button>
    </div>
    <GlassPanel3D v-if="!compact" :title="tabTitle" large>
      <div v-if="showSearch" class="panel-search">
        <ElInput
          v-model="searchKeyword"
          placeholder="模糊搜索..."
          clearable
          size="small"
          :prefix-icon="Search"
        />
      </div>
      <template v-if="activeTab === 'control'">
        <div class="scene-brief">
          <h4>{{ sceneInfo?.label }}</h4>
          <p>{{ sceneInfo?.description }}</p>
        </div>
        <dl class="summary-list">
          <div class="summary-list__row">
            <dt>仿真时长</dt>
            <dd>{{ formatDuration(simStatus.elapsedSec) }}</dd>
          </div>
          <div class="summary-list__row">
            <dt>当前水位</dt>
            <dd>{{ simStatus.currentLevel.toFixed(2) }} m</dd>
          </div>
          <div class="summary-list__row">
            <dt>入库流量</dt>
            <dd>{{ simStatus.currentFlow }} m³/s</dd>
          </div>
          <div class="summary-list__row">
            <dt>平均开度</dt>
            <dd>{{ simStatus.currentOpening }}%</dd>
          </div>
          <template v-if="physicsGuard">
            <div class="summary-list__row">
              <dt>防护配置</dt>
              <dd>v{{ physicsGuard.config_version }}</dd>
            </div>
            <div class="summary-list__row">
              <dt>L3 置信度阈值</dt>
              <dd>{{ physicsGuard.fusion_l3_confidence }}</dd>
            </div>
          </template>
        </dl>
        <div v-if="simStatus.historyLevels.length" class="history-block">
          <h5>水位变化（最近）</h5>
          <ul class="history-list">
            <li v-for="(p, i) in simStatus.historyLevels.slice(-5)" :key="i">
              T+{{ p.time }}s · {{ p.value.toFixed(2) }} m
            </li>
          </ul>
        </div>
        <p v-else class="hint-text">启动仿真后，此处显示运行摘要与历史曲线采样。</p>
      </template>

      <template v-else-if="activeTab === 'model'">
        <div class="panel-actions panel-actions--model">
          <ElButton type="primary" :loading="modelUploading" @click="emit('open-model-registry')">
            {{ MODEL_REGISTRY_LABEL }}
          </ElButton>
        </div>
        <p class="hint-text">请先在{{ MODEL_REGISTRY_LABEL }}上传模型，再选用并激活用于仿真。</p>
        <ElEmpty v-if="!filteredModels.length && !modelLoading" :description="`暂无模型，请打开${MODEL_REGISTRY_LABEL}上传`" />
        <ul v-else class="entity-list">
          <li v-for="m in filteredModels" :key="m.id" class="entity-list__item">
            <div class="entity-list__main">
              <strong>{{ m.type }} {{ m.version }}</strong>
              <div class="entity-list__tags">
                <ElTag
                  v-if="m.metrics?.healthGrade"
                  :color="healthGradeColor(m.metrics.healthGrade)"
                  effect="dark"
                  size="small"
                >
                  健康 {{ m.metrics.healthGrade }}
                </ElTag>
                <ElTag :color="MODEL_STATUS_MAP[m.status]?.color" effect="dark" size="small">
                  {{ MODEL_STATUS_MAP[m.status]?.label }}
                </ElTag>
              </div>
            </div>
            <div class="entity-list__meta">
              {{ m.createdAt }}
              <template v-if="m.metrics?.overallScore != null">
                · 综合分 {{ (m.metrics.overallScore * 100).toFixed(0) }}</template
              >
            </div>
            <p v-if="m.remark" class="entity-list__desc">{{ m.remark }}</p>
            <div class="entity-list__actions">
              <ElButton
                v-if="m.status !== 'active'"
                link
                type="primary"
                @click="emit('activate', m.id)"
                >激活</ElButton
              >
              <ElButton link type="primary" @click="emit('train', m.id)">训练</ElButton>
            </div>
          </li>
        </ul>
      </template>

      <template v-else-if="activeTab === 'report'">
        <div class="panel-actions">
          <ElButton type="primary" :loading="reportLoading" @click="emit('generate')">生成方案评估报告</ElButton>
        </div>
        <p class="hint-text">先启动仿真并运行一段时间，再基于当前仿真结果生成本地评估报告（可下载 .txt）。</p>
        <ElEmpty v-if="!filteredReports.length && !reportLoading" description="暂无方案评估报告" />
        <ul v-else class="entity-list">
          <li v-for="r in filteredReports" :key="r.id" class="entity-list__item">
            <div class="entity-list__main">
              <strong>{{ getSimulationSceneLabel(r.scene) }}</strong>
              <span class="entity-list__tag">最高 {{ r.summary.maxLevel.toFixed(2) }} m</span>
            </div>
            <div class="entity-list__meta">{{ r.createdAt }} · {{ r.operatorName }}</div>
            <p class="entity-list__desc">{{ r.content }}</p>
            <div class="entity-list__actions">
              <ElButton link type="primary" @click="emit('download-report', r.id)">下载报告</ElButton>
            </div>
          </li>
        </ul>
      </template>

      <template v-else>
        <div class="panel-actions">
          <ElButton size="small" :loading="reviewLoading" @click="emit('refresh-reviews')">刷新列表</ElButton>
        </div>
        <p class="hint-text">关联历史告警事件，复盘根因并一键导入仿真参数复现（数据来自后端 simulation_incidents）。</p>
        <p v-if="reviewError" class="hint-text hint-text--warn">{{ reviewError }}</p>
        <ElEmpty
          v-if="!filteredReviews.length && !reviewLoading"
          :description="reviewError ? '无法加载故障复盘' : '暂无历史故障复盘记录'"
        />
        <ul v-else class="entity-list">
          <li v-for="r in filteredReviews" :key="r.id" class="entity-list__item">
            <div class="entity-list__main">
              <strong>{{ r.faultType }}</strong>
              <ElTag :color="REVIEW_STATUS_MAP[r.status]?.color" effect="dark">
                {{ REVIEW_STATUS_MAP[r.status]?.label }}
              </ElTag>
            </div>
            <div class="entity-list__meta">{{ r.impactScope }} · {{ r.createdAt }}</div>
            <div class="entity-list__actions">
              <ElButton link type="primary" @click="emit('import-review', r.id)">导入仿真</ElButton>
            </div>
          </li>
        </ul>
      </template>
    </GlassPanel3D>
    <div v-else class="sim-tab-panel__compact-body">
      <div v-if="showSearch" class="panel-search">
        <ElInput
          v-model="searchKeyword"
          placeholder="模糊搜索..."
          clearable
          size="small"
          :prefix-icon="Search"
        />
      </div>
      <template v-if="activeTab === 'control'">
        <div class="scene-brief">
          <h4>{{ sceneInfo?.label }}</h4>
          <p>{{ sceneInfo?.description }}</p>
        </div>
        <dl class="summary-list">
          <div class="summary-list__row">
            <dt>仿真时长</dt>
            <dd>{{ formatDuration(simStatus.elapsedSec) }}</dd>
          </div>
          <div class="summary-list__row">
            <dt>当前水位</dt>
            <dd>{{ simStatus.currentLevel.toFixed(2) }} m</dd>
          </div>
          <div class="summary-list__row">
            <dt>入库流量</dt>
            <dd>{{ simStatus.currentFlow }} m³/s</dd>
          </div>
          <div class="summary-list__row">
            <dt>平均开度</dt>
            <dd>{{ simStatus.currentOpening }}%</dd>
          </div>
          <template v-if="physicsGuard">
            <div class="summary-list__row">
              <dt>防护配置</dt>
              <dd>v{{ physicsGuard.config_version }}</dd>
            </div>
            <div class="summary-list__row">
              <dt>L3 置信度阈值</dt>
              <dd>{{ physicsGuard.fusion_l3_confidence }}</dd>
            </div>
          </template>
        </dl>
        <div v-if="simStatus.historyLevels.length" class="history-block">
          <h5>水位变化（最近）</h5>
          <ul class="history-list">
            <li v-for="(p, i) in simStatus.historyLevels.slice(-5)" :key="i">
              T+{{ p.time }}s · {{ p.value.toFixed(2) }} m
            </li>
          </ul>
        </div>
        <p v-else class="hint-text">启动仿真后，此处显示运行摘要与历史曲线采样。</p>
      </template>

      <template v-else-if="activeTab === 'model'">
        <div class="panel-actions panel-actions--model">
          <ElButton type="primary" :loading="modelUploading" @click="emit('open-model-registry')">
            {{ MODEL_REGISTRY_LABEL }}
          </ElButton>
        </div>
        <p class="hint-text">请先在{{ MODEL_REGISTRY_LABEL }}上传模型，再选用并激活用于仿真。</p>
        <ElEmpty v-if="!filteredModels.length && !modelLoading" :description="`暂无模型，请打开${MODEL_REGISTRY_LABEL}上传`" />
        <ul v-else class="entity-list">
          <li v-for="m in filteredModels" :key="m.id" class="entity-list__item">
            <div class="entity-list__main">
              <strong>{{ m.type }} {{ m.version }}</strong>
              <div class="entity-list__tags">
                <ElTag
                  v-if="m.metrics?.healthGrade"
                  :color="healthGradeColor(m.metrics.healthGrade)"
                  effect="dark"
                  size="small"
                >
                  健康 {{ m.metrics.healthGrade }}
                </ElTag>
                <ElTag :color="MODEL_STATUS_MAP[m.status]?.color" effect="dark" size="small">
                  {{ MODEL_STATUS_MAP[m.status]?.label }}
                </ElTag>
              </div>
            </div>
            <div class="entity-list__meta">
              {{ m.createdAt }}
              <template v-if="m.metrics?.overallScore != null">
                · 综合分 {{ (m.metrics.overallScore * 100).toFixed(0) }}</template
              >
            </div>
            <p v-if="m.remark" class="entity-list__desc">{{ m.remark }}</p>
            <div class="entity-list__actions">
              <ElButton
                v-if="m.status !== 'active'"
                link
                type="primary"
                @click="emit('activate', m.id)"
                >激活</ElButton
              >
              <ElButton link type="primary" @click="emit('train', m.id)">训练</ElButton>
            </div>
          </li>
        </ul>
      </template>

      <template v-else-if="activeTab === 'report'">
        <div class="panel-actions">
          <ElButton type="primary" :loading="reportLoading" @click="emit('generate')">生成方案评估报告</ElButton>
        </div>
        <p class="hint-text">先启动仿真并运行一段时间，再基于当前仿真结果生成本地评估报告（可下载 .txt）。</p>
        <ElEmpty v-if="!filteredReports.length && !reportLoading" description="暂无方案评估报告" />
        <ul v-else class="entity-list">
          <li v-for="r in filteredReports" :key="r.id" class="entity-list__item">
            <div class="entity-list__main">
              <strong>{{ getSimulationSceneLabel(r.scene) }}</strong>
              <span class="entity-list__tag">最高 {{ r.summary.maxLevel.toFixed(2) }} m</span>
            </div>
            <div class="entity-list__meta">{{ r.createdAt }} · {{ r.operatorName }}</div>
            <p class="entity-list__desc">{{ r.content }}</p>
            <div class="entity-list__actions">
              <ElButton link type="primary" @click="emit('download-report', r.id)">下载报告</ElButton>
            </div>
          </li>
        </ul>
      </template>

      <template v-else>
        <div class="panel-actions">
          <ElButton size="small" :loading="reviewLoading" @click="emit('refresh-reviews')">刷新列表</ElButton>
        </div>
        <p class="hint-text">关联历史告警事件，复盘根因并一键导入仿真参数复现（数据来自后端 simulation_incidents）。</p>
        <p v-if="reviewError" class="hint-text hint-text--warn">{{ reviewError }}</p>
        <ElEmpty
          v-if="!filteredReviews.length && !reviewLoading"
          :description="reviewError ? '无法加载故障复盘' : '暂无历史故障复盘记录'"
        />
        <ul v-else class="entity-list">
          <li v-for="r in filteredReviews" :key="r.id" class="entity-list__item">
            <div class="entity-list__main">
              <strong>{{ r.faultType }}</strong>
              <ElTag :color="REVIEW_STATUS_MAP[r.status]?.color" effect="dark">
                {{ REVIEW_STATUS_MAP[r.status]?.label }}
              </ElTag>
            </div>
            <div class="entity-list__meta">{{ r.impactScope }} · {{ r.createdAt }}</div>
            <div class="entity-list__actions">
              <ElButton link type="primary" @click="emit('import-review', r.id)">导入仿真</ElButton>
            </div>
          </li>
        </ul>
      </template>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;

.sim-tab-panel {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;

  &__tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
    flex-shrink: 0;
  }

  &__tab {
    padding: 6px 12px;
    font-size: $cockpit-font-sm;
    font-weight: 600;
    color: $cockpit-text-dim;
    background: rgba(255, 255, 255, 0.85);
    border: 1px solid rgba(24, 144, 255, 0.18);
    border-radius: 8px;
    @include interactive-tactile(-2px);

    &.active {
      color: $cockpit-accent;
      background: #e6f4ff;
      border-color: $cockpit-accent;
      box-shadow: 0 2px 10px rgba(24, 144, 255, 0.15);
    }
  }

  &--compact {
    min-height: min-content;

    .sim-tab-panel__compact-body {
      overflow: visible;
      font-size: $cockpit-font-base;
      line-height: 1.55;
      color: $cockpit-text;
      min-width: 0;
    }
  }

  :deep(.glass-panel) {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
  }

  :deep(.glass-panel__body) {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    font-size: $cockpit-font-base;
    @include hide-scrollbar;
  }

  :deep(.el-button) {
    font-size: $cockpit-font-sm;
  }
}

.scene-brief {
  margin-bottom: 10px;
  min-width: 0;

  h4 {
    margin: 0 0 6px;
    font-size: $cockpit-font-md;
    color: $cockpit-text;
  }

  p {
    margin: 0;
    font-size: $cockpit-font-base;
    color: $cockpit-text-dim;
    line-height: 1.5;
    word-break: break-word;
  }
}

.summary-list {
  margin: 0 0 10px;
  padding: 0;
  min-width: 0;

  &__row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    padding: 10px 0;
    margin: 0;
    border-bottom: 1px solid rgba(24, 144, 255, 0.1);
    font-size: $cockpit-font-base;
    min-width: 0;
  }

  dt {
    flex: 1;
    min-width: 0;
    color: $cockpit-text-dim;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  dd {
    margin: 0;
    flex-shrink: 0;
    font-weight: 700;
    color: $cockpit-accent;
    font-size: $cockpit-font-md;
    white-space: nowrap;
  }
}

.history-block {
  min-width: 0;

  h5 {
    margin: 0 0 8px;
    font-size: $cockpit-font-base;
    color: $cockpit-text-dim;
    font-weight: 700;
  }
}

.history-list {
  margin: 0;
  padding: 0;
  list-style: none;
  font-size: $cockpit-font-base;
  color: $cockpit-text;
  min-width: 0;

  li {
    padding: 6px 0;
    margin: 0;
    border-bottom: 1px dashed rgba(24, 144, 255, 0.08);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

.hint-text {
  margin: 0;
  font-size: $cockpit-font-base;
  color: $cockpit-text-dim;
  line-height: 1.5;
  word-break: break-word;

  &--warn {
    color: #d97706;
    margin-top: 8px;
  }
}

.panel-actions {
  margin-bottom: var(--spacing-md);
}

.panel-search {
  margin-bottom: 12px;
}

.entity-list {
  margin: 0;
  padding: 0;
  list-style: none;

  &__item {
    padding: 10px 8px;
    margin: 0 -8px;
    border-bottom: 1px solid rgba(24, 144, 255, 0.1);
    border-radius: 8px;

    &:last-child {
      border-bottom: none;
    }

    &:active {
      transform: scale(0.99);
    }
  }

  &__main {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;

    strong {
      font-size: $cockpit-font-base;
      color: $cockpit-text;
      line-height: 1.4;
      word-break: break-word;
    }
  }

  &__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: flex-end;
  }

  &__tag {
    font-size: $cockpit-font-sm;
    color: $cockpit-accent;
    font-weight: 600;
  }

  &__meta {
    font-size: $cockpit-font-sm;
    color: $cockpit-text-dim;
    margin-bottom: 4px;
    line-height: 1.45;
    word-break: break-word;
  }

  &__desc {
    margin: 0;
    font-size: $cockpit-font-base;
    color: $cockpit-text-dim;
    line-height: 1.45;
    word-break: break-word;
  }

  &__actions {
    display: flex;
    gap: 10px;
    margin-top: 6px;
  }
}
</style>
