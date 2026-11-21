<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { ElSelect, ElOption, ElButton, ElMessage, ElMessageBox, ElAlert } from 'element-plus'
import { RESERVOIR_OPTIONS } from '@/constants/settings'
import { getPhysicsGuard, updatePhysicsGuard } from '@/api/settings'
import { clonePhysicsGuardConfig } from '@/api/gateaiSettings'
import { mapBackendPhysicsGuardConfig } from '@/api/physicsGuardAdapter'
import type { BackendPhysicsGuardRaw } from '@/api/physicsGuardAdapter'
import { usePhysicsGuardStore } from '@/stores/physicsGuard'
import type { PhysicsGuardConfig } from '@/stores/physicsGuard'
import PhysicsGuardForm from './components/PhysicsGuardForm.vue'
import WaterLevelPreview from './components/WaterLevelPreview.vue'
import ConfigDiffDialog from './components/ConfigDiffDialog.vue'
import CloneConfigDialog from './components/CloneConfigDialog.vue'

const store = usePhysicsGuardStore()

const reservoirId = ref<number>(1)
const config = ref<PhysicsGuardConfig | null>(null)
const configLoading = ref(false)
const isSaving = ref(false)

// Edited config that the form modifies in-place
const editedConfig = ref<PhysicsGuardConfig | null>(null)

const diffDialogVisible = ref(false)
const cloneDialogVisible = ref(false)

const isDirty = computed(() => {
  if (!config.value || !editedConfig.value) return false
  return JSON.stringify(config.value) !== JSON.stringify(editedConfig.value)
})

// ── Mock ──
function generateMockConfig(): PhysicsGuardConfig {
  return {
    id: 1,
    reservoir_id: reservoirId.value,
    version: 'v2.4.1',
    upstream_danger: 190,
    upstream_emergency: 193,
    upstream_warning: 188,
    upstream_min: 167,
    ideal_min: 178,
    ideal_max: 188,
    downstream_danger: 128,
    downstream_max: 130,
    downstream_min: 115,
    eco_flow_min: 20,
    reservoir_area: 15000000,
    max_level_change_per_hour: 2.0,
    shadow_lookahead_steps: 3,
    shadow_danger_offset: 3.0,
    deadband_percent: 2,
    max_rate_per_hour: 10,
    fusion_l3_confidence: 0.7,
    fusion_l3_risk: 0.3,
    fusion_l2_confidence: 0.5,
    fusion_l2_risk: 0.1,
    gate_max_discharge: [300, 200, 250],
  }
}

// ── Fetch ──
async function fetchConfig() {
  configLoading.value = true
  try {
    const res = await getPhysicsGuard({ reservoir_id: reservoirId.value })
    if (res.data?.code === 0 && res.data.data) {
      const mapped = mapBackendPhysicsGuardConfig(res.data.data as BackendPhysicsGuardRaw)
      config.value = { ...mapped, version: (res.data.data as BackendPhysicsGuardRaw).config_version } as PhysicsGuardConfig
      editedConfig.value = JSON.parse(JSON.stringify(config.value))
      store.setConfig(config.value)
      return
    }
  } catch {
    /* fallback */
  }
  const mock = generateMockConfig()
  config.value = mock
  editedConfig.value = JSON.parse(JSON.stringify(mock))
  store.setConfig(mock)
  configLoading.value = false
}

function onReservoirChange() {
  fetchConfig()
}

function handleFormUpdate(updated: PhysicsGuardConfig) {
  editedConfig.value = updated
}

function handleSave() {
  if (!isDirty.value) {
    ElMessage.info('配置未修改')
    return
  }
  diffDialogVisible.value = true
}

async function confirmSave() {
  if (!editedConfig.value) return
  diffDialogVisible.value = false
  isSaving.value = true
  try {
    await updatePhysicsGuard(editedConfig.value.id, editedConfig.value)
    ElMessage.success('配置已保存，新版本已生成')
    config.value = JSON.parse(JSON.stringify(editedConfig.value))
    store.setConfig(config.value!)
  } catch {
    ElMessage.error('保存失败')
  } finally {
    isSaving.value = false
  }
}

function handleReset() {
  if (config.value) {
    editedConfig.value = JSON.parse(JSON.stringify(config.value))
    ElMessage.info('已重置为初始配置')
  }
}

function handleClone(data: { sourceReservoirId: number; targetReservoirId: number }) {
  cloneDialogVisible.value = false
  clonePhysicsGuardConfig(data.sourceReservoirId, data.targetReservoirId)
    .then((res) => {
      ElMessage.success(res.msg || '配置已克隆')
      if (data.targetReservoirId === reservoirId.value) {
        fetchConfig()
      }
    })
    .catch(() => {
      ElMessage.error('克隆失败')
    })
}

onMounted(() => {
  fetchConfig()
})
</script>

<template>
  <div class="physics-guard-page">
    <div class="page-header">
      <h2 class="page-title">物理防护配置</h2>
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

    <!-- Dirty warning -->
    <ElAlert
      v-if="isDirty"
      title="配置已修改，保存后将生成新版本"
      type="warning"
      :closable="false"
      show-icon
      style="margin-bottom: var(--spacing-md)"
    />

    <div class="main-layout">
      <!-- Left: Form (60%) -->
      <div class="main-left">
        <PhysicsGuardForm
          :config="editedConfig"
          :loading="configLoading"
          @update:config="handleFormUpdate"
        />
      </div>

      <!-- Right: Water level preview (40%) -->
      <div class="main-right">
        <WaterLevelPreview :config="editedConfig" />
      </div>
    </div>

    <!-- Bottom actions -->
    <div class="bottom-actions">
      <ElButton type="primary" :loading="isSaving" @click="handleSave"> 保存配置 </ElButton>
      <ElButton :disabled="!isDirty" @click="handleReset"> 重置修改 </ElButton>
      <ElButton @click="cloneDialogVisible = true"> 克隆配置到其他水库 </ElButton>
    </div>

    <!-- Diff dialog -->
    <ConfigDiffDialog
      :visible="diffDialogVisible"
      :old-config="config"
      :new-config="editedConfig"
      @update:visible="(v: boolean) => (diffDialogVisible = v)"
      @confirm="confirmSave"
    />

    <!-- Clone dialog -->
    <CloneConfigDialog
      :visible="cloneDialogVisible"
      @update:visible="(v: boolean) => (cloneDialogVisible = v)"
      @clone="handleClone"
    />
  </div>
</template>

<style scoped lang="scss">
.physics-guard-page {
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

.main-layout {
  display: flex;
  gap: var(--spacing-lg);
}

.main-left {
  flex: 0 0 60%;
  min-width: 0;
}

.main-right {
  flex: 0 0 38%;
  min-width: 0;
}

.bottom-actions {
  display: flex;
  align-items: center;
  gap: var(--spacing-md);
  margin-top: var(--spacing-lg);
  padding-top: var(--spacing-md);
  border-top: 1px solid var(--color-border);
}
</style>
