// ============================================================
// 数字孪生 — WebSocket / Reverb 实时推送
// ============================================================
import { ref, onUnmounted } from 'vue'
import type { SimulationProgressPayload, SimulationRealtimeData } from '@/types/simulation'
import { useWebSocket } from '@/composables/useWebSocket'

type ProgressHandler = (payload: SimulationProgressPayload) => void

let echoInstance: { leave: (ch: string) => void; disconnect: () => void } | null = null
let rawWs: ReturnType<typeof useWebSocket> | null = null

/** 将 ws_endpoint 转为可连接的 WebSocket URL（开发环境走 Vite 代理） */
export function buildSimulationWsUrl(wsEndpoint: string, token: string): string {
  let raw = wsEndpoint.trim()
  if (!raw) return ''

  if (raw.startsWith('http://')) raw = `ws://${raw.slice(7)}`
  else if (raw.startsWith('https://')) raw = `wss://${raw.slice(8)}`
  else if (!raw.startsWith('ws')) {
    const proto = location.protocol === 'https:' ? 'wss' : 'ws'
    raw = `${proto}://${location.host}${raw.startsWith('/') ? raw : `/${raw}`}`
  }

  const httpBase = raw.replace(/^wss:/, 'https:').replace(/^ws:/, 'http:')
  const url = new URL(httpBase)
  if (!url.searchParams.has('token') && token) url.searchParams.set('token', token)
  if (!url.searchParams.has('simulationId') && !url.searchParams.has('simulation_id')) {
    /* 由调用方在 ws_endpoint 中携带 */
  }

  if (import.meta.env.DEV) {
    const proto = location.protocol === 'https:' ? 'wss' : 'ws'
    return `${proto}://${location.host}${url.pathname}${url.search}`
  }
  const proto = url.protocol === 'https:' ? 'wss:' : 'ws:'
  return `${proto}//${url.host}${url.pathname}${url.search}`
}

function parseProgressMessage(raw: unknown): SimulationProgressPayload | null {
  if (!raw || typeof raw !== 'object') return null
  const obj = raw as Record<string, unknown>

  // Laravel Reverb / Pusher 包裹：{ event, data: "json string" }
  if (typeof obj.event === 'string' && obj.data != null) {
    const ev = obj.event as string
    if (ev === 'progress' || ev.endsWith('progress')) {
      try {
        const inner =
          typeof obj.data === 'string' ? JSON.parse(obj.data) : (obj.data as SimulationProgressPayload)
        return inner
      } catch {
        return null
      }
    }
  }

  if (obj.metrics || obj.progress != null || obj.simulation_id || obj.simulationId) {
    return obj as SimulationProgressPayload
  }
  return null
}

export function mapProgressToRealtime(
  current: SimulationRealtimeData,
  payload: SimulationProgressPayload,
  durationSec: number,
): SimulationRealtimeData {
  const finite = (v: unknown, fallback: number) => {
    const n = typeof v === 'number' ? v : Number(v)
    return Number.isFinite(n) ? n : fallback
  }

  const metrics = payload.metrics ?? {}
  const progress = finite(payload.progress, 0)
  const elapsedSec = Math.max(0, Math.round((progress / 100) * durationSec))
  const backendStatus = (payload.status ?? '').toLowerCase()

  let status = current.status
  if (backendStatus === 'completed' || backendStatus === 'finished' || progress >= 100) {
    status = 'finished'
  } else if (backendStatus === 'failed') {
    status = 'idle'
  } else if (backendStatus === 'paused') {
    status = 'paused'
  } else if (backendStatus === 'running' || progress > 0) {
    status = 'running'
  }

  const level = finite(metrics.upstream_level, current.currentLevel)
  const flow = finite(metrics.inflow_rate, current.currentFlow)
  const opening = finite(metrics.gate_opening, current.currentOpening)
  const downstream = finite(metrics.downstream_level, current.currentDownstreamLevel)

  const historyLevels = [...current.historyLevels, { time: elapsedSec, value: level }]
  const historyFlows = [...current.historyFlows, { time: elapsedSec, value: flow }]
  if (historyLevels.length > 180) historyLevels.shift()
  if (historyFlows.length > 180) historyFlows.shift()

  return {
    status,
    elapsedSec,
    currentLevel: level,
    currentDownstreamLevel: downstream,
    currentFlow: flow,
    currentOpening: Math.round(opening),
    historyLevels,
    historyFlows,
  }
}

async function connectReverb(
  simulationId: string,
  token: string,
  onProgress: ProgressHandler,
): Promise<boolean> {
  const appKey = import.meta.env.VITE_REVERB_APP_KEY
  if (!appKey) return false

  try {
    const { default: Echo } = await import('laravel-echo')
    const { default: Pusher } = await import('pusher-js')
    ;(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher

    const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http'
    const host = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname
    const port = Number(import.meta.env.VITE_REVERB_PORT ?? 8080)
    const apiBase = import.meta.env.VITE_API_BASE_URL ?? '/api'

    const echo = new Echo({
      broadcaster: 'reverb',
      key: appKey,
      wsHost: host,
      wsPort: port,
      wssPort: port,
      forceTLS: scheme === 'https',
      disableStats: true,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: `${apiBase}/broadcasting/auth`,
      auth: { headers: { Authorization: `Bearer ${token}` } },
    })

    echoInstance = echo as unknown as typeof echoInstance
    const channelName = `simulation.${simulationId}`
    echo
      .private(channelName)
      .listen('.progress', (payload: SimulationProgressPayload) => onProgress(payload))

    return true
  } catch {
    return false
  }
}

export function useSimulationStream() {
  const connected = ref(false)
  const mode = ref<'none' | 'reverb' | 'websocket'>('none')

  function disconnect() {
    rawWs?.disconnect()
    rawWs = null
    if (echoInstance) {
      try {
        echoInstance.disconnect()
      } catch {
        /* */
      }
      echoInstance = null
    }
    connected.value = false
    mode.value = 'none'
  }

  async function connect(options: {
    simulationId: string
    wsEndpoint?: string
    token: string
    onProgress: ProgressHandler
    onError?: (err: unknown) => void
  }) {
    disconnect()
    const { simulationId, wsEndpoint, token, onProgress, onError } = options

    const reverbOk = await connectReverb(simulationId, token, onProgress)
    if (reverbOk) {
      connected.value = true
      mode.value = 'reverb'
      return
    }

    const wsUrl =
      wsEndpoint && wsEndpoint.length > 0
        ? buildSimulationWsUrl(wsEndpoint, token)
        : buildSimulationWsUrl(
            `/api/v1/simulation/stream-data?simulationId=${encodeURIComponent(simulationId)}`,
            token,
          )

    if (!wsUrl) {
      onError?.(new Error('无 WebSocket 地址'))
      return
    }

    rawWs = useWebSocket(wsUrl, {
      reconnectMaxAttempts: 5,
      onOpen: () => {
        connected.value = true
        mode.value = 'websocket'
      },
      onMessage: (data) => {
        const payload = parseProgressMessage(data)
        if (payload) onProgress(payload)
      },
      onError: (e) => onError?.(e),
      onClose: () => {
        connected.value = false
      },
    })
    rawWs.connect()
  }

  onUnmounted(() => disconnect())

  return { connected, mode, connect, disconnect }
}
