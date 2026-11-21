<script setup lang="ts">
// ============================================================
// 个人中心 — 个人信息 / 账户安全 / 操作日志 三卡片布局
// ============================================================

// ── 1. 外部依赖 ──
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  ElCard,
  ElAvatar,
  ElTag,
  ElButton,
  ElDialog,
  ElForm,
  ElFormItem,
  ElInput,
  ElTable,
  ElTableColumn,
  ElPagination,
  ElSelect,
  ElOption,
  ElDatePicker,
  ElProgress,
  ElMessage,
} from 'element-plus'
import { useUserStore, ROLE_LABEL_MAP } from '@/stores/user'
import { checkPasswordStrength, FORM_RULES, PASSWORD_RULE } from '@/constants/validation'
import { changePassword, updateProfile } from '@/api/profile'
import type { OperationLog } from '@/shared/types'

const router = useRouter()
const userStore = useUserStore()

// ── 5. 响应式数据 ──

/** 从 localStorage 读取本地扩展资料（phone/email/registerTime 等 store 不存的信息） */
function loadExtProfile(): {
  realname: string
  phone: string
  email: string
  registerTime: string
} {
  let fromProfile: { realname?: string; phone?: string; email?: string; registerTime?: string } = {}
  try {
    const raw = localStorage.getItem('profile')
    if (raw) fromProfile = JSON.parse(raw)
  } catch {
    /* ignore */
  }
  // phone 优先从 userInfo 读取（首次改密时写入），其次从 profile
  let phone = fromProfile.phone || ''
  try {
    const raw = localStorage.getItem('userInfo')
    if (raw) {
      const ui = JSON.parse(raw)
      if (ui.phone) phone = ui.phone
    }
  } catch { /* ignore */ }
  return {
    realname: fromProfile.realname || '',
    phone,
    email: fromProfile.email || '',
    registerTime: fromProfile.registerTime || new Date().toISOString().slice(0, 10),
  }
}

function saveExtProfile(data: Record<string, string>) {
  const existing = loadExtProfile()
  localStorage.setItem('profile', JSON.stringify({ ...existing, ...data }))
}

const extProfile = ref(loadExtProfile())
const profileLoading = ref(false)

// 个人信息弹窗
const infoVisible = ref(false)
const infoForm = ref({ realname: '', phone: '', email: '' })
const infoSubmitting = ref(false)

// 修改密码弹窗
const pwdVisible = ref(false)
const pwdForm = ref({ old_password: '', new_password: '', confirm_password: '' })
const pwdSubmitting = ref(false)
const showNewPwd = ref(false)

// 操作日志（本地 mock，记录在 localStorage）
const logs = ref<OperationLog[]>([])
const logsTotal = ref(0)
const logsPage = ref(1)
const logsLoading = ref(false)
const logModuleFilter = ref('')
const logDateRange = ref<[string, string] | null>(null)
const logModules = [
  { label: '全部模块', value: '' },
  { label: '登录认证', value: 'auth' },
  { label: '设备管理', value: 'equipment' },
  { label: '系统设置', value: 'settings' },
  { label: '调度决策', value: 'dispatch' },
  { label: '告警管理', value: 'warning' },
  { label: '个人中心', value: 'profile' },
]

import { useOperationLog } from '@/composables/useOperationLog'
const { record: recordLog } = useOperationLog()

// ── 6. Computed ──
const strength = computed(() => checkPasswordStrength(pwdForm.value.new_password))
const strengthColor = computed(() => {
  if (strength.value.level === 'strong') return '#22c55e'
  if (strength.value.level === 'medium') return '#f59e0b'
  return pwdForm.value.new_password ? '#ef4444' : '#c0c4cc'
})

const displayName = computed(
  () => userStore.userInfo?.nickname || userStore.userInfo?.username || '用户',
)

const roleLabel = computed(() => {
  const roles = userStore.userInfo?.roles ?? []
  return roles.map((r) => ROLE_LABEL_MAP[r] ?? r).join('、') || '未分配'
})

/** 注册时间：优先 localStorage，否则用当前时间 */
const registerTime = computed(
  () => extProfile.value.registerTime || new Date().toISOString().slice(0, 10),
)

// ── 7. 方法函数 ──

/** 初始化资料（从 store + localStorage） */
function initProfile() {
  profileLoading.value = true
  // 数据来自 store（username/nickname/roles）+ localStorage（phone/email）
  extProfile.value = loadExtProfile()
  profileLoading.value = false
}

function openInfoDialog() {
  const p = extProfile.value
  infoForm.value = {
    realname: p.realname || userStore.userInfo?.nickname || '',
    phone: p.phone || '',
    email: p.email || '',
  }
  infoVisible.value = true
}

async function submitInfo() {
  infoSubmitting.value = true
  try {
    const userId = userStore.userInfo?.id
    if (!userId) {
      ElMessage.error('用户信息异常，请重新登录后重试')
      return
    }
    // 调用真实 API §8.4.3 PUT /api/settings/users/{id}
    const res = await updateProfile(userId, {
      realname: infoForm.value.realname || undefined,
      phone: infoForm.value.phone || undefined,
    })
    // 校验后端是否真正写入成功
    if (res.data?.code !== 0) {
      ElMessage.error(res.data?.msg || '保存失败，请稍后重试')
      return
    }
    // 同步更新本地缓存 + store（仅后端确认成功后）
    saveExtProfile(infoForm.value)
    extProfile.value = loadExtProfile()
    if (userStore.userInfo) {
      userStore.userInfo.nickname = infoForm.value.realname || userStore.userInfo.nickname
      if (infoForm.value.phone) {
        const updated = { ...userStore.userInfo, phone: infoForm.value.phone }
        userStore.userInfo = updated
        localStorage.setItem('userInfo', JSON.stringify(updated))
        // sessionStorage 必须同步更新：未勾"自动登录"时 loadUserInfo 优先读 sessionStorage
        if (sessionStorage.getItem('userInfo')) {
          sessionStorage.setItem('userInfo', JSON.stringify(updated))
        }
      }
    }
    recordLog('个人中心', '编辑', '修改了个人资料', 1)
    ElMessage.success('资料更新成功')
    infoVisible.value = false
  } catch (err: any) {
    ElMessage.error(err?.message || '保存失败，请稍后重试')
  } finally {
    infoSubmitting.value = false
  }
}

function openPwdDialog() {
  pwdForm.value = { old_password: '', new_password: '', confirm_password: '' }
  pwdVisible.value = true
}

async function submitPassword() {
  if (!pwdForm.value.old_password) {
    ElMessage.warning('请输入当前密码')
    return
  }
  if (!pwdForm.value.new_password) {
    ElMessage.warning('请输入新密码')
    return
  }
  if (pwdForm.value.new_password === pwdForm.value.old_password) {
    ElMessage.warning('新密码不能与当前密码相同')
    return
  }
  if (pwdForm.value.new_password !== pwdForm.value.confirm_password) {
    ElMessage.warning('两次输入的新密码不一致')
    return
  }
  // 强制要求全部 5 项密码规则通过
  if (!PASSWORD_RULE.pattern.test(pwdForm.value.new_password)) {
    ElMessage.warning(PASSWORD_RULE.message)
    return
  }
  if (strength.value.level !== 'strong') {
    ElMessage.warning('新密码未满足全部规则，请按提示完成设置')
    return
  }
  pwdSubmitting.value = true
  try {
    const res = await changePassword(pwdForm.value)
    if (res.data?.code === 0) {
      recordLog('个人中心', '修改密码', '修改了登录密码', 1)
      ElMessage.success('密码修改成功，3 秒后跳转登录')
      pwdVisible.value = false
      setTimeout(() => {
        userStore.logout()
        router.push('/login')
      }, 3000)
    } else {
      ElMessage.error(res.data?.msg || '密码修改失败')
      recordLog('个人中心', '修改密码', '修改密码失败', 0)
    }
  } catch {
    recordLog('个人中心', '修改密码', '修改密码失败', 0)
  } finally {
    pwdSubmitting.value = false
  }
}

/** 种子日志（首次访问时写入） */
function seedLogs() {
  if (localStorage.getItem('operationLogs')) return
  const seed: OperationLog[] = [
    {
      id: 1,
      time: '2026-07-03 10:05:22',
      module: '登录认证',
      type: '登录',
      description: '用户 admin 登录成功',
      result: 1,
    },
    {
      id: 2,
      time: '2026-07-03 09:30:15',
      module: '系统设置',
      type: '修改',
      description: '更新了上游水位告警阈值',
      result: 1,
    },
    {
      id: 3,
      time: '2026-07-02 18:20:00',
      module: '设备管理',
      type: '重启',
      description: '远程重启设备「水泵」',
      result: 1,
    },
    {
      id: 4,
      time: '2026-07-02 16:45:30',
      module: '调度决策',
      type: '执行',
      description: '手动下发阀门开度 35%',
      result: 1,
    },
    {
      id: 5,
      time: '2026-07-02 14:10:00',
      module: '告警管理',
      type: '处置',
      description: '处置告警 ALM-20260702-00078',
      result: 1,
    },
    {
      id: 6,
      time: '2026-07-02 11:30:00',
      module: '个人中心',
      type: '修改密码',
      description: '修改了登录密码',
      result: 1,
    },
    {
      id: 7,
      time: '2026-07-01 09:00:00',
      module: '登录认证',
      type: '登录',
      description: '用户 admin 登录成功',
      result: 1,
    },
    {
      id: 8,
      time: '2026-06-30 17:00:00',
      module: '系统设置',
      type: '创建',
      description: '创建新用户「孙七」',
      result: 1,
    },
    {
      id: 9,
      time: '2026-06-30 15:30:00',
      module: '设备管理',
      type: '状态变更',
      description: '设备「降压模块」标记为维护中',
      result: 1,
    },
    {
      id: 10,
      time: '2026-06-29 08:45:00',
      module: '登录认证',
      type: '登录',
      description: '用户 zhangsan 登录失败（密码错误）',
      result: 0,
    },
  ]
  localStorage.setItem('operationLogs', JSON.stringify(seed))
}

function fetchLogs() {
  logsLoading.value = true
  try {
    const raw = localStorage.getItem('operationLogs')
    let all: OperationLog[] = raw ? JSON.parse(raw) : []
    // 筛选
    if (logModuleFilter.value) {
      all = all.filter((l) => l.module === logModuleFilter.value)
    }
    if (logDateRange.value) {
      const [start, end] = logDateRange.value
      all = all.filter((l) => l.time >= start && l.time <= end + ' 23:59:59')
    }
    all.sort((a, b) => b.id - a.id)
    logsTotal.value = all.length
    const start = (logsPage.value - 1) * 15
    logs.value = all.slice(start, start + 15)
  } finally {
    logsLoading.value = false
  }
}

function onLogFilterChange() {
  logsPage.value = 1
  fetchLogs()
}
function onLogPageChange(p: number) {
  logsPage.value = p
  fetchLogs()
}

// ── 8. 生命周期 ──
onMounted(() => {
  initProfile()
  seedLogs()
  fetchLogs()
})
</script>

<template>
  <div class="page profile-page">
    <!-- ═══ 卡片1: 个人信息 ═══ -->
    <ElCard v-loading="profileLoading" class="profile-card" shadow="never">
      <template #header>
        <div class="profile-card__header">
          <span>个人信息</span>
          <ElButton type="primary" @click="openInfoDialog"> 编辑 </ElButton>
        </div>
      </template>
      <div class="profile-info">
        <div class="profile-info__avatar">
          <ElAvatar
            :size="96"
            style="
              background: linear-gradient(135deg, #1890ff, #00d4ff);
              color: #fff;
              font-size: 38px;
              font-weight: 600;
            "
          >
            {{ displayName.charAt(0) }}
          </ElAvatar>
        </div>
        <div class="profile-info__fields">
          <div class="profile-info__row">
            <span class="profile-info__label">用户名</span>
            <span class="profile-info__value">{{
              userStore.userInfo?.username ?? displayName
            }}</span>
          </div>
          <div class="profile-info__row">
            <span class="profile-info__label">姓名</span>
            <span class="profile-info__value">{{
              extProfile.realname || userStore.userInfo?.nickname || '-'
            }}</span>
          </div>
          <div class="profile-info__row">
            <span class="profile-info__label">角色</span>
            <span class="profile-info__value">
              <ElTag>{{ roleLabel || '未分配' }}</ElTag>
            </span>
          </div>
          <div class="profile-info__row">
            <span class="profile-info__label">邮箱</span>
            <span class="profile-info__value">{{ extProfile.email || '未填写' }}</span>
          </div>
          <div class="profile-info__row">
            <span class="profile-info__label">手机</span>
            <span class="profile-info__value">{{ extProfile.phone || '未填写' }}</span>
          </div>
          <div class="profile-info__row">
            <span class="profile-info__label">注册时间</span>
            <span class="profile-info__value">{{ registerTime }}</span>
          </div>
        </div>
      </div>
    </ElCard>

    <!-- ═══ 卡片2: 账户安全 ═══ -->
    <ElCard class="profile-card" shadow="never">
      <template #header>
        <span>账户安全</span>
      </template>
      <div class="profile-security">
        <div class="profile-security__item">
          <div class="profile-security__info">
            <span class="profile-security__label">登录密码</span>
            <span class="profile-security__desc">定期更换密码可保护账户安全</span>
          </div>
          <ElButton @click="openPwdDialog"> 修改密码 </ElButton>
        </div>
      </div>
    </ElCard>

    <!-- ═══ 卡片3: 操作日志 ═══ -->
    <ElCard class="profile-card" shadow="never">
      <template #header>
        <span>我的操作日志</span>
      </template>
      <div class="profile-logs__filters">
        <ElSelect
          v-model="logModuleFilter"
          placeholder="操作模块"
          clearable
          style="width: 160px"
          @change="onLogFilterChange"
        >
          <ElOption v-for="m in logModules" :key="m.value" :label="m.label" :value="m.value" />
        </ElSelect>
        <ElDatePicker
          v-model="logDateRange"
          type="daterange"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          size="default"
          value-format="YYYY-MM-DD"
          @change="onLogFilterChange"
        />
      </div>
      <ElTable
        v-loading="logsLoading"
        :data="logs"
        class="profile-logs-table"
        style="width: 100%; margin-top: 14px"
      >
        <ElTableColumn prop="time" label="时间" width="190" />
        <ElTableColumn prop="module" label="操作模块" width="120" />
        <ElTableColumn prop="type" label="操作类型" width="130" />
        <ElTableColumn prop="description" label="操作描述" min-width="220" show-overflow-tooltip />
        <ElTableColumn label="操作结果" width="100">
          <template #default="scope">
            <ElTag :type="(scope.row as OperationLog).result === 1 ? 'success' : 'danger'">
              {{ (scope.row as OperationLog).result === 1 ? '成功' : '失败' }}
            </ElTag>
          </template>
        </ElTableColumn>
      </ElTable>
      <ElPagination
        v-model:current-page="logsPage"
        :page-size="15"
        :total="logsTotal"
        layout="total, prev, pager, next"
        background
        class="profile-logs-pagination"
        style="margin-top: 16px; justify-content: flex-end"
        @current-change="onLogPageChange"
      />
    </ElCard>

    <!-- ═══ 编辑资料弹窗 ═══ -->
    <ElDialog v-model="infoVisible" title="编辑个人信息" width="420px">
      <ElForm :model="infoForm" label-width="80px">
        <ElFormItem label="姓名" :rules="FORM_RULES.realname">
          <ElInput v-model="infoForm.realname" maxlength="20" placeholder="2-20个字符" />
        </ElFormItem>
        <ElFormItem label="手机号" :rules="FORM_RULES.phone">
          <ElInput v-model="infoForm.phone" maxlength="11" placeholder="11位手机号" />
        </ElFormItem>
        <ElFormItem label="邮箱" :rules="FORM_RULES.email">
          <ElInput v-model="infoForm.email" placeholder="请输入邮箱地址" />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="infoVisible = false"> 取消 </ElButton>
        <ElButton type="primary" :loading="infoSubmitting" @click="submitInfo"> 保存 </ElButton>
      </template>
    </ElDialog>

    <!-- ═══ 修改密码弹窗 ═══ -->
    <ElDialog v-model="pwdVisible" title="修改密码" width="460px">
      <ElForm :model="pwdForm" label-width="100px">
        <ElFormItem label="当前密码" required>
          <ElInput
            v-model="pwdForm.old_password"
            type="password"
            show-password
            placeholder="请输入当前密码"
          />
        </ElFormItem>
        <ElFormItem label="新密码" required>
          <ElInput
            v-model="pwdForm.new_password"
            :type="showNewPwd ? 'text' : 'password'"
            show-password
            placeholder="≥8位含大小写字母和数字"
          />
        </ElFormItem>
        <!-- 密码强度 -->
        <template v-if="pwdForm.new_password">
          <div class="pwd-strength">
            <div class="pwd-strength__bar">
              <ElProgress
                :percentage="(strength.score / 5) * 100"
                :color="strengthColor"
                :stroke-width="8"
                :show-text="false"
              />
            </div>
            <span class="pwd-strength__label" :class="`pwd-strength__label--${strength.level}`">
              {{ strength.level === 'strong' ? '强' : strength.level === 'medium' ? '中' : '弱' }}
            </span>
          </div>
          <ul class="pwd-checklist">
            <li v-for="(c, i) in strength.checks" :key="i" :class="{ 'is-passed': c.passed }">
              <span class="pwd-checklist__icon">{{ c.passed ? '✓' : '○' }}</span>
              {{ c.label }}
            </li>
          </ul>
        </template>
        <ElFormItem label="确认新密码" required>
          <ElInput
            v-model="pwdForm.confirm_password"
            type="password"
            show-password
            placeholder="请再次输入新密码"
            :class="{
              'is-error':
                pwdForm.confirm_password && pwdForm.new_password !== pwdForm.confirm_password,
            }"
          />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="pwdVisible = false"> 取消 </ElButton>
        <ElButton type="primary" :loading="pwdSubmitting" @click="submitPassword">
          确认修改
        </ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/text-mixins.scss' as *;
.profile-page {
  max-width: var(--content-max-width);
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-lg);
  font-size: 16px;
}

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

// 个人信息
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
  }
}

// 账户安全
.profile-security {
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

// 操作日志
.profile-logs__filters {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;

  :deep(.el-select),
  :deep(.el-date-editor) {
    font-size: 15px;
  }
}

.profile-logs-table {
  :deep(.el-table__header th.el-table__cell) {
    font-size: 16px;
    font-weight: 600;
    padding: 14px 0;
  }

  :deep(.el-table__body td.el-table__cell) {
    font-size: 16px;
    padding: 14px 0;
  }

  :deep(.el-tag) {
    font-size: 14px;
    padding: 4px 10px;
  }
}

.profile-logs-pagination {
  :deep(.el-pagination__total) {
    font-size: 15px;
  }
  :deep(.btn-prev),
  :deep(.btn-next),
  :deep(.el-pager li) {
    min-width: 36px;
    height: 36px;
    line-height: 36px;
    font-size: 15px;
  }
}

// 密码强度
.pwd-strength {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 4px 0 12px 100px;

  &__bar {
    flex: 1;
    :deep(.el-progress-bar__outer) {
      border-radius: 4px;
      background: #e5e7eb;
    }
    :deep(.el-progress-bar__inner) {
      border-radius: 4px;
      transition: width 0.5s ease, background-color 0.5s ease;
    }
  }
  &__label {
    width: 32px;
    text-align: center;
    font-size: 13px;
    font-weight: 700;
    border-radius: 4px;
    padding: 2px 0;

    &--weak {
      color: #ef4444;
      background: rgba(239, 68, 68, 0.1);
    }
    &--medium {
      color: #f59e0b;
      background: rgba(245, 158, 11, 0.1);
    }
    &--strong {
      color: #22c55e;
      background: rgba(34, 197, 94, 0.1);
    }
  }
}

.pwd-checklist {
  list-style: none;
  margin: 0 0 var(--spacing-md) 100px;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 6px 16px;

  li {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #9ca3af;
    transition: color 0.3s ease;
    &.is-passed {
      color: #22c55e;
    }
  }

  &__icon {
    font-size: 12px;
    width: 14px;
    text-align: center;
  }
}

.is-error {
  :deep(.el-input__inner) {
    border-color: #ef4444;
  }
}
</style>
