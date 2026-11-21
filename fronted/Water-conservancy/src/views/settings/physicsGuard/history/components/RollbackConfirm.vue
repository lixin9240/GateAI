<script setup lang="ts">
import { computed } from 'vue'
import { ElDialog, ElButton } from 'element-plus'
import { WarningFilled } from '@element-plus/icons-vue'
import type { ConfigHistoryItem } from '@/stores/physicsGuard'

const props = defineProps<{
  visible: boolean
  item: ConfigHistoryItem | null
  loading: boolean
}>()

const emit = defineEmits<{
  (e: 'update:visible', val: boolean): void
  (e: 'confirm'): void
}>()

const itemVersion = computed(() => props.item?.version ?? '--')
const itemDesc = computed(() => props.item?.description ?? '--')
const itemTime = computed(() => props.item?.changed_at?.replace('T', ' ') ?? '--')
</script>

<template>
  <ElDialog
    :model-value="visible"
    title="确认回滚配置"
    width="480px"
    @update:model-value="(val: boolean) => emit('update:visible', val)"
  >
    <div class="rollback-confirm">
      <div class="confirm-icon">
        <el-icon :size="48"
color="#E6A23C">
          <WarningFilled />
        </el-icon>
      </div>

      <div class="confirm-info">
        <p class="confirm-text">
          确定将配置回滚至版本 <strong>{{ itemVersion }}</strong>？
        </p>

        <div class="info-card">
          <div class="info-row">
            <span class="info-label">版本号</span>
            <span class="info-value">{{ itemVersion }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">变更时间</span>
            <span class="info-value">{{ itemTime }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">变更说明</span>
            <span class="info-value">{{ itemDesc }}</span>
          </div>
        </div>

        <p class="confirm-warn">
          <el-icon :size="14">
            <WarningFilled />
          </el-icon>
          回滚后将生成新版本号，当前配置将被保留在历史记录中。
        </p>
      </div>
    </div>

    <template #footer>
      <ElButton @click="emit('update:visible', false)"> 取消 </ElButton>
      <ElButton type="warning" :loading="loading" @click="emit('confirm')"> 确认回滚 </ElButton>
    </template>
  </ElDialog>
</template>

<style scoped lang="scss">
.rollback-confirm {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--spacing-lg);
  padding: var(--spacing-md) 0;
}

.confirm-icon {
  //
}

.confirm-info {
  text-align: center;
}

.confirm-text {
  font-size: var(--font-size-base);
  color: var(--color-text);
  margin-bottom: var(--spacing-md);

  strong {
    color: var(--color-primary);
    font-size: var(--font-size-lg);
  }
}

.info-card {
  background: var(--color-bg-dark);
  border-radius: var(--border-radius-base);
  padding: var(--spacing-md);
  text-align: left;
  margin-bottom: var(--spacing-md);
}

.info-row {
  display: flex;
  gap: var(--spacing-md);
  padding: var(--spacing-xs) 0;

  .info-label {
    width: 80px;
    flex-shrink: 0;
    font-size: var(--font-size-xs);
    color: var(--color-text-secondary);
  }

  .info-value {
    font-size: var(--font-size-sm);
    color: var(--color-text);
  }
}

.confirm-warn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  font-size: var(--font-size-xs);
  color: var(--color-warning);
}
</style>
