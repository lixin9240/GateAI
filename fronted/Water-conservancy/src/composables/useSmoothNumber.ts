import { ref, watch, onUnmounted, type Ref } from 'vue'

/** 数值平滑过渡，用于水位等实时遥测展示 */
export function useSmoothNumber(source: Ref<number>, durationMs = 1400) {
  const display = ref(source.value)
  let rafId = 0
  let animStart = 0
  let from = source.value
  let to = source.value

  function easeOutCubic(t: number) {
    return 1 - (1 - t) ** 3
  }

  function tick(now: number) {
    const t = Math.min(1, (now - animStart) / durationMs)
    display.value = from + (to - from) * easeOutCubic(t)
    if (t < 1) rafId = requestAnimationFrame(tick)
  }

  function animate(next: number) {
    if (!Number.isFinite(next)) return
    cancelAnimationFrame(rafId)
    from = Number.isFinite(display.value) ? display.value : next
    to = next
    animStart = performance.now()
    rafId = requestAnimationFrame(tick)
  }

  watch(source, (v) => {
    if (Number.isFinite(v)) animate(v)
  }, { immediate: true })

  onUnmounted(() => cancelAnimationFrame(rafId))

  return display
}
