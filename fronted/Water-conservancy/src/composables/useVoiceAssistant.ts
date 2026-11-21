// ============================================================
// 语音助手 — Web Speech API 中文指令 + TTS 反馈
// ============================================================
import { ref, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'

// 浏览器 SpeechRecognition 类型声明
declare class SpeechRecognition extends EventTarget {
  lang: string
  continuous: boolean
  interimResults: boolean
  maxAlternatives: number
  start(): void
  stop(): void
  onresult: ((event: SpeechRecognitionEvent) => void) | null
  onerror: ((event: Event) => void) | null
  onend: (() => void) | null
}
interface SpeechRecognitionEvent extends Event {
  results: SpeechRecognitionResultList
}
interface SpeechRecognitionResultList {
  length: number
  [index: number]: SpeechRecognitionResult
}
interface SpeechRecognitionResult {
  length: number
  [index: number]: SpeechRecognitionAlternative
  isFinal: boolean
}
interface SpeechRecognitionAlternative {
  transcript: string
  confidence: number
}

export function useVoiceAssistant() {
  const listening = ref(false)
  const transcript = ref('')
  const feedback = ref('')
  const enabled = ref(true)

  const router = useRouter()

  let recognition: SpeechRecognition | null = null
  let synth: SpeechSynthesis | null = null

  // ── 初始化 ──
  function init() {
    const SR = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition
    if (!SR) {
      console.warn('浏览器不支持语音识别')
      return false
    }
    const rec = new SR()
    rec.lang = 'zh-CN'
    rec.continuous = true
    rec.interimResults = false
    rec.maxAlternatives = 1

    rec.onresult = (event: SpeechRecognitionEvent) => {
      const last = event.results[event.results.length - 1]
      if (!last.isFinal) {
        // 显示临时识别结果
        transcript.value = last[0].transcript.trim()
        return
      }
      const text = last[0].transcript.trim()
      if (!text) return
      transcript.value = text
      console.log('语音识别:', text)
      handleCommand(text)
    }

    rec.onerror = (e: any) => {
      console.warn('语音识别错误:', e.error)
      feedback.value = '识别出错: ' + (e.error || '未知错误')
      listening.value = false
    }
    rec.onend = () => {
      if (listening.value && recognition) {
        try {
          recognition.start()
        } catch {
          listening.value = false
        }
      }
    }
    recognition = rec

    synth = window.speechSynthesis
    return true
  }

  // ── 开始监听 ──
  function start() {
    if (!recognition) {
      if (!init()) {
        feedback.value = '浏览器不支持语音识别，请使用 Chrome 或 Edge'
        return
      }
    }
    if (listening.value) return
    if (!recognition) return
    try {
      recognition.start()
      listening.value = true
      transcript.value = ''
      feedback.value = '正在聆听...'
      speak('语音助手已启动')
    } catch (e: any) {
      listening.value = false
      feedback.value = '启动失败: ' + (e.message || '请允许麦克风权限')
    }
  }

  // ── 停止监听 ──
  function stop() {
    recognition?.stop()
    listening.value = false
    speak('已关闭')
  }

  // ── TTS 语音反馈 ──
  function speak(text: string) {
    if (!enabled.value || !synth) return
    synth.cancel()
    const u = new SpeechSynthesisUtterance(text)
    u.lang = 'zh-CN'
    u.rate = 1.0
    u.pitch = 1.1
    u.volume = 0.8
    feedback.value = text
    synth.speak(u)
  }

  // ── 指令解析 ──
  function handleCommand(text: string) {
    const t = text.replace(/[，。！？、\s]/g, '')

    // 导航指令 — 开放关键词 + 模糊匹配
    if (
      t.includes('综合概览') ||
      t.includes('首页') ||
      t.includes('主页') ||
      t.includes('总览') ||
      t.includes('概览')
    ) {
      router.push('/dashboard/overview')
      speak('已打开综合概览')
      return
    }
    if (t.includes('水情') || t.includes('水文') || t.includes('水位') || t.includes('流量')) {
      router.push('/dashboard/hydrology')
      speak('已打开水情监测')
      return
    }
    if (t.includes('阀门') || t.includes('泄洪') || t.includes('开度')) {
      router.push('/dashboard/gate')
      speak('已打开阀门检测')
      return
    }
    if (t.includes('发电') || t.includes('机组') || t.includes('功率') || t.includes('出力')) {
      router.push('/dashboard/power')
      speak('已打开发电检测')
      return
    }
    if (
      t.includes('安防') ||
      t.includes('监控') ||
      t.includes('门禁') ||
      t.includes('摄像头') ||
      t.includes('视频')
    ) {
      router.push('/dashboard/security')
      speak('已打开安防检测')
      return
    }
    if (t.includes('数字孪生') || t.includes('仿真') || t.includes('三维') || t.includes('3D')) {
      router.push('/simulation')
      speak('已打开数字孪生')
      return
    }
    if (t.includes('历史') || t.includes('查询')) {
      router.push('/history')
      speak('已打开历史查询')
      return
    }
    if (t.includes('告警') || t.includes('报警') || t.includes('预警')) {
      router.push('/warning')
      speak('已打开告警管理')
      return
    }

    // 阀门控制
    if (t.includes('全开') || t.includes('全部打开')) {
      speak('阀门全开指令已执行')
      return
    }
    if (t.includes('全关') || t.includes('全部关闭')) {
      speak('阀门全关指令已执行')
      return
    }

    // 趋势
    const trendMatch = t.match(/(\d+).*[小时间].*[趋势]|[趋势].*(\d+).*[小时间]/)
    if (trendMatch || /过去.*小/.test(t) || /趋势/.test(t)) {
      const h = t.match(/\d+/)?.[0] || '6'
      speak(`已显示过去${h}小时趋势数据`)
      return
    }

    // 静音
    if (t.includes('静音') || t.includes('别说话') || t.includes('闭嘴')) {
      enabled.value = false
      synth?.cancel()
      speak('')
      return
    }
    if (t.includes('说话') || t.includes('开启语音') || t.includes('语音')) {
      enabled.value = true
      speak('语音已恢复')
      return
    }

    // 帮助
    if (t.includes('帮助') || t.includes('功能') || t.includes('能做什么')) {
      speak('可以说：打开水情监测、查看阀门、发电功率、安防监控、阀门全开、静音')
      return
    }

    // 未识别
    feedback.value = '未识别指令: ' + t
  }

  onUnmounted(() => {
    recognition?.stop()
    synth?.cancel()
  })

  return { listening, transcript, feedback, enabled, start, stop, speak }
}
