<script setup lang="ts">
// ============================================================
// 系统设置 — 四Tab：告警阈值 / 权重配置 / 模型管理 / 用户管理
// ============================================================

// ── 1. 外部依赖 ──
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  // ElCard, // 多目标权重配置页面已隐藏
  ElTable,
  ElTableColumn,
  ElInputNumber,
  ElSwitch,
  ElButton,
  ElTag,
  ElPagination,
  ElDialog,
  ElForm,
  ElFormItem,
  ElInput,
  ElSelect,
  ElOption,
  // ElSlider, // 多目标权重配置页面已隐藏
  ElUpload,
  ElMessage,
  ElMessageBox,
} from 'element-plus'
import { Plus, Upload, Search, Refresh, /* Warning, */ Download } from '@element-plus/icons-vue'
import ModelMetricsPanel from './components/ModelMetricsPanel.vue'
import PhysicsGuardPanel from './components/PhysicsGuardPanel.vue'
import PhysicsGuardHistoryPanel from './components/PhysicsGuardHistoryPanel.vue'
import GateInterlockPanel from './components/GateInterlockPanel.vue'
import RolePagePermission from './components/RolePagePermission.vue'
import { FORM_RULES } from '@/constants/validation'
import { useOperationLog } from '@/composables/useOperationLog'
import { useUserStore } from '@/stores/user'
import {
  fetchThresholdList,
  toThresholdUpdatePayload,
  updateThreshold,
  /* getWeights, */
  /* updateWeights, */
  getModels,
  activateModel,
  rollbackModel,
  deployModel,
  uploadModel,
  getUsers,
  createUser,
  updateUser,
  resetUserPassword,
  lockUser,
  unlockUser,
  deleteUser,
  deleteModel,
} from '@/api/settings'
import type { ThresholdRule, /* WeightConfig, */ ModelInfo, SystemUser } from '@/shared/types'
import {
  getUserStatusMeta,
  isUserLoginLocked,
  normalizeSystemUser,
  userNeedsUnlock,
} from '@/utils/userStatus'

const { record: recordLog } = useOperationLog()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const SETTINGS_TAB_NAMES = [
  'thresholds',
  // 'weights', // 多目标权重配置页面已隐藏
  'models',
  'ai-metrics',
  'physics-guard',
  'physics-guard-history',
  'gate-interlock',
  'users',
  'role-pages',
] as const

// ── 5. 响应式数据 ──
const activeTab = ref('thresholds')
const saveLoadingTab1 = ref(false)
// const saveLoadingTab2 = ref(false) // 多目标权重配置页面已隐藏

// Tab1: 阈值
const thresholds = ref<ThresholdRule[]>([])
const thresholdsLoading = ref(false)
const thresholdsEditing = ref<Record<number, ThresholdRule>>({})

// Tab2: 权重（多目标权重配置页面已隐藏，代码保留待后续恢复）
/*
const weights = ref<WeightConfig | null>(null)
const weightForm = ref({ power_weight: 0.4, safety_weight: 0.35, ecology_weight: 0.25 })
const weightLoading = ref(false)
const presetOptions = [
  { label: '均衡方案', power: 0.4, safety: 0.35, ecology: 0.25 },
  { label: '发电优先', power: 0.6, safety: 0.25, ecology: 0.15 },
  { label: '安全优先', power: 0.15, safety: 0.7, ecology: 0.15 },
  { label: '生态优先', power: 0.2, safety: 0.25, ecology: 0.55 },
]

type Preset = (typeof presetOptions)[number]

function applyPreset(p: Preset) {
  weightForm.value = { power_weight: p.power, safety_weight: p.safety, ecology_weight: p.ecology }
}
*/

// 模型状态映射
const modelTypeMap: Record<string, string> = {
  lstm_prediction: 'LSTM 预测',
  dqn_decision: 'DQN 决策',
  fault_detection: '故障检测',
  general: '通用',
}
const modelStatusMap: Record<string, string> = {
  uploaded: '已上传',
  validating: '验证中',
  ready: '就绪',
  active: '激活中',
  deprecated: '已废弃',
}
const healthGradeColor: Record<string, string> = {
  S: '#16a34a',
  A: '#22c55e',
  B: '#f59e0b',
  C: '#f97316',
  D: '#dc2626',
}

// 阈值指标映射
const metricLabelMap: Record<string, string> = {
  upstream_level: '上游水位',
  downstream_level: '下游水位',
  inflow_rate: '入库流量',
  outflow_rate: '出库流量',
  gate_opening: '阀门开度',
  power_output: '发电功率',
}

// 角色
const roleOptions = [
  { label: '系统管理员', value: 1 },
  { label: '调度员', value: 2 },
  { label: '运维人员', value: 3 },
  { label: '站长', value: 4 },
  { label: '算法工程师', value: 5 },
]
const roleLabel = computed(() => {
  const map: Record<number, string> = {}
  roleOptions.forEach((r) => {
    map[r.value] = r.label
  })
  return map
})

// ── CSV 导出工具 ──
const exportingModels = ref(false)
const exportingUsers = ref(false)

function downloadCSV(filename: string, headers: string[], rows: string[][]) {
  // BOM 确保 Excel 正确识别 UTF-8 中文
  const BOM = '﻿'
  const csvContent = [headers, ...rows]
    .map((row) => row.map((cell) => `"${String(cell).replace(/"/g, '""')}"`).join(','))
    .join('\n')
  const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

/** 循环分页拉取全量数据，pageSize 对齐列表默认值 */
const EXPORT_PAGE_SIZE = 10

async function fetchAllModels(): Promise<ModelInfo[]> {
  const first = await getModels({ page: 1, page_size: EXPORT_PAGE_SIZE })
  if (first.data.code !== 0 || !first.data.data) return models.value
  const { list, total } = first.data.data
  const all = [...list]
  const totalPages = Math.ceil(total / EXPORT_PAGE_SIZE)
  for (let p = 2; p <= totalPages; p++) {
    const res = await getModels({ page: p, page_size: EXPORT_PAGE_SIZE })
    if (res.data.code === 0 && res.data.data?.list?.length) {
      all.push(...res.data.data.list)
    }
  }
  return all
}

async function fetchAllUsers(): Promise<SystemUser[]> {
  const first = await getUsers({ page: 1, page_size: EXPORT_PAGE_SIZE })
  if (first.data.code !== 0 || !first.data.data) return users.value
  const { list, total } = first.data.data
  const all: SystemUser[] = list.map((item) =>
    normalizeSystemUser(item as unknown as Record<string, unknown>),
  )
  const totalPages = Math.ceil(total / EXPORT_PAGE_SIZE)
  for (let p = 2; p <= totalPages; p++) {
    const res = await getUsers({ page: p, page_size: EXPORT_PAGE_SIZE })
    if (res.data.code === 0 && res.data.data?.list?.length) {
      all.push(
        ...res.data.data.list.map((item) =>
          normalizeSystemUser(item as unknown as Record<string, unknown>),
        ),
      )
    }
  }
  return all
}

async function exportModels() {
  exportingModels.value = true
  try {
    const list = await fetchAllModels()
    const headers = ['模型名称', '类型', '版本', '状态', '准确率(%)', '大小(MB)', '已下发节点', '健康等级', '综合评分']
    const rows = list.map((m) => [
      m.name,
      modelTypeMap[m.type] ?? m.type,
      m.version,
      modelStatusMap[m.status] ?? m.status,
      String(m.accuracy ?? ''),
      String(m.size),
      String(m.deployed_nodes),
      m.health_grade ?? '',
      m.overall_score != null ? (m.overall_score * 100).toFixed(0) : '',
    ])
    downloadCSV(`模型管理_${new Date().toISOString().slice(0, 10)}.csv`, headers, rows)
    ElMessage.success(`已导出 ${rows.length} 条模型记录`)
  } catch {
    ElMessage.error('导出失败，请稍后重试')
  } finally {
    exportingModels.value = false
  }
}

async function exportUsers() {
  exportingUsers.value = true
  try {
    const list = await fetchAllUsers()
    const headers = ['用户名', '姓名', '角色', '状态', '手机号', '注册时间']
    const rows = list.map((u) => {
      const statusMeta = getUserStatusMeta(u)
      return [
        u.account,
        u.realname,
        roleLabel[u.role_id] || u.role_name || '未知',
        statusMeta.label,
        u.phone,
        u.created_at?.slice(0, 16) ?? '',
      ]
    })
    downloadCSV(`用户管理_${new Date().toISOString().slice(0, 10)}.csv`, headers, rows)
    ElMessage.success(`已导出 ${rows.length} 条用户记录`)
  } catch {
    ElMessage.error('导出失败，请稍后重试')
  } finally {
    exportingUsers.value = false
  }
}

// ── 6. Computed (权重合计)（多目标权重配置页面已隐藏）──
/*
const weightSum = computed(
  () =>
    +(
      weightForm.value.power_weight +
      weightForm.value.safety_weight +
      weightForm.value.ecology_weight
    ).toFixed(2),
)
const weightValid = computed(() => Math.abs(weightSum.value - 1.0) < 0.001)

// ── 联动滑块 ──
let sliderRaf: number | null = null
function onSliderChange(changed: string) {
  if (sliderRaf !== null) return
  sliderRaf = requestAnimationFrame(() => {
    sliderRaf = null
    const keys = ['power_weight', 'safety_weight', 'ecology_weight'] as const
    const changedKey = changed as (typeof keys)[number]
    const rest = 1.0 - weightForm.value[changedKey]
    const others = keys.filter((k) => k !== changedKey)
    const sumOthers = others.reduce((s, k) => s + weightForm.value[k], 0)
    if (sumOthers === 0) {
      others.forEach((k) => {
        weightForm.value[k] = +(rest / others.length).toFixed(2)
      })
    } else {
      others.forEach((k) => {
        weightForm.value[k] = +((rest * weightForm.value[k]) / sumOthers).toFixed(2)
      })
    }
  })
}
*/

// ── 模型上传 ──
const uploadRef = ref()
const uploading = ref(false)
const uploadProgress = ref(0)

async function handleUpload(opts: { file: File }) {
  const formData = new FormData()
  formData.append('file', opts.file)
  formData.append('name', opts.file.name.replace(/\.[^.]+$/, ''))
  formData.append('version', `v${new Date().toISOString().slice(0, 10).replace(/-/g, '')}`)
  formData.append('type', 'general')

  uploading.value = true
  uploadProgress.value = 0
  const timer = setInterval(() => {
    if (uploadProgress.value < 90) uploadProgress.value += 10
  }, 200)

  try {
    const res = await uploadModel(formData)
    clearInterval(timer)
    uploadProgress.value = 100
    if (res.data.code === 0) {
      ElMessage.success(`模型「${opts.file.name}」上传成功，进入验证阶段`)
      uploadRef.value?.clearFiles()
      fetchModels()
    }
  } catch {
    clearInterval(timer)
    uploadProgress.value = 0
  } finally {
    uploading.value = false
  }
}

// ── Mock 数据 ──
const MOCK_THRESHOLDS: ThresholdRule[] = [
  {
    id: 1,
    reservoir_id: 1,
    metric: 'upstream_level',
    warning_upper: 93.0,
    warning_lower: 88.0,
    critical_upper: 95.0,
    critical_lower: 86.0,
    debounce_seconds: 30,
    enabled: 1,
  },
  {
    id: 2,
    reservoir_id: 1,
    metric: 'downstream_level',
    warning_upper: 86.5,
    warning_lower: 84.0,
    critical_upper: 88.0,
    critical_lower: 83.0,
    debounce_seconds: 30,
    enabled: 1,
  },
  {
    id: 3,
    reservoir_id: 1,
    metric: 'inflow_rate',
    warning_upper: 500.0,
    warning_lower: 100.0,
    critical_upper: 600.0,
    critical_lower: 80.0,
    debounce_seconds: 30,
    enabled: 1,
  },
  {
    id: 4,
    reservoir_id: 1,
    metric: 'outflow_rate',
    warning_upper: 450.0,
    warning_lower: 50.0,
    critical_upper: 550.0,
    critical_lower: 30.0,
    debounce_seconds: 30,
    enabled: 1,
  },
  {
    id: 5,
    reservoir_id: 1,
    metric: 'gate_opening',
    warning_upper: 80.0,
    warning_lower: 10.0,
    critical_upper: 90.0,
    critical_lower: 5.0,
    debounce_seconds: 30,
    enabled: 1,
  },
  {
    id: 6,
    reservoir_id: 1,
    metric: 'power_output',
    warning_upper: 200.0,
    warning_lower: 50.0,
    critical_upper: 250.0,
    critical_lower: 30.0,
    debounce_seconds: 30,
    enabled: 0,
  },
]
// MOCK_WEIGHTS（多目标权重配置页面已隐藏，代码保留待后续恢复）
/*
const MOCK_WEIGHTS: WeightConfig = {
  id: 1,
  version: 'v2.1.0',
  enabled: 1,
  power_weight: 0.4,
  safety_weight: 0.35,
  ecology_weight: 0.25,
  preset_name: '均衡方案',
  is_preset: 1,
  updated_at: '2026-07-03 09:00:00',
}
*/
const MOCK_MODELS: ModelInfo[] = [
  {
    id: 1,
    name: 'LSTM 水位预测 v2',
    version: 'v2.3.0',
    type: 'lstm_prediction',
    framework: 'pytorch',
    status: 'active',
    accuracy: 94.2,
    training_date: '2026-06-28',
    size: 128,
    is_active: 1,
    deployed_nodes: 3,
    overall_score: 0.88,
    health_grade: 'A',
  },
  {
    id: 2,
    name: 'DQN 调度决策 v3',
    version: 'v3.0.1',
    type: 'dqn_decision',
    framework: 'pytorch',
    status: 'active',
    accuracy: 88.7,
    training_date: '2026-06-30',
    size: 256,
    is_active: 1,
    deployed_nodes: 3,
    overall_score: 0.82,
    health_grade: 'A',
  },
  {
    id: 3,
    name: '故障检测模型',
    version: 'v1.2.0',
    type: 'fault_detection',
    framework: 'onnx',
    status: 'ready',
    accuracy: 82.5,
    training_date: '2026-06-25',
    size: 64,
    is_active: 0,
    deployed_nodes: 0,
    overall_score: 0.72,
    health_grade: 'B',
  },
  {
    id: 4,
    name: 'DQN 调度决策 v2',
    version: 'v2.9.0',
    type: 'dqn_decision',
    framework: 'pytorch',
    status: 'deprecated',
    accuracy: 85.1,
    training_date: '2026-05-15',
    size: 240,
    is_active: 0,
    deployed_nodes: 0,
    overall_score: 0.68,
    health_grade: 'B',
  },
  {
    id: 5,
    name: 'Physics-LSTM v5.1',
    version: 'v5.1.0',
    type: 'lstm_prediction',
    framework: 'pytorch',
    status: 'validating',
    accuracy: 76.3,
    training_date: '2026-07-02',
    size: 96,
    is_active: 0,
    deployed_nodes: 0,
    overall_score: 0.42,
    health_grade: 'C',
  },
  {
    id: 6,
    name: 'Transformer 水位预测 v1',
    version: 'v1.0.0',
    type: 'lstm_prediction',
    framework: 'pytorch',
    status: 'ready',
    accuracy: 79.8,
    training_date: '2026-07-01',
    size: 180,
    is_active: 0,
    deployed_nodes: 0,
    overall_score: 0.55,
    health_grade: 'C',
  },
  {
    id: 7,
    name: 'CNN 故障检测 v2',
    version: 'v2.1.0',
    type: 'fault_detection',
    framework: 'onnx',
    status: 'active',
    accuracy: 91.3,
    training_date: '2026-06-20',
    size: 72,
    is_active: 1,
    deployed_nodes: 2,
    overall_score: 0.85,
    health_grade: 'A',
  },
  {
    id: 8,
    name: 'DQN 调度决策 v1',
    version: 'v1.5.0',
    type: 'dqn_decision',
    framework: 'pytorch',
    status: 'deprecated',
    accuracy: 78.2,
    training_date: '2026-03-10',
    size: 200,
    is_active: 0,
    deployed_nodes: 0,
    overall_score: 0.58,
    health_grade: 'C',
  },
  {
    id: 9,
    name: 'GRU 水位预测 v3',
    version: 'v3.0.2',
    type: 'lstm_prediction',
    framework: 'pytorch',
    status: 'validating',
    accuracy: 84.6,
    training_date: '2026-07-05',
    size: 110,
    is_active: 0,
    deployed_nodes: 1,
    overall_score: 0.63,
    health_grade: 'B',
  },
  {
    id: 10,
    name: '随机森林故障检测',
    version: 'v1.0.0',
    type: 'fault_detection',
    framework: 'onnx',
    status: 'ready',
    accuracy: 74.5,
    training_date: '2026-05-18',
    size: 45,
    is_active: 0,
    deployed_nodes: 0,
    overall_score: 0.48,
    health_grade: 'C',
  },
  {
    id: 11,
    name: 'LSTM 水位预测 v4',
    version: 'v4.0.0-beta',
    type: 'lstm_prediction',
    framework: 'pytorch',
    status: 'uploaded',
    accuracy: 0,
    training_date: '2026-07-09',
    size: 156,
    is_active: 0,
    deployed_nodes: 0,
    overall_score: 0,
    health_grade: 'D',
  },
  {
    id: 12,
    name: '强化学习调度 v5',
    version: 'v5.0.0-rc1',
    type: 'dqn_decision',
    framework: 'pytorch',
    status: 'validating',
    accuracy: 81.9,
    training_date: '2026-07-08',
    size: 290,
    is_active: 0,
    deployed_nodes: 0,
    overall_score: 0.51,
    health_grade: 'C',
  },
]
// Tab3: 模型
const models = ref<ModelInfo[]>([])
const modelsTotal = ref(0)
const modelsPage = ref(1)
const modelsLoading = ref(false)
const modelKeyword = ref('')
let modelSearchTimer: ReturnType<typeof setTimeout> | null = null

function onModelKeywordInput() {
  if (modelSearchTimer) clearTimeout(modelSearchTimer)
  modelSearchTimer = setTimeout(() => {
    modelsPage.value = 1
    fetchModels()
  }, 300)
}

// Tab4: 用户
const users = ref<SystemUser[]>([])
const usersTotal = ref(0)
const usersPage = ref(1)
const usersLoading = ref(false)
const userKeyword = ref('')
let userSearchTimer: ReturnType<typeof setTimeout> | null = null

function onUserKeywordInput() {
  if (userSearchTimer) clearTimeout(userSearchTimer)
  userSearchTimer = setTimeout(() => {
    usersPage.value = 1
    fetchUsers()
  }, 300)
}
const userDialogVisible = ref(false)
const userDialogMode = ref<'create' | 'edit'>('create')
const userForm = ref({ account: '', password: '', realname: '', role_id: 3 })
const editingUserId = ref<number | null>(null)
const userSubmitting = ref(false)

// 新增用户的表单校验规则（密码无限制，不校验）
const userFormRules = computed(() => {
  if (userDialogMode.value === 'create') {
    return {
      account: FORM_RULES.account,
      realname: FORM_RULES.realname,
    }
  }
  return { realname: FORM_RULES.realname }
})

// ── 7. 方法 ──

// -- Tab1 --
async function fetchThresholds() {
  thresholdsLoading.value = true
  try {
    thresholds.value = await fetchThresholdList(1)
  } catch {
    ElMessage.warning('阈值接口暂不可用，已显示本地示例数据')
    thresholds.value = [...MOCK_THRESHOLDS]
  } finally {
    thresholdsLoading.value = false
  }
}

function startEditThreshold(row: ThresholdRule) {
  thresholdsEditing.value[row.id] = {
    ...row,
    warning_upper: Number(row.warning_upper),
    warning_lower: Number(row.warning_lower),
    critical_upper: Number(row.critical_upper),
    critical_lower: Number(row.critical_lower),
    debounce_seconds: Number(row.debounce_seconds),
    enabled: Number(row.enabled),
  }
}

function cancelEditThreshold(id: number) {
  delete thresholdsEditing.value[id]
}

async function saveThreshold(id: number) {
  const edit = thresholdsEditing.value[id]
  if (!edit) return
  if (edit.warning_lower >= edit.warning_upper || edit.critical_lower >= edit.critical_upper) {
    ElMessage.warning('下限必须小于上限')
    return
  }
  if (edit.debounce_seconds < 10 || edit.debounce_seconds > 120) {
    ElMessage.warning('防抖时间范围 10-120 秒')
    return
  }
  saveLoadingTab1.value = true
  try {
    const res = await updateThreshold(id, toThresholdUpdatePayload(edit))
    if (res.data.code === 0) {
      recordLog(
        '系统设置',
        '修改阈值',
        `更新了${metricLabelMap[edit.metric] ?? edit.metric}告警阈值`,
        1,
      )
      ElMessage.success('保存成功')
      delete thresholdsEditing.value[id]
      await fetchThresholds()
    } else {
      ElMessage.error(res.data.msg || '保存失败')
    }
  } catch {
    ElMessage.error('保存失败，请确认已登录且有管理员权限')
  } finally {
    saveLoadingTab1.value = false
  }
}

// -- Tab2 §8.2 多目标权重（多目标权重配置页面已隐藏，代码保留待后续恢复）--
/*
async function fetchWeights() {
  weightLoading.value = true
  try {
    const res = await getWeights()
    if (res.data?.code === 0 && res.data.data) {
      const d = res.data.data
      weights.value = d
      weightForm.value = {
        power_weight: Number(d.power_weight),
        safety_weight: Number(d.safety_weight),
        ecology_weight: Number(d.ecology_weight),
      }
      weightLoading.value = false
      return
    }
  } catch {
    // API 不可用，降级 Mock
  }
  weights.value = MOCK_WEIGHTS
  weightForm.value = {
    power_weight: MOCK_WEIGHTS.power_weight,
    safety_weight: MOCK_WEIGHTS.safety_weight,
    ecology_weight: MOCK_WEIGHTS.ecology_weight,
  }
  weightLoading.value = false
}

async function saveWeights() {
  if (!weightValid.value) {
    ElMessage.warning('三权重之和必须等于 1.0')
    return
  }
  saveLoadingTab2.value = true
  try {
    await ElMessageBox.confirm('确定保存新的权重配置？保存后将实时推送至边缘端', '确认保存')
  } catch {
    saveLoadingTab2.value = false
    return
  }
  try {
    const body = { ...weightForm.value }
    console.log('[权重保存] 请求体:', JSON.stringify(body))
    await updateWeights(body)
    recordLog(
      '系统设置',
      '修改权重',
      `权重配置更新为发电${(weightForm.value.power_weight * 100).toFixed(0)}%/安全${(weightForm.value.safety_weight * 100).toFixed(0)}%/生态${(weightForm.value.ecology_weight * 100).toFixed(0)}%`,
      1,
    )
    ElMessage.success('权重已保存并推送至边缘端')
  } catch (err: any) {
    const msg = err?.message || err?.msg || ''
    if (msg) {
      ElMessage.error(`保存失败：${msg}`)
    }
    console.error('[权重保存] 错误详情:', err)
  } finally {
    saveLoadingTab2.value = false
  }
}
*/

// -- Tab3: 模型操作 --
async function fetchModels() {
  modelsLoading.value = true
  try {
    const res = await getModels({
      page: modelsPage.value,
      page_size: 10,
      keyword: modelKeyword.value || undefined,
    })
    if (res.data.code === 0 && res.data.data.list.length) {
      models.value = res.data.data.list
      modelsTotal.value = res.data.data.total
      modelsLoading.value = false
      return
    }
  } catch {
    /* fallback */
  }
  let filtered = [...MOCK_MODELS]
  if (modelKeyword.value) {
    const kw = modelKeyword.value.toLowerCase()
    filtered = filtered.filter((m) => m.name.toLowerCase().includes(kw))
  }
  modelsTotal.value = filtered.length
  models.value = filtered.slice((modelsPage.value - 1) * 10, modelsPage.value * 10)
  modelsLoading.value = false
}

async function handleActivateModel(id: number) {
  try {
    await ElMessageBox.confirm('确定激活该模型？激活后旧版本将被标记为废弃', '确认激活')
  } catch {
    return // 用户取消
  }
  try {
    await activateModel(id, {})
    recordLog('系统设置', '激活模型', `激活了模型 ID:${id}`, 1)
    await ElMessageBox.alert('模型已成功激活，旧版本已标记为废弃', '激活成功', {
      confirmButtonText: '确定',
      type: 'success',
    })
    fetchModels()
  } catch (e: any) {
    const msg = e?.msg || e?.message || '激活失败，请稍后重试'
    ElMessage.error(msg)
  }
}

async function handleRollbackModel(id: number) {
  try {
    await ElMessageBox.confirm('确定回滚到上一个版本？', '确认回滚')
  } catch {
    return // 用户取消
  }
  try {
    await rollbackModel(id)
    recordLog('系统设置', '回滚模型', `回滚了模型 ID:${id}`, 1)
    await ElMessageBox.alert('模型已成功回滚到上一个版本', '回滚成功', {
      confirmButtonText: '确定',
      type: 'success',
    })
    fetchModels()
  } catch (e: any) {
    ElMessage.error(e?.message || e?.msg || '回滚失败，请重试')
  }
}

const deployingId = ref<number | null>(null)

async function handleDeployModel(id: number) {
  // 弹窗输入节点 ID
  let ids: { value: string }
  try {
    ids = await ElMessageBox.prompt(
      '请输入目标边缘节点 ID（逗号分隔，可用节点：3~5）',
      '下发模型',
      { confirmButtonText: '下发' },
    )
  } catch {
    return // 用户取消
  }

  const nodeIds = ids.value
    .split(',')
    .map((s: string) => Number(s.trim()))
    .filter(Boolean)
  if (nodeIds.length === 0) {
    ElMessage.warning('请至少输入一个节点 ID')
    return
  }
  const invalidIds = nodeIds.filter((n) => n < 3 || n > 5)
  if (invalidIds.length > 0) {
    ElMessage.warning(`边缘节点 ID ${invalidIds.join('、')} 不存在，当前可用节点ID：3~5`)
    return
  }

  // 调用 API
  deployingId.value = id
  try {
    await deployModel(id, { edge_node_ids: nodeIds })
    recordLog('系统设置', '下发模型', `下发模型 ID:${id} 至 ${nodeIds.length} 个边缘节点`, 1)
    ElMessage.success(`模型已成功下发至 ${nodeIds.length} 个边缘节点`)
    fetchModels()
  } catch (err: unknown) {
    const msg = err && typeof err === 'object' && 'message' in err
      ? (err as { message: string }).message
      : '下发失败，请稍后重试'
    ElMessage.error(msg)
  } finally {
    deployingId.value = null
  }
}

async function handleDeleteModel(id: number) {
  try {
    await ElMessageBox.confirm('确定删除该模型？已激活的模型不可删除', '删除确认', {
      type: 'warning',
    })
  } catch {
    return // 用户取消
  }
  try {
    await deleteModel(id)
    recordLog('系统设置', '删除模型', `删除了模型 ID:${id}`, 1)
    ElMessage.success('模型已删除')
    fetchModels()
  } catch (e: any) {
    ElMessage.error(e?.message || e?.msg || '删除失败，请重试')
  }
}

// -- Tab4: 用户管理 --
async function fetchUsers() {
  usersLoading.value = true
  try {
    const res = await getUsers({
      page: usersPage.value,
      page_size: 10,
      keyword: userKeyword.value || undefined,
    })
    if (res.data.code === 0 && res.data.data) {
      const list = res.data.data.list ?? []
      users.value = list.map((item) =>
        normalizeSystemUser(item as unknown as Record<string, unknown>),
      )
      usersTotal.value = res.data.data.total ?? list.length
      return
    }
    users.value = []
    usersTotal.value = 0
    ElMessage.warning(res.data.msg || '用户列表加载失败')
  } catch (err: unknown) {
    users.value = []
    usersTotal.value = 0
    const msg = err instanceof Error ? err.message : '用户列表加载失败'
    ElMessage.error(msg.includes('登录') || msg.includes('Token') ? '登录已失效，请重新登录' : msg)
  } finally {
    usersLoading.value = false
  }
}

function openUserDialog(mode: 'create' | 'edit', row?: SystemUser) {
  userDialogMode.value = mode
  if (mode === 'edit' && row) {
    editingUserId.value = row.id
    userForm.value = {
      account: row.account,
      password: '',
      realname: row.realname,
      role_id: row.role_id,
    }
  } else {
    editingUserId.value = null
    userForm.value = { account: '', password: '12345678', realname: '', role_id: 3 }
  }
  userDialogVisible.value = true
}

async function submitUser() {
  userSubmitting.value = true
  try {
    if (userDialogMode.value === 'create') {
      await createUser(userForm.value)
      recordLog('系统设置', '创建用户', `创建了新用户「${userForm.value.realname}」`, 1)
      ElMessage.success('用户创建成功')
    } else if (editingUserId.value) {
      // 编辑时不传 password 和 account，只传可修改字段
      const { password, account, ...editData } = userForm.value
      await updateUser(editingUserId.value, editData)
      ElMessage.success('用户更新成功')
    }
    userDialogVisible.value = false
    fetchUsers()
  } catch (err: any) {
    ElMessage.error(err?.message || '操作失败')
  } finally {
    userSubmitting.value = false
  }
}

async function handleLock(row: SystemUser) {
  let reason: string | undefined
  try {
    const result = await ElMessageBox.prompt('请输入锁定原因', '锁定账号', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
    })
    reason = result.value
  } catch {
    return // 用户取消
  }
  try {
    await lockUser(row.id, { reason: reason || '管理员锁定' })
    // 乐观更新本地数据，确保 UI 立即响应（不调 fetchUsers，避免后端旧数据覆盖）
    row.lock_expire_time = new Date(Date.now() + 365 * 86400000).toISOString().replace('T', ' ').slice(0, 19)
    row.is_enabled = 1
    recordLog('系统设置', '锁定账号', `锁定了用户「${row.realname}」`, 1)
    ElMessage.success('账号已锁定')
  } catch (e: any) {
    ElMessage.error(e?.msg || e?.message || '锁定失败，请重试')
  }
}

async function handleUnlock(row: SystemUser) {
  const lockedByLogin = isUserLoginLocked(row)
  try {
    await ElMessageBox.confirm(
      lockedByLogin
        ? `用户「${row.realname}」因密码错误被临时锁定，确定提前解锁？`
        : `确定解锁用户「${row.realname}」？`,
      '解锁确认',
    )
  } catch {
    return // 用户取消
  }
  try {
    await unlockUser(row.id)
    // 乐观更新本地数据
    row.lock_expire_time = undefined
    row.is_enabled = 1
    recordLog('系统设置', '解锁账号', `解锁了用户「${row.realname}」`, 1)
    ElMessage.success('账号已解锁')
  } catch (e: any) {
    ElMessage.error(e?.msg || e?.message || '解锁失败，请重试')
  }
}

async function handleResetPwd(row: SystemUser) {
  try {
    const pwd = await ElMessageBox.prompt('请输入新密码（留空则自动生成）', '重置密码', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      inputType: 'text',
    })
    const val = pwd.value?.trim()
    if (val) {
      if (val.length < 8) {
        ElMessage.warning('密码至少需要8位字符')
        return
      }
      if (!/[a-zA-Z]/.test(val) || !/[0-9]/.test(val)) {
        ElMessage.warning('密码必须包含字母和数字')
        return
      }
    }
    // 填了密码就传给后端，留空传空对象让后端自己生成
    await resetUserPassword(row.id, val ? { new_password: val } : ({} as any))
    recordLog('系统设置', '重置密码', `重置了用户「${row.realname}」的密码`, 1)

    // 如果重置的是自己的密码，退出登录并跳转到登录页
    const isSelf =
      row.id == userStore.userInfo?.id ||
      row.account === userStore.userInfo?.username
    console.log('[resetPwd] self-check:', {
      rowId: row.id,
      rowIdType: typeof row.id,
      rowAccount: row.account,
      storeId: userStore.userInfo?.id,
      storeIdType: typeof userStore.userInfo?.id,
      storeUsername: userStore.userInfo?.username,
      isSelf,
    })
    if (isSelf) {
      ElMessage.success('密码重置成功，请重新登录')
      setTimeout(() => {
        userStore.logout()
        router.push('/login')
      }, 3000)
      return
    }

    ElMessage.success('密码重置成功')
    fetchUsers()
  } catch (err: any) {
    // 用户取消了 prompt（点取消或关闭弹窗）
    if (err === 'cancel' || err === 'close') return
    // API 或其他异常
    const msg = err?.response?.data?.msg || err?.msg || err?.message || '密码重置失败'
    ElMessage.error(msg)
  }
}

async function handleDelete(row: SystemUser) {
  try {
    await ElMessageBox.confirm(`确定删除用户「${row.realname}」？此操作不可撤销`, '删除确认', {
      type: 'warning',
    })
  } catch {
    return // 用户取消
  }
  try {
    await deleteUser(row.id)
    recordLog('系统设置', '删除用户', `删除了用户「${row.realname}」`, 1)
    ElMessage.success('用户已删除')
    fetchUsers()
  } catch (e: any) {
    ElMessage.error(e?.message || e?.msg || '删除失败，请重试')
  }
}

function syncTabFromRoute() {
  // 优先使用路由路径，其次使用 meta.settingsTab
  const pathTabMap: Record<string, string> = {
    '/settings/thresholds': 'thresholds',
    // '/settings/weights': 'weights', // 多目标权重配置页面已隐藏
    '/settings/models': 'models',
    '/settings/users': 'users',
    '/settings/physics-guard': 'physics-guard',
    '/settings/physics-guard-history': 'physics-guard-history',
    '/settings/gate-interlock': 'gate-interlock',
    '/settings/role-pages': 'role-pages',
    '/settings/ai/metrics': 'ai-metrics',
    '/settings/ai/compare': 'ai-metrics',
  }
  const fromPath = pathTabMap[route.path]
  if (fromPath) {
    activeTab.value = fromPath
    return
  }
  // 其次从 meta 读取
  const fromMeta = route.meta.settingsTab as string | undefined
  if (fromMeta && (SETTINGS_TAB_NAMES as readonly string[]).includes(fromMeta)) {
    activeTab.value = fromMeta
  }
}

// ── 按需懒加载：每个 Tab 只加载自己的数据 ──
const TAB_FETCHERS: Record<string, () => void> = {
  thresholds: fetchThresholds,
  // weights: fetchWeights, // 多目标权重配置页面已隐藏
  models: fetchModels,
  users: fetchUsers,
}

function loadActiveTabData() {
  const fetcher = TAB_FETCHERS[activeTab.value]
  if (fetcher) fetcher()
}

watch(() => route.path, () => {
  const prevTab = activeTab.value
  syncTabFromRoute()
  // Tab 没变时 watcher 不触发，手动刷新数据；Tab 变了 watcher 处理
  if (activeTab.value === prevTab) {
    loadActiveTabData()
  }
})

// 切 Tab 时按需加载
watch(activeTab, (tab) => {
  const fetcher = TAB_FETCHERS[tab]
  if (fetcher) fetcher()
})

// ── 生命周期 ──
onMounted(() => {
  syncTabFromRoute()
  loadActiveTabData()
})
</script>

<template>
  <div class="page settings-page">
    <!-- ═══ Tab1: 告警阈值 ═══ -->
    <template v-if="activeTab === 'thresholds'">
      <ElTable v-loading="thresholdsLoading" :data="thresholds" style="width: 100%">
        <ElTableColumn prop="metric" label="监控指标" min-width="140">
          <template #default="scope">
            {{
              metricLabelMap[(scope.row as ThresholdRule).metric] ??
              (scope.row as ThresholdRule).metric
            }}
          </template>
        </ElTableColumn>
        <ElTableColumn label="预警上限" width="130" align="center">
          <template #default="scope">
            <ElInputNumber
              v-if="thresholdsEditing[(scope.row as ThresholdRule).id]"
              v-model="thresholdsEditing[(scope.row as ThresholdRule).id].warning_upper"
              :step="0.1"
              controls-position="right"
              style="width: 110px"
            />
            <span v-else>{{ (scope.row as ThresholdRule).warning_upper }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="预警下限" width="130" align="center">
          <template #default="scope">
            <ElInputNumber
              v-if="thresholdsEditing[(scope.row as ThresholdRule).id]"
              v-model="thresholdsEditing[(scope.row as ThresholdRule).id].warning_lower"
              :step="0.1"
              controls-position="right"
              style="width: 110px"
            />
            <span v-else>{{ (scope.row as ThresholdRule).warning_lower }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="紧急上限" width="130" align="center">
          <template #default="scope">
            <ElInputNumber
              v-if="thresholdsEditing[(scope.row as ThresholdRule).id]"
              v-model="thresholdsEditing[(scope.row as ThresholdRule).id].critical_upper"
              :step="0.1"
              controls-position="right"
              style="width: 110px"
            />
            <span v-else>{{ (scope.row as ThresholdRule).critical_upper }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="紧急下限" width="130" align="center">
          <template #default="scope">
            <ElInputNumber
              v-if="thresholdsEditing[(scope.row as ThresholdRule).id]"
              v-model="thresholdsEditing[(scope.row as ThresholdRule).id].critical_lower"
              :step="0.1"
              controls-position="right"
              style="width: 110px"
            />
            <span v-else>{{ (scope.row as ThresholdRule).critical_lower }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="防抖(秒)" width="120" align="center">
          <template #default="scope">
            <ElInputNumber
              v-if="thresholdsEditing[(scope.row as ThresholdRule).id]"
              v-model="thresholdsEditing[(scope.row as ThresholdRule).id].debounce_seconds"
              :min="10"
              :max="120"
              :step="5"
              controls-position="right"
              style="width: 105px"
            />
            <span v-else>{{ (scope.row as ThresholdRule).debounce_seconds }}s</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="启用" width="70" align="center">
          <template #default="scope">
            <ElSwitch
              v-if="thresholdsEditing[(scope.row as ThresholdRule).id]"
              v-model="thresholdsEditing[(scope.row as ThresholdRule).id].enabled"
              :active-value="1"
              :inactive-value="0"
            />
            <ElTag v-else :type="(scope.row as ThresholdRule).enabled === 1 ? 'success' : 'info'">
              {{ (scope.row as ThresholdRule).enabled === 1 ? '启用' : '停用' }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="操作" width="150" fixed="right" align="center">
          <template #default="scope">
            <template v-if="thresholdsEditing[(scope.row as ThresholdRule).id]">
              <div class="threshold-actions">
                <ElButton
                  type="primary"
                  size="small"
                  :loading="saveLoadingTab1"
                  @click="saveThreshold((scope.row as ThresholdRule).id)"
                >
                  保存
                </ElButton>
                <ElButton
                  size="small"
                  @click="cancelEditThreshold((scope.row as ThresholdRule).id)"
                >
                  取消
                </ElButton>
              </div>
            </template>
            <ElButton
              v-else
              type="primary"
              link
              @click="startEditThreshold(scope.row as ThresholdRule)"
            >
              编辑
            </ElButton>
          </template>
        </ElTableColumn>
      </ElTable>
    </template>

    <!-- ═══ Tab2: 多目标权重 ═══（已隐藏，恢复时删除本注释包裹）-->
    <!--
    <template v-if="activeTab === 'weights'">
      <ElCard v-loading="weightLoading" class="settings-page__weight-card" shadow="never">
        <div class="weight-section">
          <div class="weight-presets">
            <span class="weight-label">预设方案：</span>
            <ElButton
              v-for="p in presetOptions"
              :key="p.label"
              style="margin-right: 8px"
              @click="applyPreset(p)"
            >
              {{ p.label }}
            </ElButton>
          </div>
          <div class="weight-sliders">
            <div class="weight-row">
              <span class="weight-row__label">发电效益</span>
              <ElSlider
                v-model="weightForm.power_weight"
                :min="0"
                :max="1"
                :step="0.01"
                style="flex: 1; margin: 0 16px"
                @input="onSliderChange('power_weight')"
              />
              <span class="weight-row__value"
                >{{ (weightForm.power_weight * 100).toFixed(0) }}%</span
              >
            </div>
            <div class="weight-row">
              <span class="weight-row__label">防洪安全</span>
              <ElSlider
                v-model="weightForm.safety_weight"
                :min="0"
                :max="1"
                :step="0.01"
                style="flex: 1; margin: 0 16px"
                @input="onSliderChange('safety_weight')"
              />
              <span class="weight-row__value"
                >{{ (weightForm.safety_weight * 100).toFixed(0) }}%</span
              >
            </div>
            <div class="weight-row">
              <span class="weight-row__label">生态流量</span>
              <ElSlider
                v-model="weightForm.ecology_weight"
                :min="0"
                :max="1"
                :step="0.01"
                style="flex: 1; margin: 0 16px"
                @input="onSliderChange('ecology_weight')"
              />
              <span class="weight-row__value"
                >{{ (weightForm.ecology_weight * 100).toFixed(0) }}%</span
              >
            </div>
          </div>
          <div class="weight-summary">
            <span>合计：</span>
            <span :class="{ 'weight-summary--invalid': !weightValid }">{{ weightSum }}</span>
            <span v-if="!weightValid" class="weight-summary--warn"
              ><el-icon><Warning /></el-icon>三权重之和必须等于 1.0 才能保存</span
            >
          </div>
          <ElButton
            type="primary"
            :disabled="!weightValid"
            :loading="saveLoadingTab2"
            style="margin-top: 16px"
            @click="saveWeights"
          >
            保存并推送至边缘端
          </ElButton>
        </div>
      </ElCard>
    </template>
    -->

    <!-- ═══ Tab3: 模型管理 ═══ -->
    <template v-if="activeTab === 'models'">
      <div class="settings-page__toolbar">
        <ElInput
          v-model="modelKeyword"
          placeholder="搜索模型名称"
          :prefix-icon="Search"
          clearable
          style="width: 220px"
          @input="onModelKeywordInput()"
        />
        <ElButton :icon="Refresh" @click="fetchModels"> 刷新 </ElButton>
        <ElButton :icon="Download" :loading="exportingModels" @click="exportModels"> 导出CSV </ElButton>
        <ElUpload
          ref="uploadRef"
          :http-request="handleUpload"
          :limit="1"
          accept=".pt,.pth,.onnx,.h5,.pb,.zip"
          :show-file-list="false"
          :on-exceed="() => ElMessage.warning('仅允许上传一个文件')"
          style="display: inline-block; margin-left: auto"
        >
          <ElButton type="primary" :icon="Upload" :loading="uploading">
            {{ uploading ? `上传中 ${uploadProgress}%` : '上传模型' }}
          </ElButton>
          <template #tip>
            <div
              class="upload-tip"
              style="font-size: 12px; color: var(--color-text-secondary); margin-top: 4px"
            >
              支持 .pt / .pth / .onnx / .h5 / .pb / .zip，单文件 ≤500MB
            </div>
          </template>
        </ElUpload>
      </div>
      <ElTable
        v-loading="modelsLoading"
        :data="models"
        stripe
        border
        style="width: 100%; margin-top: 12px"
        table-layout="auto"
      >
        <ElTableColumn prop="name" label="模型名称" min-width="150" />
        <ElTableColumn label="类型" width="100">
          <template #default="scope">
            {{ modelTypeMap[(scope.row as ModelInfo).type] ?? (scope.row as ModelInfo).type }}
          </template>
        </ElTableColumn>
        <ElTableColumn prop="version" label="版本" width="80" />
        <ElTableColumn label="健康状态" width="100" align="center">
          <template #default="scope">
            <ElTag
              v-if="(scope.row as ModelInfo).health_grade"
              :color="healthGradeColor[(scope.row as ModelInfo).health_grade!] ?? '#6b7280'"
              effect="dark"
              size="small"
            >
              {{ (scope.row as ModelInfo).health_grade }} ·
              {{ (((scope.row as ModelInfo).overall_score ?? 0) * 100).toFixed(0) }}
            </ElTag>
            <span v-else>—</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="状态" width="90">
          <template #default="scope">
            <ElTag
              :type="
                ((scope.row as ModelInfo).status === 'active'
                  ? 'success'
                  : (scope.row as ModelInfo).status === 'validating'
                    ? 'warning'
                    : (scope.row as ModelInfo).status === 'deprecated'
                      ? 'danger'
                      : 'info') as 'success' | 'warning' | 'danger' | 'info'
              "
            >
              {{
                modelStatusMap[(scope.row as ModelInfo).status] ?? (scope.row as ModelInfo).status
              }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="accuracy" label="准确率" width="80">
          <template #default="scope"> {{ (scope.row as ModelInfo).accuracy ?? '-' }}% </template>
        </ElTableColumn>
        <ElTableColumn prop="size" label="大小(MB)" width="90" />
        <ElTableColumn prop="deployed_nodes" label="已下发节点" width="100" />
        <ElTableColumn label="操作" width="240" fixed="right">
          <template #default="scope">
            <ElButton
              v-if="(scope.row as ModelInfo).status === 'ready' || (scope.row as ModelInfo).status === 'validating'"
              type="success"
              link
              @click="handleActivateModel((scope.row as ModelInfo).id)"
            >
              激活
            </ElButton>
            <ElButton
              v-else
              type="warning"
              link
              @click="handleRollbackModel((scope.row as ModelInfo).id)"
            >
              回滚
            </ElButton>
            <ElButton
              type="primary"
              link
              :loading="deployingId === (scope.row as ModelInfo).id"
              @click="handleDeployModel((scope.row as ModelInfo).id)"
            >
              下发
            </ElButton>
            <ElButton
              v-if="(scope.row as ModelInfo).status !== 'active' && (scope.row as ModelInfo).status !== 'deprecated'"
              type="danger"
              link
              @click="handleDeleteModel((scope.row as ModelInfo).id)"
            >
              删除
            </ElButton>
          </template>
        </ElTableColumn>
      </ElTable>
      <ElPagination
        v-model:current-page="modelsPage"
        :page-size="10"
        :total="modelsTotal"
        layout="total, prev, pager, next"
        background
        style="margin-top: 12px; justify-content: flex-end"
        @current-change="fetchModels"
      />
    </template>

    <!-- ═══ Tab4: 模型健康度 ═══ -->
    <template v-if="activeTab === 'ai-metrics'">
      <div class="gateai-tab-content">
        <ModelMetricsPanel />
      </div>
    </template>

    <!-- ═══ Tab5: 物理防护配置 ═══ -->
    <template v-if="activeTab === 'physics-guard'">
      <div class="gateai-tab-content">
        <PhysicsGuardPanel />
      </div>
    </template>

    <!-- ═══ Tab5b: 配置变更历史 ═══ -->
    <template v-if="activeTab === 'physics-guard-history'">
      <div class="gateai-tab-content">
        <PhysicsGuardHistoryPanel />
      </div>
    </template>

    <!-- ═══ Tab6: 阀门互锁 ═══ -->
    <template v-if="activeTab === 'gate-interlock'">
      <div class="gateai-tab-content">
        <GateInterlockPanel />
      </div>
    </template>

    <!-- ═══ Tab7: 用户管理 ═══ -->
    <template v-if="activeTab === 'users'">
      <div class="settings-page__toolbar">
        <ElInput
          v-model="userKeyword"
          placeholder="搜索用户名/姓名"
          :prefix-icon="Search"
          clearable
          style="width: 220px"
          @input="onUserKeywordInput()"
        />
        <ElButton :icon="Refresh" @click="fetchUsers"> 刷新 </ElButton>
        <ElButton :icon="Download" :loading="exportingUsers" @click="exportUsers"> 导出CSV </ElButton>
        <ElButton
          type="primary"
          :icon="Plus"
          style="margin-left: auto"
          @click="openUserDialog('create')"
        >
          新增用户
        </ElButton>
      </div>
      <ElTable
        v-loading="usersLoading"
        :data="users"
        stripe
        border
        style="width: 100%; margin-top: 12px"
        table-layout="auto"
      >
        <ElTableColumn type="index" label="#" width="50" align="center" />
        <ElTableColumn prop="account" label="用户名" min-width="100" />
        <ElTableColumn prop="realname" label="姓名" min-width="80" />
        <ElTableColumn label="角色" min-width="110" align="center">
          <template #default="scope">
            <ElTag type="info">
              {{
                roleLabel[(scope.row as SystemUser).role_id] ||
                (scope.row as SystemUser).role_name ||
                '未知'
              }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="状态" width="100" align="center">
          <template #default="scope">
            <ElTag
              :type="getUserStatusMeta(scope.row as SystemUser).type"
              effect="plain"
            >
              {{ getUserStatusMeta(scope.row as SystemUser).label }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="锁定/到期" min-width="150">
          <template #default="scope">
            <span v-if="isUserLoginLocked(scope.row as SystemUser)" class="users-lock-expire">
              {{ (scope.row as SystemUser).lock_expire_time?.slice(0, 16) ?? '—' }}
            </span>
            <span v-else class="users-lock-expire users-lock-expire--muted">—</span>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="phone" label="手机号" min-width="120" />
        <ElTableColumn prop="created_at" label="注册时间" min-width="150" />
        <ElTableColumn label="操作" width="240" fixed="right" align="center">
          <template #default="scope">
            <ElButton
              type="primary"
              link
              @click="openUserDialog('edit', scope.row as SystemUser)"
            >
              编辑
            </ElButton>
            <ElButton link @click="handleResetPwd(scope.row as SystemUser)"> 重置 </ElButton>
            <ElButton
              v-if="!userNeedsUnlock(scope.row as SystemUser)"
              type="warning"
              link
              @click="handleLock(scope.row as SystemUser)"
            >
              锁定
            </ElButton>
            <ElButton
              v-else
              type="success"
              link
              @click="handleUnlock(scope.row as SystemUser)"
            >
              解锁
            </ElButton>
            <ElButton type="danger" link @click="handleDelete(scope.row as SystemUser)">
              删除
            </ElButton>
          </template>
        </ElTableColumn>
      </ElTable>
      <ElPagination
        v-model:current-page="usersPage"
        :page-size="10"
        :total="usersTotal"
        layout="total, prev, pager, next"
        background
        style="margin-top: 12px; justify-content: flex-end"
        @current-change="fetchUsers"
      />

      <!-- 用户弹窗 -->
      <ElDialog
        v-model="userDialogVisible"
        :title="userDialogMode === 'create' ? '新增用户' : '编辑用户'"
        width="480px"
      >
        <ElForm
          :model="userForm"
          label-width="80px"
          :rules="userFormRules"
        >
          <ElFormItem v-if="userDialogMode === 'create'" label="用户名" prop="account">
            <ElInput v-model="userForm.account" placeholder="≥3位字母数字下划线" />
          </ElFormItem>
          <ElFormItem v-if="userDialogMode === 'create'" label="密码">
            <ElInput
              v-model="userForm.password"
              type="password"
              placeholder="请输入密码（选填）"
              show-password
            />
          </ElFormItem>
          <ElFormItem label="姓名" prop="realname">
            <ElInput v-model="userForm.realname" placeholder="2-20个字符" maxlength="20" />
          </ElFormItem>
          <ElFormItem label="角色">
            <ElSelect v-model="userForm.role_id" style="width: 100%">
              <ElOption
                v-for="r in roleOptions"
                :key="r.value"
                :label="r.label"
                :value="r.value"
              />
            </ElSelect>
          </ElFormItem>
        </ElForm>
        <template #footer>
          <ElButton @click="userDialogVisible = false"> 取消 </ElButton>
          <ElButton type="primary" :loading="userSubmitting" @click="submitUser">
            {{ userDialogMode === 'create' ? '创建' : '保存' }}
          </ElButton>
        </template>
      </ElDialog>
    </template>

    <!-- ═══ Tab8: 角色页面权限 ═══ -->
    <template v-if="activeTab === 'role-pages'">
      <RolePagePermission />
    </template>

    <!-- 兜底：activeTab 异常时显示 -->
    <div v-if="!(SETTINGS_TAB_NAMES as readonly string[]).includes(activeTab)" style="padding: 60px; text-align: center; color: #999;">
      <p>页面加载中...</p>
      <p style="font-size: 12px; margin-top: 8px;">当前 Tab: {{ activeTab }}</p>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use '@/assets/styles/text-mixins.scss' as *;
.settings-page {
  // 全局提大表格字号
  :deep(.el-table) {
    font-size: var(--font-size-base);
  }
  :deep(.el-table th) {
    font-size: var(--font-size-base);
    font-weight: 600;
  }
  :deep(.el-table td) {
    font-size: var(--font-size-base);
  }
  :deep(.el-tag) {
    font-size: var(--font-size-sm);
  }

  &__toolbar {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    flex-wrap: wrap;
  }

  &__weight-card {
    :deep(.el-slider__runway) {
      height: 8px;
    }
    :deep(.el-slider__bar) {
      height: 8px;
      transition: width 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }
    :deep(.el-slider__button-wrapper) {
      transition:
        left 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94),
        transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }
    :deep(.el-slider__button) {
      width: 20px;
      height: 20px;
    }
  }
}

.users-lock-expire {
  font-size: var(--font-size-sm);
  color: #d48806;

  &--muted {
    color: var(--color-text-secondary, #c0c4cc);
  }
}

.weight-section {
  display: flex;
  flex-direction: column;
  gap: 36px;
  padding: var(--spacing-lg) 0;
}

.weight-presets {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--spacing-md);
}

.weight-label {
  font-size: var(--font-size-lg);
  color: var(--color-text-secondary);
  margin-right: var(--spacing-md);
}

.weight-sliders {
  display: flex;
  flex-direction: column;
  gap: 40px;
}

.weight-row {
  display: flex;
  align-items: center;
  padding: var(--spacing-sm) 0;
  &__label {
    width: 100px;
    flex-shrink: 0;
    font-size: var(--font-size-lg);
    color: var(--color-text);
    font-weight: 500;
  }
  &__value {
    width: 60px;
    text-align: right;
    font-weight: 700;
    font-size: var(--font-size-lg);
    color: var(--color-primary);
  }
}

.weight-summary {
  display: flex;
  align-items: center;
  gap: var(--spacing-sm);
  font-size: var(--font-size-xl);
  font-weight: 700;
  padding: var(--spacing-lg);
  background: var(--color-bg-dark);
  border-radius: var(--border-radius-base);

  &--invalid {
    color: #ef4444;
  }
  &--warn {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: var(--font-size-base);
    color: #ef4444;
    font-weight: 400;
  }
}

.threshold-actions {
  display: flex;
  gap: 8px;
  justify-content: center;
}

.gateai-tab-content {
  min-height: 400px;
}

.upload-tip {
  font-size: 12px;
  color: var(--color-text-secondary);
  margin-top: 4px;
}
</style>
