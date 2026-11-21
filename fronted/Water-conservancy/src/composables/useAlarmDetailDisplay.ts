import { ref, watch } from 'vue'

const STORAGE_KEY = 'alarm_detail_display'

export interface AlarmDetailDisplayConfig {
  showConfirmedBy: boolean
  showHandledBy: boolean
}

const defaults: AlarmDetailDisplayConfig = {
  showConfirmedBy: true,
  showHandledBy: true,
}

function load(): AlarmDetailDisplayConfig {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return { ...defaults }
    const parsed = JSON.parse(raw) as Partial<AlarmDetailDisplayConfig>
    return {
      showConfirmedBy: parsed.showConfirmedBy ?? defaults.showConfirmedBy,
      showHandledBy: parsed.showHandledBy ?? defaults.showHandledBy,
    }
  } catch {
    return { ...defaults }
  }
}

const config = ref<AlarmDetailDisplayConfig>(load())

watch(
  config,
  (val) => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(val))
    } catch { /* ignore */ }
  },
  { deep: true },
)

export function useAlarmDetailDisplay() {
  function setShowConfirmedBy(v: boolean) {
    config.value.showConfirmedBy = v
  }
  function setShowHandledBy(v: boolean) {
    config.value.showHandledBy = v
  }
  return {
    config,
    setShowConfirmedBy,
    setShowHandledBy,
  }
}
