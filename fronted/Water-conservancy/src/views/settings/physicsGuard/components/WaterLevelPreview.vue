<script setup lang="ts">
import { computed } from 'vue'
import type { PhysicsGuardConfig } from '@/stores/physicsGuard'

const props = defineProps<{
  config: PhysicsGuardConfig | null
}>()

// Normalize water level to percentage (0-100%) for visualization
// Assuming range between dead water level and danger level
const waterLevelPercent = computed(() => {
  if (!props.config) return 50
  const min = props.config.upstream_min
  const max = props.config.upstream_danger
  const current = (props.config.ideal_min + props.config.ideal_max) / 2 // Use mid of ideal range as "current"
  if (max <= min) return 50
  return Math.max(0, Math.min(100, ((current - min) / (max - min)) * 100))
})

const dangerLine = computed(() => {
  if (!props.config) return 10
  const min = props.config.upstream_min
  const max = props.config.upstream_danger
  return ((props.config.upstream_danger - min) / (max - min)) * 100
})

const emergencyLine = computed(() => {
  if (!props.config) return 20
  const min = props.config.upstream_min
  const max = props.config.upstream_danger
  return ((props.config.upstream_emergency - min) / (max - min)) * 100
})

const warningLine = computed(() => {
  if (!props.config) return 35
  const min = props.config.upstream_min
  const max = props.config.upstream_danger
  return ((props.config.upstream_warning - min) / (max - min)) * 100
})

const idealMinLine = computed(() => {
  if (!props.config) return 50
  const min = props.config.upstream_min
  const max = props.config.upstream_danger
  return ((props.config.ideal_min - min) / (max - min)) * 100
})

const idealMaxLine = computed(() => {
  if (!props.config) return 70
  const min = props.config.upstream_min
  const max = props.config.upstream_danger
  return ((props.config.ideal_max - min) / (max - min)) * 100
})
</script>

<template>
  <div class="water-level-preview">
    <h4 class="preview-title">水位标尺预览</h4>
    <div class="ruler-container">
      <div class="ruler">
        <!-- Danger zone -->
        <div class="ruler-zone ruler-zone--danger" :style="{ bottom: dangerLine + '%' }">
          <span class="zone-label">危险</span>
        </div>
        <!-- Emergency zone -->
        <div class="ruler-zone ruler-zone--emergency" :style="{ bottom: emergencyLine + '%' }">
          <span class="zone-label">紧急</span>
        </div>
        <!-- Warning zone -->
        <div class="ruler-zone ruler-zone--warning" :style="{ bottom: warningLine + '%' }">
          <span class="zone-label">预警</span>
        </div>
        <!-- Ideal zone -->
        <div
          class="ruler-zone ruler-zone--ideal"
          :style="{ bottom: idealMinLine + '%', height: idealMaxLine - idealMinLine + '%' }"
        >
          <span class="zone-label">理想区间</span>
        </div>

        <!-- Current water level indicator -->
        <div class="water-level-line" :style="{ bottom: waterLevelPercent + '%' }">
          <span class="level-text">当前水位</span>
        </div>

        <!-- Danger line -->
        <div class="ruler-line ruler-line--danger" :style="{ bottom: dangerLine + '%' }">
          <span class="line-label"> {{ config?.upstream_danger ?? '--' }}m </span>
        </div>
        <!-- Emergency line -->
        <div class="ruler-line ruler-line--emergency" :style="{ bottom: emergencyLine + '%' }">
          <span class="line-label"> {{ config?.upstream_emergency ?? '--' }}m </span>
        </div>
        <!-- Warning line -->
        <div class="ruler-line ruler-line--warning" :style="{ bottom: warningLine + '%' }">
          <span class="line-label"> {{ config?.upstream_warning ?? '--' }}m </span>
        </div>
        <!-- Ideal min -->
        <div class="ruler-line ruler-line--ideal-min" :style="{ bottom: idealMinLine + '%' }">
          <span class="line-label"> {{ config?.ideal_min ?? '--' }}m </span>
        </div>
        <!-- Ideal max -->
        <div class="ruler-line ruler-line--ideal-max" :style="{ bottom: idealMaxLine + '%' }">
          <span class="line-label"> {{ config?.ideal_max ?? '--' }}m </span>
        </div>

        <!-- Dead water level -->
        <div class="ruler-line ruler-line--dead" style="bottom: 0%">
          <span class="line-label"> {{ config?.upstream_min ?? '--' }}m (死水位) </span>
        </div>
      </div>
    </div>
    <div v-if="!config" class="preview-note">请先选择水库加载配置</div>
  </div>
</template>

<style scoped lang="scss">
.water-level-preview {
  position: sticky;
  top: var(--spacing-lg);
}

.preview-title {
  font-size: var(--font-size-base);
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: var(--spacing-md);
}

.ruler-container {
  display: flex;
  justify-content: center;
}

.ruler {
  position: relative;
  width: 80px;
  height: 400px;
  border: 2px solid var(--color-border);
  border-radius: var(--border-radius-base);
  background: linear-gradient(to bottom, #f56c6c 0%, #e6a23c 30%, #67c23a 60%, #409eff 100%);
  overflow: hidden;
}

.ruler-zone {
  position: absolute;
  left: 0;
  right: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.ruler-zone--danger {
  top: 0;
  background: rgba(245, 108, 108, 0.3);
}

.ruler-zone--emergency {
  top: 0;
  background: rgba(230, 162, 60, 0.3);
}

.ruler-zone--warning {
  top: 0;
  background: rgba(230, 162, 60, 0.15);
}

.ruler-zone--ideal {
  background: rgba(103, 194, 58, 0.25);
}

.zone-label {
  font-size: 10px;
  font-weight: 600;
  color: #fff;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}

.water-level-line {
  position: absolute;
  left: 0;
  right: 0;
  height: 3px;
  background: #fff;
  box-shadow: 0 0 6px rgba(0, 0, 0, 0.5);
  z-index: 10;
  display: flex;
  align-items: center;

  .level-text {
    position: absolute;
    left: calc(100% + 8px);
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    background: var(--color-primary);
    padding: 1px 6px;
    border-radius: 2px;
    white-space: nowrap;
  }
}

.ruler-line {
  position: absolute;
  left: 0;
  right: 0;
  height: 1px;

  .line-label {
    position: absolute;
    right: calc(100% + 6px);
    font-size: 9px;
    font-weight: 500;
    color: var(--color-text);
    white-space: nowrap;
    background: rgba(255, 255, 255, 0.8);
    padding: 0 2px;
    border-radius: 2px;
  }
}

.ruler-line--danger {
  background: #f56c6c;
  height: 2px;
}
.ruler-line--emergency {
  background: #e6a23c;
  height: 2px;
}
.ruler-line--warning {
  background: #e6a23c;
}
.ruler-line--ideal-min {
  background: #67c23a;
  border-top-style: dashed;
}
.ruler-line--ideal-max {
  background: #67c23a;
  border-top-style: dashed;
}
.ruler-line--dead {
  background: #999;
  height: 2px;
}

.preview-note {
  text-align: center;
  color: var(--color-text-placeholder);
  font-size: var(--font-size-sm);
  margin-top: var(--spacing-md);
}
</style>
