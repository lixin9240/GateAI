// ============================================================
// 公共表单校验规则
// ============================================================

/** 用户名：≥3 位字母数字下划线 */
export const ACCOUNT_RULE = {
  pattern: /^[a-zA-Z0-9_]{3,}$/,
  message: '用户名至少 3 位，仅允许字母、数字、下划线',
}

/** 密码强度：≥8 位含大小写字母、数字和特殊字符 */
export const PASSWORD_RULE = {
  pattern: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?~`]).{8,32}$/,
  message: '密码 8-32 位，须含大小写字母、数字和特殊字符',
}

/** 密码强度分级检查（5 项规则） */
export function checkPasswordStrength(pwd: string): {
  score: number    // 0-5
  level: 'weak' | 'medium' | 'strong'
  checks: { label: string; passed: boolean }[]
} {
  const checks = [
    { label: '8-32 个字符', passed: pwd.length >= 8 && pwd.length <= 32 },
    { label: '包含大写字母', passed: /[A-Z]/.test(pwd) },
    { label: '包含小写字母', passed: /[a-z]/.test(pwd) },
    { label: '包含数字', passed: /\d/.test(pwd) },
    { label: '包含特殊字符', passed: /[!@#$%^&*()_+\-=[\]{};':"\\|,.<>/?~`]/.test(pwd) },
  ]
  const score = checks.filter((c) => c.passed).length
  let level: 'weak' | 'medium' | 'strong'
  if (score <= 2) level = 'weak'
  else if (score <= 4) level = 'medium'
  else level = 'strong'
  return { score, level, checks }
}

/** 姓名：2-20 字符 */
export const REALNAME_RULE = {
  min: 2,
  max: 20,
  message: '姓名为 2-20 个字符',
}

/** 手机号：11 位 */
export const PHONE_RULE = {
  pattern: /^\d{11}$/,
  message: '请输入正确的 11 位手机号',
}

/** 邮箱格式 */
export const EMAIL_RULE = {
  pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
  message: '请输入正确的邮箱地址',
}

/** Element Plus 表单校验规则（用于 el-form） */
export const FORM_RULES = {
  account: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { pattern: ACCOUNT_RULE.pattern, message: ACCOUNT_RULE.message, trigger: 'blur' },
  ],
  password: [
    { required: true, message: '请输入密码', trigger: 'blur' },
    { pattern: PASSWORD_RULE.pattern, message: PASSWORD_RULE.message, trigger: 'blur' },
  ],
  realname: [
    { required: true, message: '请输入姓名', trigger: 'blur' },
    {
      min: REALNAME_RULE.min,
      max: REALNAME_RULE.max,
      message: REALNAME_RULE.message,
      trigger: 'blur',
    },
  ],
  phone: [{ pattern: PHONE_RULE.pattern, message: PHONE_RULE.message, trigger: 'blur' }],
  email: [{ pattern: EMAIL_RULE.pattern, message: EMAIL_RULE.message, trigger: 'blur' }],
  required: (label: string) => [{ required: true, message: `请输入${label}`, trigger: 'blur' }],
}
