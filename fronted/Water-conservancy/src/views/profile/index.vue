<script setup lang="ts">
// ============================================================
// 个人中心 — 重构后的入口页（三卡片布局）
// 数据流通过 Pinia stores，不直接读写 localStorage
// ============================================================
import { ref, onMounted } from 'vue'
import { ElCard, ElButton } from 'element-plus'
import { useProfileStore } from '@/stores/profile'
import { useUserStore } from '@/stores/user'
import ProfileCard from './components/ProfileCard.vue'
import PasswordChangeDialog from './components/PasswordChangeDialog.vue'
import MyOperationLogs from './components/MyOperationLogs.vue'

// ── Stores ──

const userStore = useUserStore()
const profileStore = useProfileStore()

// ── 修改密码弹窗 ──

const pwdVisible = ref(false)

function openPwdDialog() {
  pwdVisible.value = true
}

// ── 初始化数据 ──

const loading = ref(false)

function initData() {
  loading.value = true
  // 从 localStorage 恢复头像到 store（key 按用户 ID 隔离）
  const avatarCacheKey = `profile_avatar_${userStore.userInfo?.id ?? '0'}`
  if (!profileStore.avatarUrl) {
    profileStore.avatarUrl = localStorage.getItem(avatarCacheKey) || ''
  }
  if (!profileStore.userInfo) {
    profileStore.setUserInfo({
      id: userStore.userInfo?.id ?? 1,
      account: userStore.userInfo?.username ?? 'admin',
      realname: userStore.userInfo?.nickname ?? '管理员',
      avatar: localStorage.getItem(avatarCacheKey) || userStore.userInfo?.avatar || '',
      role_name: (userStore.userInfo?.roles ?? ['admin'])[0] ?? 'admin',
      phone: userStore.userInfo?.phone || '未填写',
      created_at: new Date().toISOString().slice(0, 10),
    })
  }
  loading.value = false
}

// ── 生命周期 ──

onMounted(() => {
  initData()
})
</script>

<template>
  <div v-loading="loading"
class="profile-page">
    <!-- 卡片1: 个人信息 -->
    <ProfileCard />

    <!-- 卡片2: 账户安全 -->
    <ElCard class="security-card"
shadow="never">
      <template #header>
        <span>账户安全</span>
      </template>
      <div class="security-card__body">
        <div class="security-card__item">
          <div class="security-card__info">
            <span class="security-card__label">登录密码</span>
            <span class="security-card__desc">定期更换密码可保护账户安全</span>
          </div>
          <ElButton @click="openPwdDialog"> 修改密码 </ElButton>
        </div>
      </div>
    </ElCard>

    <!-- 卡片3: 登录日志 -->
    <ElCard class="logs-card"
shadow="never">
      <template #header>
        <span>登录日志</span>
      </template>
      <MyOperationLogs />
    </ElCard>

    <!-- 修改密码弹窗 -->
    <PasswordChangeDialog :visible="pwdVisible"
@update:visible="pwdVisible = $event" />
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/text-mixins.scss' as *;
.profile-page {
  max-width: var(--content-max-width, 1200px);
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
  font-size: 16px;
}

// 卡片通用样式
.security-card,
.logs-card {
  :deep(.el-card__header) {
    padding: 18px 24px;
    font-weight: 600;
    font-size: 20px;
    border-bottom: 1px solid var(--color-border);
  }

  :deep(.el-card__body) {
    padding: 22px 24px;
  }

  :deep(.el-button) {
    font-size: 15px;
    padding: 10px 18px;
  }
}

// 账户安全
.security-card {
  &__body {
    // 继承卡片 body padding
  }

  &__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 0;
  }

  &__info {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  &__label {
    font-size: 17px;
    font-weight: 600;
    color: var(--color-text);
  }

  &__desc {
    font-size: 15px;
    color: var(--color-text-secondary);
  }
}
</style>
