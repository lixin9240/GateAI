// ============================================================
// 设备管理 — Pinia Store
// 按需求文档 4.6 节结构
// ============================================================
import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'
import { getEquipmentList, getEquipmentDetail } from '@/api/equipment'
import type { Equipment, EquipmentDetail } from '@/shared/types'

export const useEquipmentStore = defineStore('equipment', () => {
  // ── 设备列表 ──
  const deviceList = ref<Equipment[]>([])
  const listLoading = ref(false)
  const pagination = reactive({ page: 1, pageSize: 15, total: 0 })

  // ── 筛选条件 ──
  const filters = reactive({
    type: null as string | null,
    status: null as string | null,
    group: null as string | null,
    keyword: '',
  })

  // ── 当前设备详情 ──
  const currentDeviceId = ref<number | null>(null)
  const currentDevice = ref<EquipmentDetail | null>(null)
  const detailLoading = ref(false)

  // ── WebSocket 实时状态映射 ──
  const deviceStatusMap = ref<Record<number, 'online' | 'offline' | 'fault' | 'maintenance'>>({})

  // ── 选中行 ──
  const selectedIds = ref<number[]>([])

  // ── Actions ──
  async function fetchDeviceList() {
    listLoading.value = true
    try {
      const res = await getEquipmentList({
        page: pagination.page,
        page_size: pagination.pageSize,
        type: filters.type || undefined,
        status: filters.status || undefined,
        group: filters.group || undefined,
        keyword: filters.keyword || undefined,
      })
      const body = res.data
      if (body.code === 0 && body.data) {
        deviceList.value = body.data.list
        pagination.total = body.data.total
        return
      }
    } catch {
      /* API 不可用时由页面组件接管 mock */
    } finally {
      listLoading.value = false
    }
  }

  async function fetchDeviceDetail(id: number) {
    currentDeviceId.value = id
    detailLoading.value = true
    try {
      const res = await getEquipmentDetail(id)
      const body = res.data
      if (body.code === 0 && body.data) {
        currentDevice.value = body.data
        detailLoading.value = false
        return
      }
    } catch {
      /* fallback */
    }
    detailLoading.value = false
  }

  function setFilters(newFilters: Partial<typeof filters>) {
    Object.assign(filters, newFilters)
    pagination.page = 1
    fetchDeviceList()
  }

  function clearSelection() {
    currentDeviceId.value = null
    currentDevice.value = null
  }

  function updateDeviceStatus(id: number, status: 'online' | 'offline' | 'fault' | 'maintenance') {
    deviceStatusMap.value[id] = status
  }

  function reset() {
    deviceList.value = []
    pagination.page = 1
    pagination.total = 0
    filters.type = null
    filters.status = null
    filters.group = null
    filters.keyword = ''
    currentDeviceId.value = null
    currentDevice.value = null
    selectedIds.value = []
  }

  return {
    deviceList,
    listLoading,
    pagination,
    filters,
    currentDeviceId,
    currentDevice,
    detailLoading,
    deviceStatusMap,
    selectedIds,
    fetchDeviceList,
    fetchDeviceDetail,
    setFilters,
    clearSelection,
    updateDeviceStatus,
    reset,
  }
})
