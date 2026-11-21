<script setup lang="ts">
import { computed } from 'vue'
import { ElCard, ElStatistic } from 'element-plus'
import { HEALTH_GRADE } from '@/constants/aiHealth'
import type { HealthOverview } from '@/stores/aiHealth'

const props = defineProps<{
  overview: HealthOverview | null
  loading: boolean
}>()

const gradeInfo = computed(() => {
  if (!props.overview) return null
  return HEALTH_GRADE[props.overview.healthGrade] ?? null
})

const scoreColor = computed(() => {
  return gradeInfo.value?.color ?? 'var(--color-text)'
})

function formatPercent(val: number): string {
  return `${(val * 100).toFixed(1)}%`
}
</script>

<template>
  <div v-loading="loading" class="health-overview">
    <ElCard shadow="hover" class="overview-card overview-card--score">
      <ElStatistic title="综合评分">
        <template #default>
          <span class="score-value" :style="{ color: scoreColor }">
            {{ overview ? (overview.overallScore * 100).toFixed(1) : '--' }}
          </span>
        </template>
      </ElStatistic>
      <div
        v-if="gradeInfo"
        class="grade-tag"
        :style="{ background: gradeInfo.color, color: '#fff' }"
      >
        {{ gradeInfo.label }}
      </div>
      <div v-if="gradeInfo" class="grade-sub">
        {{ gradeInfo.scoreRange }} | {{ gradeInfo.level }}
      </div>
      <div v-if="!overview && !loading" class="card-placeholder">暂无数据</div>
    </ElCard>

    <ElCard shadow="hover" class="overview-card">
      <ElStatistic title="LSTM 水位 MAE" :value="overview?.waterLevelMAE ?? 0" :precision="3">
        <template #suffix>
          <span class="unit-text">m</span>
        </template>
      </ElStatistic>
      <div v-if="!overview && !loading" class="card-placeholder">暂无数据</div>
    </ElCard>

    <ElCard shadow="hover" class="overview-card">
      <ElStatistic
        title="安全规则覆盖率"
        :value="overview ? formatPercent(overview.safetyCoverageRate) : '--'"
      />
      <div v-if="!overview && !loading" class="card-placeholder">暂无数据</div>
    </ElCard>

    <ElCard shadow="hover" class="overview-card">
      <ElStatistic
        title="决策自主率 (L3_AUTO占比)"
        :value="overview ? formatPercent(overview.decisionAutoRate) : '--'"
      />
      <div v-if="!overview && !loading" class="card-placeholder">暂无数据</div>
    </ElCard>
  </div>
</template>

<style scoped lang="scss">
.health-overview {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--spacing-md);
  margin-bottom: var(--spacing-lg);
}

.overview-card {
  :deep(.el-card__body) {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--spacing-xs);
  }

  :deep(.el-statistic__head) {
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
    margin-bottom: var(--spacing-xs);
  }

  :deep(.el-statistic__content) {
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--color-text);
  }
}

.overview-card--score {
  border-top: 4px solid var(--color-primary);
}

.score-value {
  font-size: 36px;
  font-weight: 800;
}

.grade-tag {
  display: inline-block;
  padding: 2px 12px;
  border-radius: var(--border-radius-base);
  font-size: var(--font-size-sm);
  font-weight: 600;
}

.grade-sub {
  font-size: var(--font-size-xs);
  color: var(--color-text-secondary);
}

.unit-text {
  font-size: var(--font-size-sm);
  color: var(--color-text-secondary);
  margin-left: 4px;
}

.card-placeholder {
  color: var(--color-text-placeholder);
  font-size: var(--font-size-sm);
}
</style>
