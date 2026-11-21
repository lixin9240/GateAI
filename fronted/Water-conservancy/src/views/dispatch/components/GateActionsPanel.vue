<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElTable, ElTableColumn, ElTag, ElInput, ElButton } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import type { GateAction } from '@/types/dispatch'
import { fetchGateActions } from '@/api/dispatchPage'
import { ACTION_SOURCE_MAP } from '@/constants/dispatch'

const list = ref<GateAction[]>([])
const keyword = ref('')
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const res = await fetchGateActions(keyword.value.trim() || undefined)
    list.value = res.data.list
  } finally {
    loading.value = false
  }
}

function reset() {
  keyword.value = ''
  load()
}

onMounted(load)
</script>

<template>
  <div class="gate-actions-panel">
    <div class="gate-actions-panel__toolbar">
      <ElInput
        v-model="keyword"
        placeholder="搜索互锁规则/开度"
        :prefix-icon="Search"
        clearable
        style="width: 220px"
        @keyup.enter="load"
      />
      <ElButton type="primary" :loading="loading" @click="load">查询</ElButton>
      <ElButton :loading="loading" @click="reset">重置</ElButton>
    </div>
    <ElTable v-loading="loading" :data="list" stripe border class="gate-actions-panel__table">
      <ElTableColumn prop="acted_at" label="动作时间" min-width="180" />
      <ElTableColumn label="设备" min-width="90">
        <template #default="{ row }">{{ (row as GateAction).equipment_id }}#阀</template>
      </ElTableColumn>
      <ElTableColumn label="开度变化" min-width="170">
        <template #default="{ row }">
          {{ (row as GateAction).previous_opening }}% →
          <template v-if="(row as GateAction).actual_opening != null">
            {{ (row as GateAction).actual_opening }}%
          </template>
          <template v-else>
            {{ (row as GateAction).target_opening }}%<span class="muted">（目标）</span>
          </template>
        </template>
      </ElTableColumn>
      <ElTableColumn label="动作" min-width="80">
        <template #default="{ row }">{{ (row as GateAction).action_type }}</template>
      </ElTableColumn>
      <ElTableColumn label="来源" min-width="120">
        <template #default="{ row }">
          {{
            ACTION_SOURCE_MAP[(row as GateAction).action_source] ??
            (row as GateAction).action_source
          }}
        </template>
      </ElTableColumn>
      <ElTableColumn label="互锁标记" min-width="160">
        <template #default="{ row }">
          <ElTag v-if="(row as GateAction).interlock_rule_name" type="warning" effect="plain">
            {{ (row as GateAction).interlock_rule_name }}
          </ElTag>
          <span v-else class="muted">—</span>
        </template>
      </ElTableColumn>
      <ElTableColumn prop="duration_ms" label="耗时(ms)" min-width="110" align="right" />
    </ElTable>
  </div>
</template>

<style scoped lang="scss">
.gate-actions-panel {
  font-size: 15px;

  &__toolbar {
    display: flex;
    gap: 10px;
    margin-bottom: 14px;
    font-size: 15px;
  }

  &__table {
    width: 100%;
    font-size: 15px;
  }

  :deep(.el-table__header th) {
    font-size: 15px;
    font-weight: 600;
    padding: 12px 0;
  }

  :deep(.el-table__body td) {
    font-size: 15px;
    padding: 12px 0;
  }

  :deep(.el-tag) {
    font-size: 14px;
    padding: 4px 10px;
  }

  .muted {
    color: #94a3b8;
  }
}
</style>
