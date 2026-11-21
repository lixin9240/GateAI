<script setup lang="ts">
import { useRouter } from 'vue-router'
import { ElButton } from 'element-plus'

const router = useRouter()

function goHome() {
  router.push('/dashboard/overview')
}
</script>

<template>
  <div class="not-found">
    <h1>404</h1>
    <p>页面不存在</p>
    <ElButton
type="primary" @click="goHome"> 返回首页 </ElButton>
  </div>
</template>

<style scoped lang="scss">
.not-found {
  height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-md);
  background: var(--color-bg-dark);
  color: var(--color-text);

  h1 {
    font-size: 72px;
    color: var(--color-accent);
  }

  p {
    color: var(--color-text-secondary);
    margin-bottom: var(--spacing-lg);
  }
}
</style>
