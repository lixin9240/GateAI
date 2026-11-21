/** 安防告警蜂鸣（默认 6 声，比原先 3 声更醒目） */
export function playAlarmSound(options?: { beeps?: number; volume?: number }) {
  const beeps = options?.beeps ?? 6
  const volume = options?.volume ?? 0.24

  try {
    const ctx = new AudioContext()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.type = 'square'
    osc.frequency.value = 880
    gain.gain.value = 0

    const interval = 0.45
    const duration = 0.3
    const now = ctx.currentTime
    for (let i = 0; i < beeps; i++) {
      const t = now + i * interval
      gain.gain.setValueAtTime(volume, t)
      gain.gain.setValueAtTime(0, t + duration)
    }
    osc.start(now)
    osc.stop(now + beeps * interval + 0.1)
  } catch {
    // 浏览器不支持或自动播放被拦截
  }
}
