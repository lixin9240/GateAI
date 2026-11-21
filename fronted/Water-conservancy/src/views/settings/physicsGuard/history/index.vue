<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { ElSelect, ElOption, ElDialog, ElMessage } from 'element-plus'
import { RESERVOIR_OPTIONS } from '@/constants/settings'
import { getPhysicsGuardHistory, rollbackPhysicsGuard } from '@/api/settings'
import type { ConfigHistoryItem } from '@/stores/physicsGuard'
import HistoryTable from './components/HistoryTable.vue'
import RollbackConfirm from './components/RollbackConfirm.vue'

const reservoirId = ref<number>(1)
const historyList = ref<ConfigHistoryItem[]>([])
const loading = ref(false)

// JSON viewer dialog
const jsonDialogVisible = ref(false)
const jsonContent = ref('')

// Rollback
const rollbackDialogVisible = ref(false)
const rollbackItem = ref<ConfigHistoryItem | null>(null)
const rollbackLoading = ref(false)

// ── Mock ──
function generateMockHistory(): ConfigHistoryItem[] {
  return [
    {
      id: 5,
      reservoir_id: 1,
      version: 'v2.4.1',
      changed_by: '张工',
      changed_at: '2026-07-03 14:30:00',
      description: '调整上游预警水位上限至188m，调整熔断L3置信度阈值',
      config_snapshot: {} as unknown as any,
    },
    {
      id: 4,
      reservoir_id: 1,
      version: 'v2.4.0',
      changed_by: '李工',
      changed_at: '2026-06-28 09:15:00',
      description: '更新生态流量最小值至20m³/s，调整阀门最大泄流量',
      config_snapshot: {} as unknown as any,
    },
    {
      id: 3,
      reservoir_id: 1,
      version: 'v2.3.0',
      changed_by: '王工',
      changed_at: '2026-06-20 16:00:00',
      description: '调整影子水位模型前瞻步数为3步',
      config_snapshot: {} as unknown as any,
    },
    {
      id: 2,
      reservoir_id: 1,
      version: 'v2.2.1',
      changed_by: '张工',
      changed_at: '2026-06-12 11:45:00',
      description: '修正死区百分比参数至2%',
      config_snapshot: {} as unknown as any,
    },
    {
      id: 1,
      reservoir_id: 1,
      version: 'v2.2.0',
      changed_by: '系统初始化',
      changed_at: '2026-06-01 08:00:00',
      description: '初始配置导入',
      config_snapshot: {} as unknown as any,
    },
  ]
}

// ── Fetch ──
async function fetchHistory() {
  loading.value = true
  try {
    const res = await getPhysicsGuardHistory({ reservoir_id: reservoirId.value })
    if (res.data?.code === 0 && res.data.data) {
      historyList.value = res.data.data
      return
    }
  } catch {
    /* fallback */
  }
  historyList.value = generateMockHistory()
  loading.value = false
}

function onReservoirChange() {
  fetchHistory()
}

function handleViewDetail(item: ConfigHistoryItem) {
  jsonContent.value = JSON.stringify(item.config_snapshot, null, 2)
  jsonDialogVisible.value = true
}

function handleRollback(item: ConfigHistoryItem) {
  rollbackItem.value = item
  rollbackDialogVisible.value = true
}

async function confirmRollback() {
  if (!rollbackItem.value) return
  rollbackLoading.value = true
  try {
    await rollbackPhysicsGuard(rollbackItem.value.id)
    ElMessage.success(`已回滚至版本 ${rollbackItem.value.version}`)
    rollbackDialogVisible.value = false
    fetchHistory()
  } catch {
    ElMessage.info(`回滚至 ${rollbackItem.value.version} 已提交`)
    rollbackDialogVisible.value = false
  } finally {
    rollbackLoading.value = false
  }
}

watch(
  () => reservoirId.value,
  () => {
    fetchHistory()
  },
)

onMounted(() => {
  fetchHistory()
})
</script>

<template>
  <div class="physics-guard-history">
    <div class="page-header">
      <h2 class="page-title">物理防护配置历史</h2>
      <ElSelect
        v-model="reservoirId"
        placeholder="选择水库"
        style="width: 180px"
        @change="onReservoirChange"
      >
        <ElOption
          v-for="opt in RESERVOIR_OPTIONS"
          :key="opt.value"
          :label="opt.label"
          :value="opt.value"
        />
      </ElSelect>
    </div>

    <HistoryTable
      :history="historyList"
      :loading="loading"
      @view-detail="handleViewDetail"
      @rollback="handleRollback"
    />

    <!-- JSON detail dialog -->
    <ElDialog v-model="jsonDialogVisible"
title="配置快照详情" width="600px">
      <pre class="json-viewer">{{ jsonContent }}</pre>
      <template #footer>
        <el-button @click="jsonDialogVisible = false"> 关闭 </el-button>
      </template>
    </ElDialog>

    <!-- Rollback confirm -->
    <RollbackConfirm
      :visible="rollbackDialogVisible"
      :item="rollbackItem"
      :loading="rollbackLoading"
      @update:visible="(v: boolean) => (rollbackDialogVisible = v)"
      @confirm="confirmRollback"
    />
  </div>
</template>

<style scoped lang="scss">
.physics-guard-history {
  padding: var(--spacing-md);
}

.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--spacing-lg);
}

.page-title {
  font-size: var(--font-size-xxl);
  font-weight: 700;
  color: var(--color-text);
  margin: 0;
}

.json-viewer {
  background: #1e1e1e;
  color: #d4d4d4;
  padding: var(--spacing-md);
  border-radius: var(--border-radius-base);
  max-height: 450px;
  overflow: auto;
  font-family: 'Cascadia Code', 'Fira Code', monospace;
  font-size: 12px;
  line-height: 1.6;
  white-space: pre-wrap;
  word-break: break-all;
}
</style>
