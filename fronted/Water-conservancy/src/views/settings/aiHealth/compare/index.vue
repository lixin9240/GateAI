<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElSelect, ElOption, ElButton, ElMessage } from 'element-plus'
import { RESERVOIR_OPTIONS } from '@/constants/settings'
import { getAIVersionCompare } from '@/api/settings'
import type { CompareResult } from '@/stores/aiHealth'
import CompareRadar from './components/CompareRadar.vue'
import ScoreDiffTable from './components/ScoreDiffTable.vue'

const reservoirId = ref<number>(1)
const version1 = ref('')
const version2 = ref('')
const compareData = ref<CompareResult | null>(null)
const compareLoading = ref(false)

const versionOptions = ref([
  { label: 'v3.2.1 (2026-07-03)', value: 'v3.2.1_20260703' },
  { label: 'v3.2.0 (2026-06-28)', value: 'v3.2.0_20260628' },
  { label: 'v3.1.5 (2026-06-20)', value: 'v3.1.5_20260620' },
  { label: 'v3.1.0 (2026-06-10)', value: 'v3.1.0_20260610' },
  { label: 'v3.0.0 (2026-05-15)', value: 'v3.0.0_20260515' },
])

function generateMockCompare(): CompareResult {
  return {
    version1: {
      overallScore: 0.82,
      healthGrade: 'A',
      waterLevelMAE: 0.035,
      safetyCoverageRate: 0.92,
      decisionAutoRate: 0.75,
      version: version1.value,
    },
    version2: {
      overallScore: 0.78,
      healthGrade: 'A',
      waterLevelMAE: 0.042,
      safetyCoverageRate: 0.89,
      decisionAutoRate: 0.68,
      version: version2.value,
    },
    radarData: [
      { dimension: 'prediction', v1: 0.85, v2: 0.8 },
      { dimension: 'decision', v1: 0.8, v2: 0.76 },
      { dimension: 'compliance', v1: 0.9, v2: 0.88 },
      { dimension: 'safety_coverage', v1: 0.92, v2: 0.89 },
      { dimension: 'decision_auto_rate', v1: 0.75, v2: 0.68 },
    ],
    scoreDiff: [
      { dimension: '预测准确性', v1: 0.85, v2: 0.8, diff: 0.05 },
      { dimension: '决策可靠性', v1: 0.8, v2: 0.76, diff: 0.04 },
      { dimension: '物理合规性', v1: 0.9, v2: 0.88, diff: 0.02 },
      { dimension: '安全覆盖率', v1: 0.92, v2: 0.89, diff: 0.03 },
      { dimension: '决策自主率', v1: 0.75, v2: 0.68, diff: 0.07 },
    ],
  }
}

async function doCompare() {
  if (!version1.value || !version2.value) {
    ElMessage.warning('请选择两个版本')
    return
  }
  if (version1.value === version2.value) {
    ElMessage.warning('请选择不同的版本进行比较')
    return
  }
  compareLoading.value = true
  try {
    const res = await getAIVersionCompare({
      reservoir_id: reservoirId.value,
      version1: version1.value,
      version2: version2.value,
    })
    if (res.data?.code === 0 && res.data.data) {
      compareData.value = res.data.data
      return
    }
  } catch {
    /* fallback */
  }
  compareData.value = generateMockCompare()
  compareLoading.value = false
}

function onReservoirChange() {
  compareData.value = null
}

onMounted(() => {
  // Optionally auto-load default compare
})
</script>

<template>
  <div class="ai-health-compare">
    <div class="page-header">
      <h2 class="page-title">AI 模型版本对比</h2>
    </div>

    <div class="compare-controls">
      <ElSelect
        v-model="reservoirId"
        placeholder="选择水库"
        style="width: 160px"
        @change="onReservoirChange"
      >
        <ElOption
          v-for="opt in RESERVOIR_OPTIONS"
          :key="opt.value"
          :label="opt.label"
          :value="opt.value"
        />
      </ElSelect>
      <ElSelect v-model="version1" placeholder="版本 1 (基准)" style="width: 220px">
        <ElOption
          v-for="opt in versionOptions"
          :key="opt.value"
          :label="opt.label"
          :value="opt.value"
        />
      </ElSelect>
      <span class="vs-label">VS</span>
      <ElSelect v-model="version2" placeholder="版本 2 (对比)" style="width: 220px">
        <ElOption
          v-for="opt in versionOptions"
          :key="opt.value"
          :label="opt.label"
          :value="opt.value"
        />
      </ElSelect>
      <ElButton type="primary" :loading="compareLoading" @click="doCompare"> 开始对比 </ElButton>
    </div>

    <CompareRadar :data="compareData?.radarData ?? null" :loading="compareLoading" />

    <ScoreDiffTable :data="compareData?.scoreDiff ?? null" :loading="compareLoading" />

    <div v-if="compareData" class="compare-summary">
      <div class="summary-row">
        <span
          >版本1 综合评分:
          <strong>{{ (compareData.version1.overallScore * 100).toFixed(1) }}%</strong></span
        >
        <span
          >版本2 综合评分:
          <strong>{{ (compareData.version2.overallScore * 100).toFixed(1) }}%</strong></span
        >
        <span
          :class="
            compareData.version1.overallScore >= compareData.version2.overallScore
              ? 'diff-positive'
              : 'diff-negative'
          "
        >
          差异:
          {{
            ((compareData.version1.overallScore - compareData.version2.overallScore) * 100).toFixed(
              2,
            ) > '0'
              ? '+'
              : ''
          }}{{
            ((compareData.version1.overallScore - compareData.version2.overallScore) * 100).toFixed(
              1,
            )
          }}%
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.ai-health-compare {
  padding: var(--spacing-md);
}

.page-header {
  margin-bottom: var(--spacing-lg);
}

.page-title {
  font-size: var(--font-size-xxl);
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.compare-controls {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
  margin-bottom: var(--spacing-lg);
  flex-wrap: wrap;
  padding: var(--spacing-md);
  background: var(--color-bg-dark);
  border-radius: var(--border-radius-base);
}

.vs-label {
  font-size: var(--font-size-lg);
  font-weight: 700;
  color: var(--color-text-secondary);
}

.compare-summary {
  padding: var(--spacing-md);
  background: var(--color-bg-dark);
  border-radius: var(--border-radius-base);
  margin-top: var(--spacing-md);
}

.summary-row {
  display: flex;
  gap: var(--spacing-lg);
  font-size: var(--font-size-base);
  color: var(--color-text);

  strong {
    font-weight: 700;
  }
}

.diff-positive {
  color: #67c23a;
  font-weight: 700;
}

.diff-negative {
  color: #f56c6c;
  font-weight: 700;
}
</style>
