// ============================================================
// 告警管理 — WebSocket 实时通知 Composable
// 依据：《水电站阀门智能调度系统-详细需求报告》§2.7
// ============================================================
import { ref, h } from 'vue'
import { ElNotification } from 'element-plus'
import router from '@/app/router'
import type { AlarmPushMessage, AlarmRecord } from '@/types/alarm'
import { ALARM_LEVEL_MAP, ALARM_TYPE_MAP } from '@/constants/alarm'
import { playAlarmSound } from '@/utils/alarmSound'

/** 未处理告警数量（全局共享，供角标使用） */
export const pendingAlarmCount = ref(0)

/** 新告警信号：WarningPage 监听后刷新列表 */
export const alarmRefreshTick = ref(0)

/** 是否有声音提示（本地设置） */
const soundEnabled = ref(localStorage.getItem('alarmSound') !== 'off')

// ---------- 弹窗通知 ----------
function showAlarmNotification(alarm: AlarmRecord) {
  const levelInfo = ALARM_LEVEL_MAP[alarm.level]
  const typeLabel = ALARM_TYPE_MAP[alarm.type]?.label ?? alarm.type
  ElNotification({
    title: `【${levelInfo?.label ?? alarm.level}】${typeLabel}`,
    message: h('div', { class: 'alarm-notify-body' }, [
      h('p', { class: 'alarm-notify-text' }, alarm.content),
      h(
        'button',
        {
          class: 'alarm-notify-action',
          type: 'button',
          onClick: (e: MouseEvent) => {
            e.stopPropagation()
            router.push('/warning')
          },
        },
        '立即去处理 →',
      ),
    ]),
    type: alarm.level === 'URGENT' ? 'error' : alarm.level === 'IMPORTANT' ? 'warning' : 'info',
    duration: alarm.level === 'URGENT' ? 0 : 6000,
    position: 'top-right',
    customClass: 'alarm-notify alarm-notify--center',
  })
}

// ---------- 处理推送消息（全局 / 页面共用） ----------
export function handlePushMessage(msg: AlarmPushMessage) {
  if (msg.type === 'alarm_new') {
    pendingAlarmCount.value = msg.pendingCount
    alarmRefreshTick.value++
    showAlarmNotification(msg.data)
    if (soundEnabled.value) playAlarmSound()
  }
}

// ---------- 切换声音 ----------
export function useAlarmNotify() {
  function toggleSound() {
    soundEnabled.value = !soundEnabled.value
    localStorage.setItem('alarmSound', soundEnabled.value ? 'on' : 'off')
  }

  return {
    pendingAlarmCount,
    soundEnabled,
    toggleSound,
    handlePushMessage,
  }
}
