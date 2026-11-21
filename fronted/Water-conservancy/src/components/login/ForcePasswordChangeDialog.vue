<script setup lang="ts">
// ============================================================
// 强制改密弹窗 — 首次登录使用默认密码时弹出，不可关闭/不可绕过
// ============================================================
import { ref, reactive, computed, watch } from 'vue'
import { ElDialog, ElForm, ElFormItem, ElInput, ElButton, ElProgress, ElMessage } from 'element-plus'
import { WarningFilled } from '@element-plus/icons-vue'
import type { FormInstance } from 'element-plus'
import { changePassword } from '@/api/profile'
import { updateProfile } from '@/api/profile'
import { useUserStore } from '@/stores/user'
import { checkPasswordStrength, PASSWORD_RULE } from '@/constants/validation'
import { DEFAULT_PASSWORD, FORCE_PWD_CHANGE_KEY } from '@/constants/auth'

const userStore = useUserStore()

// ── Props & Emits ──
const props = defineProps<{
  visible: boolean
  released: boolean
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  success: []
}>()

// ── 双向绑定 computed ──
const dialogVisible = computed({
  get: () => props.visible,
  set: (value: boolean) => emit('update:visible', value),
})

// ── 表单状态 ──
const formRef = ref<FormInstance>()
const submitting = ref(false)

interface ForcePwdForm {
  new_password: string
  confirm_password: string
  phone: string
}

const pwdForm = reactive<ForcePwdForm>({
  new_password: '',
  confirm_password: '',
  phone: '',
})

// ── 弹窗打开时重置 ──
watch(
  () => props.visible,
  (val) => {
    if (val) {
      pwdForm.new_password = ''
      pwdForm.confirm_password = ''
      pwdForm.phone = ''
      formRef.value?.resetFields()
    }
  },
)

// ── 密码强度 ──
const strength = computed(() => checkPasswordStrength(pwdForm.new_password))

const strengthColor = computed(() => {
  if (strength.value.level === 'strong') return '#22c55e'
  if (strength.value.level === 'medium') return '#f59e0b'
  return pwdForm.new_password ? '#ef4444' : '#c0c4cc'
})

const strengthPercentage = computed(() => (strength.value.score / 3) * 100)

// ── 表单校验规则 ──
const PHONE_RULE = /^1[3-9]\d{9}$/
const rules = {
  new_password: [
    { required: true, message: '请输入新密码', trigger: 'blur' },
    { pattern: PASSWORD_RULE.pattern, message: PASSWORD_RULE.message, trigger: 'blur' },
  ],
  confirm_password: [
    { required: true, message: '请确认新密码', trigger: 'blur' },
  ],
  phone: [
    { required: true, message: '请输入手机号码', trigger: 'blur' },
    { pattern: PHONE_RULE, message: '请输入正确的11位手机号码', trigger: 'blur' },
  ],
}

// ── 提交 ──
async function submitPassword() {
  if (!pwdForm.new_password) {
    ElMessage.warning('请输入新密码')
    return
  }
  if (pwdForm.new_password === DEFAULT_PASSWORD) {
    ElMessage.warning('新密码不能与默认密码相同')
    return
  }
  if (pwdForm.new_password !== pwdForm.confirm_password) {
    ElMessage.warning('两次输入的新密码不一致')
    return
  }
  if (strength.value.level === 'weak') {
    ElMessage.warning('新密码强度太弱，请按规则设置')
    return
  }

  if (!PHONE_RULE.test(pwdForm.phone)) {
    ElMessage.warning('请输入正确的11位手机号码')
    return
  }

  submitting.value = true
  try {
    await changePassword({
      old_password: DEFAULT_PASSWORD,
      new_password: pwdForm.new_password,
      confirm_password: pwdForm.confirm_password,
    })
    // 同步更新手机号到 store + localStorage，个人中心直接读取
    const userId = userStore.userInfo?.id
    if (userId) {
      try {
        await updateProfile(userId, { phone: pwdForm.phone })
        if (userStore.userInfo) {
          const updated = { ...userStore.userInfo, phone: pwdForm.phone }
          userStore.userInfo = updated
          localStorage.setItem('userInfo', JSON.stringify(updated))
          if (sessionStorage.getItem('userInfo')) {
            sessionStorage.setItem('userInfo', JSON.stringify(updated))
          }
        }
      } catch { /* 手机号更新失败不阻塞改密流程 */ }
    }
    sessionStorage.removeItem(FORCE_PWD_CHANGE_KEY)
    ElMessage.success('密码修改成功，正在进入系统...')
    emit('success')
  } catch (err: unknown) {
    const msg =
      err && typeof err === 'object' && 'message' in err
        ? (err as { message: string }).message
        : '密码修改失败，请稍后重试'
    ElMessage.error(msg)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <ElDialog
    v-model="dialogVisible"
    title="首次登录 - 请修改密码"
    width="500px"
    align-center
    append-to-body
    :z-index="5000"
    :close-on-click-modal="released"
    :close-on-press-escape="released"
    :show-close="released"
    class="force-pwd-dialog"
  >
    <!-- ── 图标 + 说明 ── -->
    <div class="force-pwd-dialog__body">
      <div class="force-pwd-dialog__icon">
        <el-icon :size="40"><WarningFilled /></el-icon>
      </div>
      <p class="force-pwd-dialog__text">
        检测到您使用的是系统默认密码，为保障账号安全，请立即修改密码。
      </p>
      <p class="force-pwd-dialog__hint">
        密码需至少 8 位，包含字母和数字。修改完成后将自动进入系统。
      </p>
    </div>

    <!-- ── 表单 ── -->
    <ElForm ref="formRef" :model="pwdForm" :rules="rules" label-width="100px" class="force-pwd-dialog__form">
      <ElFormItem label="新密码" prop="new_password" required>
        <ElInput
          v-model="pwdForm.new_password"
          type="password"
          show-password
          placeholder="至少8位，含字母和数字"
        />
      </ElFormItem>

      <!-- 密码强度 + checklist -->
      <template v-if="pwdForm.new_password">
        <div class="pwd-strength">
          <div class="pwd-strength__bar">
            <ElProgress
              :percentage="strengthPercentage"
              :color="strengthColor"
              :stroke-width="6"
              :show-text="false"
            />
          </div>
          <span class="pwd-strength__label" :style="{ color: strengthColor }">
            {{ strength.level === 'strong' ? '强' : strength.level === 'medium' ? '中' : '弱' }}
          </span>
        </div>

        <ul class="pwd-checklist">
          <li v-for="(c, i) in strength.checks" :key="i" :class="{ 'is-passed': c.passed }">
            <span class="pwd-checklist__icon">{{ c.passed ? '&#10003;' : '&#10005;' }}</span>
            {{ c.label }}
          </li>
        </ul>
      </template>

      <ElFormItem label="确认新密码" prop="confirm_password" required>
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

      <ElFormItem label="手机号码" prop="phone" required>
        <ElInput
          v-model="pwdForm.phone"
          placeholder="请输入手机号码"
          maxlength="11"
        />
      </ElFormItem>
    </ElForm>

    <!-- ── 底部：仅确认按钮，无取消 ── -->
    <template #footer>
      <ElButton type="primary" :loading="submitting" size="large" @click="submitPassword">
        确认修改
      </ElButton>
    </template>
  </ElDialog>
</template>

<style scoped lang="scss">
// ── 弹窗主题 ──
.force-pwd-dialog {
  &__body {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0 0 20px;
    text-align: center;
  }

  &__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: rgba(250, 173, 20, 0.1);
    color: #faad14;
    margin-bottom: 16px;
  }

  &__text {
    margin: 0 0 8px;
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text);
    line-height: 1.6;
  }

  &__hint {
    margin: 0;
    font-size: 13px;
    color: #909399;
    line-height: 1.5;
  }

  &__form {
    padding: 0 10px;
  }
}

// ── 密码强度 ──
.pwd-strength {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: -8px 0 8px 100px;

  &__bar {
    flex: 1;
    max-width: 200px;
  }

  &__label {
    font-size: 13px;
    font-weight: 600;
    min-width: 24px;
  }
}

// ── 密码规则清单 ──
.pwd-checklist {
  list-style: none;
  margin: 0 0 12px 100px;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 4px 16px;

  li {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #909399;

    &.is-passed {
      color: #22c55e;
    }
  }

  &__icon {
    font-weight: 700;
    width: 14px;
  }
}

// ── 确认密码不匹配高亮 ──
.is-error {
  :deep(.el-input__inner) {
    border-color: #ef4444;
  }
}

// ── Element Plus 微调 ──
:deep(.el-dialog__header) {
  border-bottom: 1px solid var(--el-border-color-light);
  padding: 20px 24px 16px;
}

:deep(.el-dialog__body) {
  padding: 24px 24px 8px;
}

:deep(.el-dialog__footer) {
  padding: 12px 24px 20px;
  text-align: center;
}
</style>
