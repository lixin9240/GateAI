<script setup lang="ts">
import { ref, computed } from 'vue'
import { ElDialog, ElSelect, ElOption, ElButton, ElMessage } from 'element-plus'
import { RESERVOIR_OPTIONS } from '@/constants/settings'

const props = defineProps<{
  visible: boolean
}>()

const emit = defineEmits<{
  (e: 'update:visible', val: boolean): void
  (e: 'clone', data: { sourceReservoirId: number; targetReservoirId: number }): void
}>()

const sourceReservoirId = ref<number>(1)
const targetReservoirId = ref<number | null>(null)

function handleClone() {
  if (!targetReservoirId.value) {
    ElMessage.warning('请选择目标水库')
    return
  }
  if (sourceReservoirId.value === targetReservoirId.value) {
    ElMessage.warning('源水库和目标水库不能相同')
    return
  }
  emit('clone', {
    sourceReservoirId: sourceReservoirId.value,
    targetReservoirId: targetReservoirId.value,
  })
}

const filteredTargetOptions = computed(() =>
  RESERVOIR_OPTIONS.filter((o) => o.value !== sourceReservoirId.value),
)
</script>

<template>
  <ElDialog
    :model-value="visible"
    title="克隆配置到其他水库"
    width="480px"
    @update:model-value="(val: boolean) => emit('update:visible', val)"
  >
    <div class="clone-form">
      <div class="clone-field">
        <label>源水库（当前配置）</label>
        <ElSelect v-model="sourceReservoirId" style="width: 100%" disabled>
          <ElOption
            v-for="opt in RESERVOIR_OPTIONS"
            :key="opt.value"
            :label="opt.label"
            :value="opt.value"
          />
        </ElSelect>
      </div>

      <div class="clone-field">
        <label>目标水库</label>
        <ElSelect v-model="targetReservoirId" placeholder="选择目标水库" style="width: 100%">
          <ElOption
            v-for="opt in filteredTargetOptions"
            :key="opt.value"
            :label="opt.label"
            :value="opt.value"
          />
        </ElSelect>
      </div>
    </div>

    <template #footer>
      <ElButton @click="emit('update:visible', false)"> 取消 </ElButton>
      <ElButton type="primary" :disabled="!targetReservoirId" @click="handleClone">
        克隆配置
      </ElButton>
    </template>
  </ElDialog>
</template>

<style scoped lang="scss">
.clone-form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
}

.clone-field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-xs);

  label {
    font-size: var(--font-size-sm);
    font-weight: 500;
    color: var(--color-text);
  }
}
</style>
