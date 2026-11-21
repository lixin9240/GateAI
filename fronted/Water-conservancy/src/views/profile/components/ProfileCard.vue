<script setup lang="ts">
// ============================================================
// 个人信息卡片 — 展示用户基本资料，触发编辑弹窗
// ============================================================
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ElCard, ElAvatar, ElTag, ElButton, ElMessageBox } from 'element-plus'
import { useUserStore, ROLE_LABEL_MAP } from '@/stores/user'
import { useProfileStore } from '@/stores/profile'
import { getMyProfile } from '@/api/profile'
import { fixAvatarUrl } from '@/utils'
import EditProfileDialog from './EditProfileDialog.vue'

const router = useRouter()
const userStore = useUserStore()
const profileStore = useProfileStore()
const editVisible = ref(false)
const loading = ref(false)

const avatarChar = computed(() => {
  const name =
    profileStore.userInfo?.realname ||
    userStore.userInfo?.nickname ||
    userStore.userInfo?.username ||
    '用户'
  return name.charAt(0)
})

const username = computed(() => userStore.userInfo?.username ?? '未知')
const realname = computed(
  () => profileStore.userInfo?.realname || userStore.userInfo?.nickname || '-',
)

const roleLabel = computed(() => {
  const roles = userStore.userInfo?.roles ?? []
  if (roles.length > 0) {
    // 使用与用户管理页面同一套映射（ROLE_LABEL_MAP）
    return roles.map((r) => ROLE_LABEL_MAP[r] ?? r).join('、')
  }
  // 兜底：直接从 profileStore 的后端 role_name 显示
  return profileStore.userInfo?.role_name || '未分配'
})

const phone = computed(() => profileStore.userInfo?.phone || '未填写')
const registerTime = computed(() => profileStore.userInfo?.created_at || '-')
/** 头像 URL — ref 手动控制，监听自定义事件 */
const currentAvatarUrl = ref('')

function loadAvatar() {
  const avatarCacheKey = `profile_avatar_${userStore.userInfo?.id ?? '0'}`
  const raw = profileStore.avatarUrl
    || profileStore.userInfo?.avatar
    || localStorage.getItem(avatarCacheKey)
    || ''
  currentAvatarUrl.value = fixAvatarUrl(raw)
}

// 初始化
loadAvatar()

// 监听 EditProfileDialog 保存事件
window.addEventListener('avatar-updated', ((e: CustomEvent) => {
  currentAvatarUrl.value = fixAvatarUrl(e.detail || '')
}) as EventListener)

/** 初始化资料 — 后端优先，失败则用 userStore + profileStore 本地数据 */
async function initProfile() {
  loading.value = true
  try {
    const userId = userStore.userInfo?.id
    // 1) 优先从后端拉取最新数据
    if (userId && navigator.onLine) {
      try {
        const account = userStore.userInfo?.username || ''
        const res = await getMyProfile(userId, account)
        if (res.data?.code === 0 && res.data.data) {
          const d = res.data.data
          // 后端返回 avatar OSS URL，优先用后端的
          const backendAvatar = (d as any).avatar || ''
          // 不再从 profile 接口覆盖角色 — 角色仅由登录 /auth/login 确定
          // 若需刷新权限，请重新登录
          profileStore.setUserInfo({
            id: d.id,
            account: d.account,
            realname: d.realname || userStore.userInfo?.nickname || '',
            avatar: backendAvatar || userStore.userInfo?.avatar || '',
            role_name: d.role_name || (userStore.userInfo?.roles ?? ['admin'])[0],
            phone: d.phone || '未填写',
            email: d.email || '',
            created_at: d.created_at || '',
          })
          if (userStore.userInfo) {
            userStore.userInfo.nickname = d.realname || userStore.userInfo.nickname
            if (d.phone || backendAvatar) {
              if (backendAvatar) userStore.userInfo.avatar = backendAvatar
              if (d.phone) userStore.userInfo.phone = d.phone
              localStorage.setItem('userInfo', JSON.stringify(userStore.userInfo))
              if (sessionStorage.getItem('userInfo')) {
                sessionStorage.setItem('userInfo', JSON.stringify(userStore.userInfo))
              }
            }
          }
          loadAvatar()  // 后端数据写入后刷新头像显示
          return
        }
      } catch {
        // 后端异常 → 兜底本地数据，不弹 toast
      }
    }

    // 2) 兜底：本地数据
    if (!profileStore.userInfo) {
      profileStore.setUserInfo({
        id: userStore.userInfo?.id ?? 1,
        account: userStore.userInfo?.username ?? 'admin',
        realname: userStore.userInfo?.nickname ?? '管理员',
        avatar: userStore.userInfo?.avatar ?? '',
        role_name: (userStore.userInfo?.roles ?? ['admin'])[0],
        phone: userStore.userInfo?.phone || '未填写',
        email: '',
        created_at: new Date().toISOString().slice(0, 10),
      })
    } else if (userStore.userInfo?.phone) {
      profileStore.userInfo.phone = userStore.userInfo.phone
    }
    loadAvatar()  // 本地兜底数据写入后刷新头像
  } finally {
    loading.value = false
  }
}

function openEditDialog() {
  editVisible.value = true
}
function onProfileSaved() {
  initProfile()
}

async function handleLogout() {
  try {
    await ElMessageBox.confirm('确定要退出登录吗？', '退出确认', {
      confirmButtonText: '退出',
      cancelButtonText: '取消',
      type: 'warning',
    })
    userStore.logout()
    router.push('/login')
  } catch {
    // 用户取消
  }
}

onMounted(() => {
  initProfile()
})
</script>

<template>
  <ElCard v-loading="loading" class="profile-card" shadow="never">
    <template #header>
      <div class="profile-card__header">
        <span>个人信息</span>
        <div class="profile-card__actions">
          <ElButton type="primary" @click="openEditDialog"> 编辑 </ElButton>
          <ElButton type="primary" @click="handleLogout"> 退出登录 </ElButton>
        </div>
      </div>
    </template>

    <div class="profile-info">
      <!-- 头像 -->
      <div class="profile-info__avatar">
        <ElAvatar
          :key="currentAvatarUrl"
          :size="96"
          :src="currentAvatarUrl || undefined"
          class="profile-card__avatar"
        >
          {{ avatarChar }}
        </ElAvatar>
      </div>

      <!-- 信息字段 -->
      <div class="profile-info__fields">
        <div class="profile-info__row">
          <span class="profile-info__label">用户名</span>
          <span class="profile-info__value">{{ username }}</span>
        </div>
        <div class="profile-info__row">
          <span class="profile-info__label">姓名</span>
          <span class="profile-info__value">{{ realname }}</span>
        </div>
        <div class="profile-info__row">
          <span class="profile-info__label">角色</span>
          <span class="profile-info__value">
            <ElTag>{{ roleLabel }}</ElTag>
          </span>
        </div>
        <div class="profile-info__row">
          <span class="profile-info__label">手机号</span>
          <span
            class="profile-info__value"
            :class="{ 'profile-info__value--empty': !phone || phone === '未填写' }"
          >{{ phone && phone !== '未填写' ? phone : '未填写' }}</span>
        </div>
        <div class="profile-info__row">
          <span class="profile-info__label">注册时间</span>
          <span class="profile-info__value">{{ registerTime }}</span>
        </div>
      </div>
    </div>

    <!-- 编辑资料弹窗 -->
    <EditProfileDialog
      :visible="editVisible"
      @update:visible="editVisible = $event"
      @saved="onProfileSaved"
    />
  </ElCard>
</template>

<style scoped lang="scss">
.profile-card {
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

.profile-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.profile-card__actions {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
}

.profile-card__avatar {
  :deep(.el-avatar) {
    background: linear-gradient(135deg, var(--color-primary), var(--color-accent)) !important;
    color: #fff;
    font-size: 38px;
    font-weight: 600;
  }
}

.profile-info {
  display: flex;
  gap: var(--spacing-xl);
  align-items: flex-start;

  &__avatar {
    flex-shrink: 0;
  }

  &__fields {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: var(--spacing-md);
  }

  &__row {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--color-border);
  }

  &__label {
    width: 100px;
    flex-shrink: 0;
    color: var(--color-text-secondary);
    font-size: 16px;
  }

  &__value {
    font-size: 17px;
    color: var(--color-text);
    font-weight: 500;

    &--empty {
      color: var(--color-text-placeholder, #c0c4cc);
      font-weight: 400;
      font-style: italic;
    }
  }
}
</style>
