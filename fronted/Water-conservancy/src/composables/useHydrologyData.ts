import { ref, readonly, onMounted, onUnmounted } from 'vue'
import { useWebSocket } from './useWebSocket'

export interface HydrologySnapshot {
  upstreamLevel: number
  downstreamLevel: number
  waterHead: number
  inflowRate: number
  outflowRate: number
  gateOpening: number
  powerOutput: number
  capacity: number
  timestamp: string
}

const BASE = {
  upstreamLevel: 378.5,
  downstreamLevel: 269.2,
  inflowRate: 6350,
  outflowRate: 5820,
  gateOpening: 34,
  powerOutput: 7200,
  capacity: 48.36,
}

function rw(curr: number, base: number, range: number, drift = 0.1): number {
  return +(curr + (Math.random() - 0.5) * range + (base - curr) * drift).toFixed(2)
}

function gen(prev: HydrologySnapshot | null): HydrologySnapshot {
  const now = new Date().toISOString()
  if (!prev)
    return {
      upstreamLevel: BASE.upstreamLevel,
      downstreamLevel: BASE.downstreamLevel,
      waterHead: +(BASE.upstreamLevel - BASE.downstreamLevel).toFixed(2),
      inflowRate: BASE.inflowRate,
      outflowRate: BASE.outflowRate,
      gateOpening: BASE.gateOpening,
      powerOutput: BASE.powerOutput,
      capacity: BASE.capacity,
      timestamp: now,
    }
  const up = rw(prev.upstreamLevel, BASE.upstreamLevel, 0.15)
  const dn = rw(prev.downstreamLevel, BASE.downstreamLevel, 0.06)
  const inflow = rw(prev.inflowRate, BASE.inflowRate, 120)
  const outflow = rw(prev.outflowRate, BASE.outflowRate, 100)
  const gate = rw(prev.gateOpening, BASE.gateOpening, 1.5, 0.15)
  const head = up - dn
  const power = +(outflow * head * 0.0092 + (Math.random() - 0.5) * 60).toFixed(1)
  return {
    upstreamLevel: up,
    downstreamLevel: dn,
    waterHead: +head.toFixed(2),
    inflowRate: Math.round(inflow),
    outflowRate: Math.round(outflow),
    gateOpening: +gate.toFixed(1),
    powerOutput: power,
    capacity: +(BASE.capacity + (up - BASE.upstreamLevel) * 0.8).toFixed(2),
    timestamp: now,
  }
}

const MAX = 5000
const RETAIN_MS = 24 * 60 * 60 * 1000

function seedTrend(): HydrologySnapshot[] {
  const result: HydrologySnapshot[] = []
  let prev: HydrologySnapshot | null = null
  const now = Date.now()
  const stepMs = 60_000
  const totalMinutes = 24 * 60

  for (let i = totalMinutes; i >= 0; i--) {
    prev = gen(prev)
    result.push({ ...prev, timestamp: new Date(now - i * stepMs).toISOString() })
  }
  return result
}

export function useHydrologyData(wsUrl?: string, iv = 2000) {
  const snapshot = ref<HydrologySnapshot>(gen(null))
  const trend = ref<HydrologySnapshot[]>([])
  const connected = ref(false)
  const useMock = ref(true)
  let timer: ReturnType<typeof setInterval> | null = null

  function push(s: HydrologySnapshot) {
    snapshot.value = s
    trend.value.push(s)
    const cutoff = Date.now() - RETAIN_MS
    while (trend.value.length > 0 && new Date(trend.value[0]!.timestamp).getTime() < cutoff) {
      trend.value.shift()
    }
    if (trend.value.length > MAX) trend.value.shift()
  }

  function startMock() {
    if (trend.value.length === 0) {
      const seeded = seedTrend()
      trend.value = seeded
      snapshot.value = seeded[seeded.length - 1]!
    }
    timer = setInterval(() => push(gen(snapshot.value)), iv)
    connected.value = true
    useMock.value = true
  }
  function stopMock() {
    if (timer) {
      clearInterval(timer)
      timer = null
    }
  }

  const { status, connect, disconnect } = useWebSocket(wsUrl ?? '', {
    onOpen: () => {
      connected.value = true
      useMock.value = false
      stopMock()
    },
    onMessage: (data: unknown) => {
      if (!data || typeof data !== 'object') return
      const d = data as Record<string, unknown>
      let p = d.data ?? d
      if (d.type === 'monitoring_realtime' && d.data) p = d.data as Record<string, unknown>
      const q = p as Record<string, unknown>
      push({
        upstreamLevel: +(q.upstream_level ?? snapshot.value.upstreamLevel),
        downstreamLevel: +(q.downstream_level ?? snapshot.value.downstreamLevel),
        waterHead:
          q.water_head != null
            ? +(q.water_head as number)
            : q.upstream_level != null && q.downstream_level != null
              ? +((q.upstream_level as number) - (q.downstream_level as number)).toFixed(2)
              : snapshot.value.waterHead,
        inflowRate: +(q.inflow_rate ?? snapshot.value.inflowRate),
        outflowRate: +(q.outflow_rate ?? snapshot.value.outflowRate),
        gateOpening: +(q.gate_opening ?? snapshot.value.gateOpening),
        powerOutput: +(q.power_output ?? snapshot.value.powerOutput),
        capacity: +(q.capacity ?? snapshot.value.capacity),
        timestamp: (q.timestamp as string) ?? new Date().toISOString(),
      })
    },
    onClose: () => {
      connected.value = false
      if (!useMock.value) startMock()
    },
  })

  onMounted(() => {
    if (wsUrl) {
      connect()
      setTimeout(() => {
        if (status.value !== 'open') startMock()
      }, 3000)
    } else startMock()
  })
  onUnmounted(() => {
    stopMock()
    disconnect()
  })

  return {
    snapshot: readonly(snapshot),
    trend: readonly(trend),
    connected: readonly(connected),
    useMock: readonly(useMock),
  }
}
