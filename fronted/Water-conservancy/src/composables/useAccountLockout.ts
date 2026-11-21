import { computed, onBeforeUnmount, ref } from 'vue'
import { formatLockCountdown, resolveLoginError } from '@/utils/loginError'

export function useAccountLockout() {
  const locked = ref(false)
  const lockDialogVisible = ref(false)
  const remainSeconds = ref(0)
  const lockReleased = ref(false)
  const attemptWarning = ref('')
  const loginErrorMessage = ref('')

  let countdownTimer: ReturnType<typeof setInterval> | null = null

  const formDisabled = computed(() => locked.value && !lockReleased.value)
  const countdownText = computed(() => formatLockCountdown(remainSeconds.value))

  function clearCountdown() {
    if (countdownTimer) {
      clearInterval(countdownTimer)
      countdownTimer = null
    }
  }

  function startCountdown(seconds: number) {
    clearCountdown()
    locked.value = true
    lockReleased.value = false
    lockDialogVisible.value = true
    remainSeconds.value = Math.max(0, seconds)

    countdownTimer = setInterval(() => {
      if (remainSeconds.value <= 1) {
        remainSeconds.value = 0
        lockReleased.value = true
        clearCountdown()
        return
      }
      remainSeconds.value -= 1
    }, 1000)
  }

  /** @returns true 表示已锁定并弹出对话框 */
  function handleLoginError(err: unknown, account = ''): boolean {
    loginErrorMessage.value = ''
    const action = resolveLoginError(err, account)

    if (action.type === 'locked') {
      attemptWarning.value = ''
      loginErrorMessage.value = ''
      startCountdown(action.remainSeconds)
      return true
    }

    if (action.type === 'wrong_password') {
      attemptWarning.value = action.warning
      loginErrorMessage.value = action.message
      return false
    }

    attemptWarning.value = ''
    loginErrorMessage.value = action.message
    return false
  }

  function clearLoginError() {
    loginErrorMessage.value = ''
    attemptWarning.value = ''
  }

  function resetLockout() {
    clearCountdown()
    locked.value = false
    lockReleased.value = false
    lockDialogVisible.value = false
    remainSeconds.value = 0
  }

  function retryAfterLockout() {
    resetLockout()
    clearLoginError()
  }

  function closeDialog() {
    if (lockReleased.value) {
      retryAfterLockout()
      return
    }
    lockDialogVisible.value = false
  }

  onBeforeUnmount(clearCountdown)

  return {
    locked,
    lockDialogVisible,
    remainSeconds,
    lockReleased,
    attemptWarning,
    loginErrorMessage,
    formDisabled,
    countdownText,
    handleLoginError,
    clearLoginError,
    retryAfterLockout,
    closeDialog,
    resetLockout,
  }
}
