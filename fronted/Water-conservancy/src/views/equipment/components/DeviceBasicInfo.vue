<script setup lang="ts">
// ============================================================
// 设备基本信息 — el-descriptions 2 列 + 行内编辑
// ============================================================

import { ref, computed } from 'vue'
import {
  ElDescriptions,
  ElDescriptionsItem,
  ElButton,
  ElInput,
  ElMessage,
} from 'element-plus'
import { Edit, Check, Close } from '@element-plus/icons-vue'
import { DEVICE_TYPE } from '@/constants'
import { updateEquipment } from '@/api/equipment'
import { useOperationLog } from '@/composables/useOperationLog'
import type { EquipmentDetail } from '@/shared/types'

const props = defineProps<{
  detail: EquipmentDetail
}>()

const { record: recordLog } = useOperationLog()

// ── 编辑状态 ──
const editing = ref(false)
const saving = ref(false)
const editForm = ref({
  name: '',
  model: '',
  install_location: '',
  remark: '',
})

const typeLabelMap = computed(() => {
  const map: Record<string, string> = {}
  Object.values(DEVICE_TYPE).forEach((d) => {
    map[d.value as string] = d.label
  })
  return map
})

function startEdit() {
  if (!props.detail) return
  editForm.value = {
    name: props.detail.name,
    model: props.detail.model ?? '',
    install_location: props.detail.install_location ?? '',
    remark: (props.detail as any).remark ?? '',
  }
  editing.value = true
}

function cancelEdit() {
  editing.value = false
}

async function saveEdit() {
  if (!props.detail) return
  saving.value = true
  try {
    const res = await updateEquipment(props.detail.id, {
      name: editForm.value.name,
      model: editForm.value.model,
      install_location: editForm.value.install_location,
      remark: editForm.value.remark,
    })
    if (res.data.code === 0) {
      recordLog('设备管理', '参数修改', `修改设备「${props.detail.name}」基本参数`, 1)
      ElMessage.success('参数保存成功')
      editing.value = false
    }
  } catch {
    ElMessage.error('保存失败，请重试')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="device-basic-info">
    <div class="device-basic-info__header">
      <span class="device-basic-info__label">设备参数</span>
      <template v-if="!editing">
        <ElButton text type="primary" :icon="Edit" size="small" @click="startEdit"> 编辑 </ElButton>
      </template>
      <template v-else>
        <div class="device-basic-info__actions">
          <ElButton
            text
            size="small"
            :icon="Check"
            type="primary"
            :loading="saving"
            @click="saveEdit"
          >
            保存
          </ElButton>
          <ElButton text size="small" :icon="Close" @click="cancelEdit"> 取消 </ElButton>
        </div>
      </template>
    </div>

    <ElDescriptions :column="2" border size="small">
      <ElDescriptionsItem label="设备名称">
        <template v-if="editing">
          <ElInput v-model="editForm.name" size="small" />
        </template>
        <template v-else>
          {{ detail.name }}
        </template>
      </ElDescriptionsItem>

      <ElDescriptionsItem label="设备编号">
        {{ detail.code }}
      </ElDescriptionsItem>

      <ElDescriptionsItem label="设备类型">
        {{ typeLabelMap[detail.type] ?? detail.type }}
      </ElDescriptionsItem>

      <ElDescriptionsItem label="型号规格">
        <template v-if="editing">
          <ElInput v-model="editForm.model" size="small" placeholder="请输入型号规格" />
        </template>
        <template v-else>
          {{ detail.model || '-' }}
        </template>
      </ElDescriptionsItem>

      <ElDescriptionsItem label="安装位置">
        <template v-if="editing">
          <ElInput v-model="editForm.install_location" size="small" placeholder="请输入安装位置" />
        </template>
        <template v-else>
          {{ detail.install_location || '-' }}
        </template>
      </ElDescriptionsItem>

      <ElDescriptionsItem label="所属分组">
        {{ (detail as any).group || '-' }}
      </ElDescriptionsItem>

      <ElDescriptionsItem label="负责人">
        {{ (detail as any).responsible_person || '-' }}
      </ElDescriptionsItem>

      <ElDescriptionsItem label="购入日期">
        {{ (detail as any).purchase_date || '-' }}
      </ElDescriptionsItem>

      <ElDescriptionsItem label="备注" :span="2">
        <template v-if="editing">
          <ElInput
            v-model="editForm.remark"
            type="textarea"
            :rows="2"
            size="small"
            placeholder="请输入备注信息"
          />
        </template>
        <template v-else>
          {{ (detail as any).remark || '-' }}
        </template>
      </ElDescriptionsItem>
    </ElDescriptions>
  </div>
</template>

<style scoped lang="scss">
.device-basic-info {
  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: var(--spacing-sm);
  }

  &__label {
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
  }

  &__actions {
    display: flex;
    gap: var(--spacing-xs);
  }
}
</style>
