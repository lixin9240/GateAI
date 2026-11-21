<script setup lang="ts">
// ============================================================
// 登录页
// ============================================================
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElInput, ElButton, ElCheckbox, ElMessage } from 'element-plus'
import { User, Lock } from '@element-plus/icons-vue'
import { APP_TITLE, DEFAULT_PASSWORD, FORCE_PWD_CHANGE_KEY } from '@/constants'
import { useUserStore } from '@/stores/user'
import { useLoginScene } from '@/composables/useLoginScene'
import { useAccountLockout } from '@/composables/useAccountLockout'
import AccountLockDialog from '@/components/login/AccountLockDialog.vue'
import ForcePasswordChangeDialog from '@/components/login/ForcePasswordChangeDialog.vue'
import { clearLocalFailCount } from '@/utils/loginError'
import logoUrl from '@/assets/images/logo.png'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const username = ref('')
const password = ref('')
const rememberMe = ref(false)
const autoLogin = ref(false)
const loading = ref(false)

const {
  lockDialogVisible,
  lockReleased,
  attemptWarning,
  loginErrorMessage,
  formDisabled,
  countdownText,
  handleLoginError,
  clearLoginError,
  retryAfterLockout,
} = useAccountLockout()

const sceneContainer = ref<HTMLElement | null>(null)
const { webglSupported } = useLoginScene(sceneContainer)

const greeting = '欢迎回来'

// ── 记住密码 / 自动登录 ──
const REMEMBER_KEY = 'remembered_credentials'
const AUTO_LOGIN_KEY = 'auto_login_flag'

function saveCredentials(user: string, pwd: string) {
  try {
    localStorage.setItem(REMEMBER_KEY, JSON.stringify({ u: user, p: encodeURIComponent(pwd), v: 2 }))
  } catch { /* quota exceeded */ }
}

function loadCredentials(): { u: string; p: string } | null {
  try {
    const raw = localStorage.getItem(REMEMBER_KEY)
    if (!raw) return null
    const data = JSON.parse(raw)
    if (!data?.u || !data?.p) return null
    // v2: encodeURIComponent 编码
    if (data.v === 2) return { u: data.u, p: decodeURIComponent(data.p) }
    // v1（无版本标记）: 旧 btoa 编码
    return { u: data.u, p: atob(data.p) }
  } catch { /* corrupted */ }
  return null
}

function clearSavedCredentials() {
  localStorage.removeItem(REMEMBER_KEY)
  localStorage.removeItem(AUTO_LOGIN_KEY)
}

// ── 强制改密 ──
const showForcePwdDialog = ref(false)

onMounted(() => {
  // 已登录但强制改密标记还在 → 弹窗
  if (
    userStore.isLoggedIn &&
    sessionStorage.getItem(FORCE_PWD_CHANGE_KEY) === 'true'
  ) {
    showForcePwdDialog.value = true
    return
  }

  // 已登录 → 无需回填
  if (userStore.isLoggedIn) return

  // 加载已保存的凭证
  const saved = loadCredentials()
  if (saved) {
    username.value = saved.u
    password.value = saved.p
    rememberMe.value = true
  }

  // 自动登录
  if (localStorage.getItem(AUTO_LOGIN_KEY) === 'true' && saved) {
    autoLogin.value = true
    handleLogin()
  }
})

function onForcePwdSuccess() {
  showForcePwdDialog.value = false
  router.push((route.query.redirect as string) || '/dashboard/overview')
}

async function handleLogin() {
  if (formDisabled.value) return
  // 无网络立即提示
  if (!navigator.onLine) {
    loginErrorMessage.value = '网络连接失败，请检查网络后重试'
    return
  }
  if (!username.value || !password.value) {
    loginErrorMessage.value = '请输入用户名和密码'
    return
  }
  clearLoginError()
  loading.value = true
  try {
    await userStore.login({
      username: username.value,
      password: password.value,
      remember: rememberMe.value,
    })
    clearLocalFailCount(username.value)
    ElMessage.success('登录成功')

    // 不勾自动登录 → token 移到 sessionStorage，关标签页自动失效
    if (!autoLogin.value) {
      sessionStorage.setItem('token', userStore.token)
      sessionStorage.setItem('userInfo', JSON.stringify(userStore.userInfo))
      localStorage.removeItem('token')
      localStorage.removeItem('userInfo')
    }

    // 记住密码：保存凭证
    if (rememberMe.value) {
      saveCredentials(username.value, password.value)
      if (autoLogin.value) {
        localStorage.setItem(AUTO_LOGIN_KEY, 'true')
      } else {
        localStorage.removeItem(AUTO_LOGIN_KEY)
      }
    } else {
      clearSavedCredentials()
    }

    // 默认密码 → 强制改密
    if (password.value === DEFAULT_PASSWORD) {
      sessionStorage.setItem(FORCE_PWD_CHANGE_KEY, 'true')
      showForcePwdDialog.value = true
      return
    }

    router.push((route.query.redirect as string) || '/dashboard/overview')
  } catch (err) {
    handleLoginError(err, username.value)
  } finally {
    loading.value = false
  }
}

function onLockRetry() {
  retryAfterLockout()
  password.value = ''
}

// ── Focus Trap：Tab 键只在登录表单内循环，不跳到浏览器 ──
const FOCUSABLE_SELECTOR = 'input:not([type="hidden"]):not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])'
let trapContainer = ref<HTMLElement | null>(null)

function getFocusableElements(): HTMLElement[] {
  if (!trapContainer.value) return []
  return Array.from(trapContainer.value.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR))
}

function onTrapKeydown(e: KeyboardEvent) {
  if (e.key !== 'Tab') return
  const els = getFocusableElements()
  if (els.length === 0) return
  const first = els[0]
  const last = els[els.length - 1]
  const active = document.activeElement

  if (e.shiftKey) {
    // Shift+Tab: 如果焦点在第一个元素，跳到最后一个
    if (active === first || !trapContainer.value?.contains(active as Node)) {
      e.preventDefault()
      last.focus()
    }
  } else {
    // Tab: 如果焦点在最后一个元素，跳到第一个
    if (active === last || !trapContainer.value?.contains(active as Node)) {
      e.preventDefault()
      first.focus()
    }
  }
}
</script>

<template>
  <div class="login-page">
    <div ref="sceneContainer"
class="login-bg" :class="{ 'login-bg--fallback': !webglSupported }" tabindex="-1" aria-hidden="true" />

    <div class="login-form">
      <div ref="trapContainer" class="login-form__card" @keydown="onTrapKeydown">
        <div class="login-form__header">
          <div class="login-form__logo-wrap">
            <img
:src="logoUrl" alt="logo" class="login-form__logo-img" />
          </div>

          <p class="login-form__overline">HYDROPOWER INTELLIGENT SYSTEM</p>
          <h1 class="login-form__brand-title">
            {{ APP_TITLE }}
          </h1>
          <p class="login-form__brand-desc">
            基于深度强化学习与数字孪生的<br >水电站阀门智能调度平台
          </p>
          <p class="login-form__greeting">
            {{ greeting }}
          </p>
        </div>

        <div class="login-form__body">
          <div class="login-field">
            <div class="login-field__icon">
              <el-icon><User /></el-icon>
            </div>
            <ElInput
              v-model="username"
              placeholder="请输入用户名"
              size="large"
              class="login-field__input"
              :readonly="formDisabled"
              @input="clearLoginError"
            />
          </div>

          <div class="login-field">
            <div class="login-field__icon">
              <el-icon><Lock /></el-icon>
            </div>
            <ElInput
              v-model="password"
              type="password"
              placeholder="请输入密码"
              size="large"
              show-password
              class="login-field__input"
              :readonly="formDisabled"
              @input="clearLoginError"
              @keyup.enter="handleLogin"
            />
          </div>

          <p v-if="loginErrorMessage" class="login-form__error" role="alert">
            {{ loginErrorMessage }}
          </p>

          <p v-if="attemptWarning" class="login-form__attempt-warn" role="alert">
            {{ attemptWarning }}
          </p>

          <div class="login-form__options">
            <ElCheckbox v-model="rememberMe"
class="login-form__checkbox"
>
记住密码
</ElCheckbox>
            <ElCheckbox v-model="autoLogin"
class="login-form__checkbox"
>
自动登录
</ElCheckbox>
          </div>

          <ElButton
            type="primary"
            size="large"
            class="login-form__btn"
            :loading="loading"
            :disabled="formDisabled"
            @click="handleLogin"
          >
            登 录
          </ElButton>
        </div>
      </div>
    </div>

    <AccountLockDialog
      v-model:visible="lockDialogVisible"
      :countdown-text="countdownText"
      :released="lockReleased"
      @retry="onLockRetry"
    />

    <ForcePasswordChangeDialog
      v-model:visible="showForcePwdDialog"
      :released="false"
      @success="onForcePwdSuccess"
    />
  </div>
</template>

<style scoped lang="scss">
.login-page {
  position: relative;
  width: 100vw;
  height: 100vh;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.login-bg {
  position: absolute;
  inset: 0;
  z-index: 0;

  &--fallback {
    background: var(--color-layout-gradient-login);
  }
}

.login-form {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 480px;
  padding: 16px;

  &__card {
    width: 100%;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 20px;
    overflow: hidden;
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    box-shadow: 0 24px 48px rgba(0, 0, 0, 0.3);
  }

  &__header {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 36px 32px 20px;
    text-align: center;
  }

  &__logo-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 120px;
    height: 120px;
    margin-bottom: 16px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
  }

  &__logo-img {
    width: 100px;
    height: 100px;
    object-fit: contain;
    filter: drop-shadow(0 8px 24px rgba(0, 0, 0, 0.2));
  }

  &__overline {
    font-family: 'SF Mono', 'Cascadia Code', monospace;
    font-size: 12px;
    letter-spacing: 2px;
    color: rgba(255, 255, 255, 0.35);
    text-transform: uppercase;
    margin: 0 0 8px;
  }

  &__brand-title {
    margin: 0 0 8px;
    font-size: 28px;
    font-weight: 600;
    color: #fff;
  }

  &__brand-desc {
    margin: 0 0 16px;
    font-size: 14px;
    color: rgba(255, 255, 255, 0.45);
    line-height: 1.7;
  }

  &__greeting {
    margin: 0;
    font-size: 18px;
    font-weight: 500;
    color: rgba(255, 255, 255, 0.75);
  }

  &__body {
    padding: 4px 32px 36px;
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  &__attempt-warn {
    margin: -8px 0 0;
    padding: 8px 12px;
    font-size: 13px;
    line-height: 1.5;
    color: #faad14;
    background: rgba(250, 173, 20, 0.12);
    border: 1px solid rgba(250, 173, 20, 0.35);
    border-radius: 8px;
  }

  &__error {
    margin: -8px 0 0;
    padding: 8px 12px;
    font-size: 13px;
    line-height: 1.5;
    color: #ff7875;
    background: rgba(245, 34, 45, 0.15);
    border: 1px solid rgba(245, 34, 45, 0.45);
    border-radius: 8px;
  }

  &__options {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 4px 4px 0;
  }

  &__checkbox {
    :deep(.el-checkbox__label) {
      color: rgba(255, 255, 255, 0.65);
      font-size: 14px;
    }

    :deep(.el-checkbox__inner) {
      width: 16px;
      height: 16px;
      background: rgba(255, 255, 255, 0.06);
      border-color: rgba(255, 255, 255, 0.25);
    }

    :deep(.el-checkbox__input.is-checked .el-checkbox__inner) {
      background: #1890ff;
      border-color: #1890ff;
    }
  }

  &__btn {
    width: 100%;
    height: 48px;
    margin-top: 24px;
    font-size: 17px;
    font-weight: 600;
    letter-spacing: 5px;
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg, #1890ff 0%, #096dd9 100%);
    box-shadow: 0 10px 28px rgba(24, 144, 255, 0.4);
    transition:
      transform 0.2s,
      box-shadow 0.2s;

    &:hover {
      background: linear-gradient(135deg, #40a9ff 0%, #1890ff 100%);
      box-shadow: 0 14px 32px rgba(24, 144, 255, 0.5);
      transform: translateY(-2px);
    }

    &:active {
      transform: translateY(0);
    }
  }
}

.login-field {
  display: flex;
  align-items: stretch;
  height: 48px;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 12px;
  overflow: hidden;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;

  &:focus-within {
    border-color: rgba(24, 144, 255, 0.7);
    box-shadow: 0 0 0 3px rgba(24, 144, 255, 0.12);
    background: rgba(255, 255, 255, 0.08);
  }

  &__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    flex-shrink: 0;
    color: rgba(255, 255, 255, 0.5);
    font-size: 22px;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(255, 255, 255, 0.03);
  }

  &__input {
    flex: 1;
    min-width: 0;

    :deep(.el-input__wrapper) {
      height: 48px;
      padding: 0 14px;
      background: transparent;
      border: none;
      border-radius: 0;
      box-shadow: none !important;

      .el-input__inner {
        color: #fff;
        font-size: 15px;
        line-height: 48px;

        &::placeholder {
          color: rgba(255, 255, 255, 0.3);
          font-size: 13px;
        }
      }
    }

    :deep(.el-input__suffix) {
      color: rgba(255, 255, 255, 0.45);
      font-size: 18px;
    }
  }
}

@media (max-width: 767px) {
  .login-bg {
    background: linear-gradient(170deg, #08121e 0%, #0f1e30 30%, #162c42 100%);
  }

  .login-form {
    padding: 16px;
  }

  .login-form__header {
    padding: 36px 24px 28px;
  }

  .login-form__logo-wrap {
    width: 150px;
    height: 150px;
  }

  .login-form__logo-img {
    width: 130px;
    height: 130px;
  }

  .login-form__brand-title {
    font-size: 28px;
  }

  .login-form__body {
    padding: 8px 24px 40px;
  }
}
</style>
