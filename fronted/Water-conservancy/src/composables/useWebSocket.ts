import { ref, onUnmounted, type Ref } from 'vue'

export type WsStatus = 'connecting' | 'open' | 'closing' | 'closed'

export interface WsOptions {
  heartbeatInterval?: number
  heartbeatMessage?: string
  reconnectBase?: number
  reconnectMax?: number
  reconnectMaxAttempts?: number
  onOpen?: (event: Event) => void
  onMessage?: (data: unknown) => void
  onClose?: (event: CloseEvent) => void
  onError?: (event: Event) => void
}

export function useWebSocket(url: string | Ref<string>, options: WsOptions = {}) {
  const {
    heartbeatInterval = 30000,
    heartbeatMessage = 'ping',
    reconnectBase = 1000,
    reconnectMax = 30000,
    reconnectMaxAttempts = Infinity,
    onOpen,
    onMessage,
    onClose,
    onError,
  } = options
  const status = ref<WsStatus>('closed')
  const lastMessage = ref<unknown>(null)
  const lastData = ref<unknown>(null)
  const reconnectCount = ref(0)
  let ws: WebSocket | null = null
  let hbTimer: ReturnType<typeof setInterval> | null = null
  let rcTimer: ReturnType<typeof setTimeout> | null = null
  let destroyed = false

  function startHb() {
    stopHb()
    hbTimer = setInterval(() => {
      if (ws?.readyState === WebSocket.OPEN) ws.send(heartbeatMessage)
    }, heartbeatInterval)
  }
  function stopHb() {
    if (hbTimer) {
      clearInterval(hbTimer)
      hbTimer = null
    }
  }
  function cancelRc() {
    if (rcTimer) {
      clearTimeout(rcTimer)
      rcTimer = null
    }
  }

  function scheduleRc() {
    if (destroyed || reconnectCount.value >= reconnectMaxAttempts) return
    const delay = Math.min(reconnectBase * 2 ** reconnectCount.value, reconnectMax)
    reconnectCount.value++
    rcTimer = setTimeout(() => {
      if (!destroyed) connect()
    }, delay)
  }

  function connect() {
    if (destroyed) return
    cancelRc()
    const u = typeof url === 'string' ? url : url.value
    if (!u) return
    try {
      status.value = 'connecting'
      ws = new WebSocket(u)
      ws.onopen = (e) => {
        status.value = 'open'
        reconnectCount.value = 0
        startHb()
        onOpen?.(e)
      }
      ws.onmessage = (e) => {
        let p: unknown = e.data
        try {
          p = JSON.parse(e.data)
        } catch {
          /* raw */
        }
        lastMessage.value = p
        lastData.value = p
        onMessage?.(p)
      }
      ws.onclose = (e) => {
        status.value = 'closed'
        stopHb()
        onClose?.(e)
        if (!e.wasClean) scheduleRc()
      }
      ws.onerror = (e) => onError?.(e)
    } catch {
      scheduleRc()
    }
  }

  function send(data: unknown) {
    if (ws?.readyState === WebSocket.OPEN) {
      ws.send(typeof data === 'string' ? data : JSON.stringify(data))
    }
  }
  function disconnect() {
    destroyed = true
    cancelRc()
    stopHb()
    status.value = 'closing'
    ws?.close(1000, 'client')
    ws = null
  }

  onUnmounted(() => disconnect())
  return { status, lastMessage, lastData, reconnectCount, connect, send, disconnect }
}
