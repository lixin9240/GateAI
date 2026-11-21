<script setup lang="ts">
import { computed } from 'vue'
import { ElDialog, ElButton } from 'element-plus'
import { Lock } from '@element-plus/icons-vue'

const props = defineProps<{
  visible: boolean
  countdownText: string
  released: boolean
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  retry: []
}>()

const dialogVisible = computed({
  get: () => props.visible,
  set: (value: boolean) => emit('update:visible', value),
})
</script>

<template>
  <ElDialog
    v-model="dialogVisible"
    title="账号已锁定"
    width="420px"
    align-center
    append-to-body
    :z-index="5000"
    :close-on-click-modal="released"
    :close-on-press-escape="released"
    :show-close="released"
    class="account-lock-dialog"
  >
    <div class="account-lock-dialog__body">
      <div class="account-lock-dialog__icon">
        <el-icon :size="40"><Lock /></el-icon>
      </div>

      <p v-if="!released" class="account-lock-dialog__text">
        您的账号因连续多次密码错误已被临时锁定。
      </p>
      <p v-else class="account-lock-dialog__text account-lock-dialog__text--success">
        锁定已解除，请重新登录
      </p>

      <p v-if="!released" class="account-lock-dialog__countdown">
        锁定剩余时间：<strong>{{ countdownText }}</strong>
      </p>

      <p class="account-lock-dialog__hint">
        如需紧急解锁，请联系站长或系统管理员。
      </p>
    </div>

    <template #footer>
      <ElButton v-if="released" type="primary" @click="emit('retry')">
        重新登录
      </ElButton>
      <ElButton v-else disabled>
        重新登录
      </ElButton>
    </template>
  </ElDialog>
</template>

<style scoped lang="scss">
.account-lock-dialog {
  &__body {
    text-align: center;
    padding: 8px 4px 0;
  }

  &__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    margin: 0 auto 16px;
    border-radius: 50%;
    background: rgba(245, 34, 45, 0.1);
    color: #f5222d;
  }

  &__text {
    margin: 0 0 12px;
    font-size: 15px;
    line-height: 1.6;
    color: var(--color-text, #303133);

    &--success {
      color: #16a34a;
      font-weight: 600;
    }
  }

  &__countdown {
    margin: 0 0 12px;
    font-size: 14px;
    color: var(--color-text-secondary, #606266);

    strong {
      color: #f5222d;
      font-weight: 600;
    }
  }

  &__hint {
    margin: 0;
    font-size: 13px;
    line-height: 1.6;
    color: var(--color-text-secondary, #909399);
  }
}
</style>
