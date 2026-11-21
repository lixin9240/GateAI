<script setup lang="ts">
// ============================================================
// 安防检测 — 单大屏视频监控 + TAB 键循环切换机位 + 截图/全屏 + 告警联动
// ============================================================
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { ElNotification } from 'element-plus'
import { playAlarmSound } from '@/utils/alarmSound'
import { pendingAlarmCount } from '@/composables/useAlarmNotify'
import { fetchSecurityCameras, fetchSecurityDoors, fetchSecurityPatrols, fetchSecurityAlarmList } from '@/api/monitoring'

// ── USB 摄像头 ──
const usbStream = ref<MediaStream | null>(null)
const usbConnected = ref(false)
const videoRef = ref<HTMLVideoElement | null>(null)

async function loadData() {
  const [cam, doorList, pat, secAlarms] = await Promise.all([
    fetchSecurityCameras(), fetchSecurityDoors(), fetchSecurityPatrols(), fetchSecurityAlarmList(),
  ])
  cameras.value = cam.map((c, i) => {
    const zone = c.alarmZone ?? c.area ?? ''
    ALARM_ZONE_TO_CAMERA[zone] = `c${i + 1}`
    return { id: `c${i + 1}`, name: c.name, area: c.area ?? '', status: c.status as CameraDef['status'], motion: c.motion ?? '—', alarmZone: zone }
  })
  doors.value = doorList.map((d) => ({
    id: `d${d.id}`, name: d.name, status: d.status as DoorDef['status'], last: d.last_access && d.last_user ? `${d.last_access} ${d.last_user}` : '—',
  }))
  patrols.value = pat.map((p) => ({
    time: p.start_time?.slice(-5) ?? '--:--', route: p.route, person: p.patrol_user,
    result: p.status === 'completed' ? (p.abnormal > 0 ? '异常' : '正常') : p.status === 'in_progress' ? '待执行' : '—',
    dur: p.end_time ? `${Math.round((new Date(p.end_time).getTime() - new Date(p.start_time).getTime()) / 60000)}分钟` : '—',
  }))
  alarms.value = secAlarms.map((a) => ({
    time: a.trigger_time?.slice(-5) ?? '--:--', loc: a.location, type: TYPE_CN[a.type] ?? a.type,
    level: a.level as AlarmDef['level'], status: STATUS_CN[a.status] ?? a.status,
  }))
}

onMounted(async () => {
  loadData()
  containerRef.value?.focus()
  try {
    const devices = await navigator.mediaDevices.enumerateDevices()
    const hasVideo = devices.some((d) => d.kind === 'videoinput')
    if (hasVideo) await startUSBCamera()
  } catch {
    /* 静默失败 */
  }
})

async function startUSBCamera() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 } })
    usbStream.value = stream
    usbConnected.value = true
    if (videoRef.value) {
      videoRef.value.srcObject = stream
      videoRef.value.play().catch(() => {})
    }
  } catch {
    usbConnected.value = false
  }
}

onUnmounted(() => {
  usbStream.value?.getTracks().forEach((t) => t.stop())
})

// ── 摄像头数据 ──
interface CameraDef {
  id: string
  name: string
  area: string
  status: 'online' | 'offline'
  motion: string
  alarmZone: string
}

const cameras = ref<CameraDef[]>([])
const ALARM_ZONE_TO_CAMERA: Record<string, string> = {
  坝顶: 'c1', 厂房: 'c3', 中控室: 'c4', 下游: 'c5', 下游河道: 'c5', 开关站: 'c6',
}

// ── 门禁 / 巡检 / 告警 ──
interface DoorDef {
  id: string
  name: string
  status: 'locked' | 'unlocked'
  last: string
}
interface PatrolDef {
  time: string
  route: string
  person: string
  result: string
  dur: string
}
interface AlarmDef {
  time: string
  loc: string
  type: string
  level: 'warning' | 'critical'
  status: string
}

const doors = ref<DoorDef[]>([])
const patrols = ref<PatrolDef[]>([])
const alarms = ref<AlarmDef[]>([])

// ── 当前显示机位 ──
const activeIdx = ref(0)
const activeCamera = computed(() => cameras.value[activeIdx.value] ?? cameras.value[0] ?? { id: '', name: '', area: '', status: 'offline' as const, motion: '', alarmZone: '' })

// 切换回 c1 时重新挂载视频流（v-show 不销毁元素，需手动恢复）
watch(activeIdx, (idx) => {
  if (cameras[idx]?.id === 'c1' && usbConnected.value && usbStream.value && videoRef.value) {
    if (videoRef.value.srcObject !== usbStream.value) {
      videoRef.value.srcObject = usbStream.value
      videoRef.value.play().catch(() => {})
    }
  }
})

// ── 点击切换机位 ──
function switchTo(idx: number) {
  activeIdx.value = idx
}
function onTabKey(e: KeyboardEvent) {
  // 如果焦点在输入框或交互控件上，放行正常的 Tab 行为
  const el = document.activeElement
  if (el) {
    const tag = el.tagName
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return
    if (tag === 'BUTTON' && !el.classList.contains('cam-tab')) return
    if (tag === 'A') return
  }

  e.preventDefault()
  if (alarmFlashTimer) { clearInterval(alarmFlashTimer); alarmFlashTimer = null }
  alarmSwitchedCamera.value = null
  alarmFlashActive.value = false
  if (e.shiftKey) {
    activeIdx.value = activeIdx.value <= 0 ? cameras.value.length - 1 : activeIdx.value - 1
  } else {
    activeIdx.value = activeIdx.value >= cameras.value.length - 1 ? 0 : activeIdx.value + 1
  }
}

// ── 容器 ref — 自动聚焦确保 Tab 键生效 ──
const containerRef = ref<HTMLDivElement | null>(null)

// ── 全屏 ──
const mainViewRef = ref<HTMLDivElement | null>(null)
const isFullscreen = ref(false)

function onFullscreenChange() {
  isFullscreen.value = !!document.fullscreenElement
}

onMounted(() => document.addEventListener('fullscreenchange', onFullscreenChange))
onUnmounted(() => document.removeEventListener('fullscreenchange', onFullscreenChange))

async function toggleFullscreen() {
  if (!mainViewRef.value) return
  try {
    if (!document.fullscreenElement) {
      await mainViewRef.value.requestFullscreen()
    } else {
      await document.exitFullscreen()
    }
  } catch {
    /* 不支持或拒绝 */
  }
}

// ── 截图 ──
function captureScreenshot() {
  if (!usbConnected.value || !videoRef.value) return
  const video = videoRef.value
  const canvas = document.createElement('canvas')
  canvas.width = video.videoWidth || 640
  canvas.height = video.videoHeight || 480
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  ctx.drawImage(video, 0, 0, canvas.width, canvas.height)

  ctx.fillStyle = 'rgba(0,0,0,0.55)'
  ctx.fillRect(0, 0, canvas.width, 28)
  ctx.fillStyle = '#fff'
  ctx.font = '13px sans-serif'
  ctx.fillText(`${activeCamera.value.name}  ${new Date().toLocaleString('zh-CN')}`, 10, 19)

  canvas.toBlob((blob) => {
    if (!blob) return
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `screenshot-${activeCamera.value.id}-${Date.now()}.png`
    a.click()
    URL.revokeObjectURL(url)
  }, 'image/png')
}

// ── 告警联动 ──
const alarmSwitchedCamera = ref<string | null>(null)
const alarmFlashActive = ref(false)
let alarmFlashTimer: ReturnType<typeof setInterval> | null = null
let alarmScenarioIdx = 0

const SIMULATED_SCENARIOS = [
  { location: '下游河道', type: '周界入侵', level: 'warning' as const },
  { location: '开关站', type: '门禁异常', level: 'critical' as const },
  { location: '中控室', type: '设备离线', level: 'warning' as const },
  { location: '坝顶', type: '人员聚集', level: 'critical' as const },
  { location: '厂房', type: '火警', level: 'critical' as const },
  { location: '下游', type: '非法闯入', level: 'warning' as const },
]

function simulateAlarm() {
  playAlarmSound()
  const scenario = SIMULATED_SCENARIOS[alarmScenarioIdx % SIMULATED_SCENARIOS.length]
  alarmScenarioIdx++

  const now = new Date()
  const time = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`

  alarms.value.unshift({
    time,
    loc: scenario.location,
    type: scenario.type,
    level: scenario.level,
    status: '待处理',
  })

  pendingAlarmCount.value++

  ElNotification({
    title: `【${scenario.level === 'critical' ? '严重' : '警告'}】${scenario.type}`,
    message: `${scenario.location} 触发告警，已自动切换摄像头`,
    type: scenario.level === 'critical' ? 'error' : 'warning',
    duration: scenario.level === 'critical' ? 0 : 8000,
    position: 'top-right',
    customClass: 'alarm-notify alarm-notify--center',
  })

  const cameraId = ALARM_ZONE_TO_CAMERA[scenario.location]
  if (cameraId) {
    triggerAlarmSwitch(cameraId)
  }
}

function triggerAlarmSwitch(cameraId: string) {
  const idx = cameras.value.findIndex((c) => c.id === cameraId)
  if (idx === -1) return

  activeIdx.value = idx
  alarmSwitchedCamera.value = cameraId

  let flashes = 0
  alarmFlashActive.value = true
  if (alarmFlashTimer) clearInterval(alarmFlashTimer)
  alarmFlashTimer = setInterval(() => {
    alarmFlashActive.value = !alarmFlashActive.value
    flashes++
    if (flashes >= 12) {
      clearInterval(alarmFlashTimer!)
      alarmFlashTimer = null
      alarmFlashActive.value = false
      alarmSwitchedCamera.value = null
    }
  }, 250)
}

// ── 状态映射 ──
const sc: Record<string, { color: string; label: string }> = {
  online: { color: '#22c55e', label: '在线' },
  offline: { color: '#94a3b8', label: '离线' },
  locked: { color: '#22c55e', label: '已锁' },
  unlocked: { color: '#f59e0b', label: '未锁' },
}
const alc: Record<string, string> = { warning: '#f59e0b', critical: '#ef4444' }
const LEVEL_CN: Record<string, string> = { warning: '警告', critical: '严重', urgent: '紧急', normal: '普通' }
const STATUS_CN: Record<string, string> = { unhandled: '待处理', handled: '已处理', acknowledged: '已确认', pending: '待确认', processed: '已处理', confirmed: '已确认' }
const TYPE_CN: Record<string, string> = { unauthorized_entry: '非法闯入', camera_offline: '摄像头离线', door_force: '门禁异常', intrusion: '周界入侵', fire: '火警', crowd: '人员聚集', equipment_offline: '设备离线' }
</script>

<template>
  <div ref="containerRef" class="sp" tabindex="0" @keydown.tab="onTabKey">
    <!-- KPI 行 -->
    <div class="kpis">
      <div class="kpi">
        <span class="kpi__d" style="background:#3b82f6" />
        <span class="kpi__l">摄像头</span>
        <span class="kpi__v">{{ cameras.filter(c => c.status === 'online').length }}<small> / {{ cameras.length }}</small></span>
      </div>
      <div class="kpi">
        <span class="kpi__d" style="background:#22c55e" />
        <span class="kpi__l">门禁正常</span>
        <span class="kpi__v">{{ doors.filter(d => d.status === 'locked').length }}<small> / {{ doors.length }}</small></span>
      </div>
      <div class="kpi">
        <span class="kpi__d" style="background:#22c55e" />
        <span class="kpi__l">巡检完成</span>
        <span class="kpi__v">{{ patrols.filter(p => p.result === '正常').length }}<small> / {{ patrols.length }}</small></span>
      </div>
      <div class="kpi">
        <span class="kpi__d" style="background:#d1d5db" />
        <span class="kpi__l">今日告警</span>
        <span class="kpi__v">{{ alarms.length }}<small> 条</small></span>
      </div>
    </div>

    <!-- 主体 -->
    <div class="main">
      <!-- 左：一个大屏视频监控 -->
      <div class="cam-section">
        <!-- 机位 Tab 栏 — 视频上方 -->
        <div class="cam-tabs">
          <button
            v-for="(c, i) in cameras"
            :key="c.id"
            class="cam-tab"
            :class="{ active: activeIdx === i }"
            tabindex="-1"
            @click="switchTo(i)"
          >
            <span class="cam-tab__dot" :style="{ background: sc[c.status].color }" />
            {{ c.name }}
          </button>
          <span class="cam-tabs__usb">
            <span :class="{ on: usbConnected }">{{ usbConnected ? '● 已连接' : '○ 无信号' }}</span>
            <button class="btn" @click="startUSBCamera()">重新连接</button>
          </span>
        </div>

        <!-- 主画面 — 大屏监控 -->
        <div
          ref="mainViewRef"
          class="cam-main"
          :class="{
            'cam-main--fs': isFullscreen,
            'cam-main--alarm': alarmSwitchedCamera && alarmFlashActive,
          }"
        >
          <!-- USB 实时视频 — v-show 保持 video 元素不销毁 -->
          <video
            v-show="activeCamera.id === 'c1' && usbConnected"
            ref="videoRef"
            autoplay
            muted
            playsinline
            class="cam-main__vid"
          />

          <!-- 占位画面 -->
          <div v-show="!(activeCamera.id === 'c1' && usbConnected)" class="cam-main__placeholder">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
              <polygon points="23 7 16 12 23 17 23 7" />
              <rect x="1" y="5" width="15" height="14" rx="2" />
            </svg>
            <span v-if="activeCamera.status === 'offline'" class="cam-main__offline">无信号</span>
          </div>

          <!-- 叠加信息 -->
          <div class="cam-main__ol">
            <span>{{ activeCamera.name }}</span>
            <span :class="activeCamera.status">{{ sc[activeCamera.status].label }}</span>
          </div>

          <!-- 工具栏 -->
          <div class="cam-toolbar">
            <button class="cam-toolbar__btn" title="截图" :disabled="!(activeCamera.id === 'c1' && usbConnected)" @click="captureScreenshot">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                <circle cx="12" cy="13" r="4" />
              </svg>
            </button>
            <button class="cam-toolbar__btn" :title="isFullscreen ? '退出全屏' : '全屏'" @click="toggleFullscreen">
              <svg v-if="!isFullscreen" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 3 21 3 21 9" /><polyline points="9 21 3 21 3 15" />
                <line x1="21" y1="3" x2="14" y2="10" /><line x1="3" y1="21" x2="10" y2="14" />
              </svg>
              <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="4 8 4 3 9 3" /><polyline points="20 16 20 21 15 21" />
                <line x1="4" y1="3" x2="10" y2="9" /><line x1="20" y1="21" x2="14" y2="15" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- 右面板 — 保持不变 -->
      <div class="right">
        <!-- 门禁 -->
        <div class="pn">
          <div class="pn__t">门禁状态</div>
          <div v-for="d in doors" :key="d.id" class="dr">
            <span class="dr__d" :style="{ background: sc[d.status].color }" />
            <span class="dr__n">{{ d.name }}</span>
            <span class="dr__s" :style="{ color: sc[d.status].color }">{{ sc[d.status].label }}</span>
            <span class="dr__l">{{ d.last }}</span>
          </div>
        </div>

        <!-- 巡检 -->
        <div class="pn">
          <div class="pn__t">巡检记录</div>
          <div v-for="p in patrols" :key="p.time" class="pr" :class="{ pen: p.result === '待执行' }">
            <span class="pr__t">{{ p.time }}</span>
            <span class="pr__r">{{ p.route }}</span>
            <span>{{ p.person }}</span>
            <span :style="{ color: p.result === '正常' ? '#22c55e' : p.result === '待执行' ? '#f59e0b' : '#6b7280' }">{{ p.result }}</span>
          </div>
        </div>

        <!-- 告警 + 模拟按钮 -->
        <div class="pn">
          <div class="pn__t">
            最近告警
            <button class="alarm-sim-btn" @click="simulateAlarm">模拟告警</button>
          </div>
          <div v-for="a in alarms" :key="a.time" class="al">
            <span class="al__d" :style="{ background: alc[a.level] }" />
            <div>
              <div class="al__h"><b>{{ a.loc }}</b> · {{ a.type }}</div>
              <div class="al__f">{{ a.time }} · {{ STATUS_CN[a.status] ?? a.status }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
// ── 布局 ──
.sp {
  height: calc(100vh - 56px);
  display: flex;
  flex-direction: column;
  background: #f5f6f8;
  overflow: hidden;
}

// ── KPI ──
.kpis {
  display: flex; gap: 0;
  padding: 0 28px;
  background: #fff;
  border-bottom: 1px solid #eef0f2;
  flex-shrink: 0;
}
.kpi {
  flex: 1; display: flex; align-items: center; gap: 8px; padding: 14px 0;
}
.kpi__d { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.kpi__l { font-size: 14px; color: #8b9198; }
.kpi__v {
  margin-left: auto; font-size: 20px; font-weight: 700; color: #1e293b; font-family: 'SF Mono', monospace;
  small { margin-left: 4px; font-size: 12px; font-weight: 400; color: #94a3b8; }
}

// ── 主体 ──
.main {
  flex: 1; display: flex; gap: 16px; padding: 16px 28px; min-height: 0; overflow: hidden;
}

// ── 左：摄像头区域 ──
.cam-section {
  flex: 1; display: flex; flex-direction: column; min-width: 0; min-height: 0;
}

// ── 机位 Tab 栏 ──
.cam-tabs {
  display: flex; align-items: center; gap: 2px; flex-shrink: 0; margin-bottom: 8px;
}
.cam-tab {
  display: flex; align-items: center; gap: 5px;
  padding: 7px 15px; font-size: 14px; font-weight: 500; color: #64748b;
  background: #fff; border: 1px solid #e5e7eb; border-radius: 8px 8px 0 0;
  cursor: pointer; transition: all 0.15s;
  &:hover { color: #374151; background: #f9fafb; }
  &.active {
    color: #1e293b; background: #fff; border-color: #3b82f6;
    border-bottom-color: #fff; position: relative; z-index: 1;
  }
}
.cam-tab__dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.cam-tabs__usb {
  margin-left: auto; display: flex; align-items: center; gap: 8px;
  font-size: 12px; font-weight: 400; color: #94a3b8;
  span.on { color: #22c55e; font-weight: 600; }
}
.btn {
  padding: 4px 12px; font-size: 12px; color: #64748b;
  background: #fff; border: 1px solid #d1d5db; border-radius: 5px; cursor: pointer;
  &:hover { background: #f3f4f6; }
}

// ── 主画面 ──
.cam-main {
  position: relative; flex: 1; display: flex; align-items: center; justify-content: center;
  min-height: 0; background: #0f172a; border-radius: 10px; overflow: hidden;
  &--fs { position: fixed; inset: 0; z-index: 9999; border-radius: 0; background: #000; }
  &--alarm { box-shadow: inset 0 0 60px rgba(239,68,68,0.35); outline: 2px solid #ef4444; outline-offset: -2px; }
}
.cam-main__vid { width: 100%; height: 100%; object-fit: contain; }
.cam-main__placeholder {
  display: flex; flex-direction: column; align-items: center; gap: 12px; color: rgba(255,255,255,0.08);
}
.cam-main__offline { font-size: 16px; color: rgba(255,255,255,0.3); font-weight: 500; }
.cam-main__ol {
  position: absolute; top: 0; left: 0; right: 0; display: flex;
  justify-content: space-between; padding: 8px 12px; pointer-events: none;
  span:first-child { padding: 2px 8px; font-size: 12px; color: rgba(255,255,255,0.55); background: rgba(0,0,0,0.55); border-radius: 4px; }
  span:last-child {
    padding: 2px 8px; font-size: 12px; font-weight: 600; background: rgba(0,0,0,0.55); border-radius: 4px;
    &.online { color: #22c55e; }
    &.offline { color: #94a3b8; }
  }
}

// ── 工具栏 ──
.cam-toolbar { position: absolute; top: 8px; right: 8px; z-index: 10; display: flex; gap: 4px; }
.cam-toolbar__btn {
  width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;
  color: rgba(255,255,255,0.7); background: rgba(0,0,0,0.45); border: none; border-radius: 6px;
  cursor: pointer; transition: all 0.15s;
  &:hover { background: rgba(0,0,0,0.7); color: #fff; }
  &:disabled { opacity: 0.3; cursor: default; }
}

// ── 右面板 ──
.right { width: 380px; flex-shrink: 0; display: flex; flex-direction: column; gap: 14px; overflow-y: auto; }
.pn { padding: 16px 18px; background: #fff; border: 1px solid #eef0f2; border-radius: 8px; }
.pn__t { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; font-size: 15px; font-weight: 600; color: #1e293b; }
.alarm-sim-btn {
  padding: 4px 14px; font-size: 12px; font-weight: 500; color: #fff; background: #ef4444;
  border: none; border-radius: 5px; cursor: pointer; transition: background 0.15s;
  &:hover { background: #dc2626; }
  &:active { background: #b91c1c; }
}

.dr { display: flex; align-items: center; gap: 10px; padding: 10px 0; font-size: 14px; line-height: 1.45;
  & + & { border-top: 1px solid #f1f5f9; }
}
.dr__d { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.dr__n { flex: 1; font-weight: 500; color: #1e293b; }
.dr__s { font-size: 13px; font-weight: 600; }
.dr__l { font-size: 13px; color: #64748b; }

.pr { display: flex; align-items: center; gap: 10px; padding: 9px 0; font-size: 14px; line-height: 1.45; color: #475569;
  & + & { border-top: 1px solid #f1f5f9; }
  &.pen { opacity: 0.55; }
}
.pr__t { min-width: 48px; flex-shrink: 0; font-size: 13px; font-family: 'SF Mono', monospace; color: #64748b; }
.pr__r { flex: 1; min-width: 0; overflow: hidden; font-weight: 500; color: #1e293b; text-overflow: ellipsis; white-space: nowrap; }

.al { display: flex; gap: 10px; padding: 10px 0; line-height: 1.45;
  & + & { border-top: 1px solid #f1f5f9; }
}
.al__d { width: 8px; height: 8px; margin-top: 6px; border-radius: 50%; flex-shrink: 0; }
.al__h { font-size: 14px; color: #1e293b; b { font-weight: 600; } }
.al__f { margin-top: 4px; font-size: 13px; color: #64748b; }
</style>

<style lang="scss">
.main-layout__content:has(.sp) {
  padding: 0 !important;
  overflow: hidden !important;
}
</style>
