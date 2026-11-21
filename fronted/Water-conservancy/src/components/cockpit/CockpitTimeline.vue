<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'

const props = withDefaults(
  defineProps<{
    playing?: boolean
    speed?: number
  }>(),
  {
    playing: true,
    speed: 1,
  },
)

const emit = defineEmits<{
  play: []
  pause: []
  speedChange: [speed: number]
  reset: []
}>()

const progress = ref(35)
let timer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  timer = setInterval(() => {
    if (props.playing) progress.value = (progress.value + 0.5 * props.speed) % 100
  }, 500)
})
onUnmounted(() => {
  if (timer) clearInterval(timer)
})

const speeds = [0.5, 1, 2, 5]
</script>

<template>
  <footer class="cockpit-timeline">
    <div class="cockpit-timeline__controls">
      <button class="tl-btn" title="重置" @click="emit('reset')">⟲</button>
      <button
        class="tl-btn tl-btn--primary"
        :title="playing ? '暂停' : '播放'"
        @click="playing ? emit('pause') : emit('play')"
      >
        {{ playing ? '⏸' : '▶' }}
      </button>
      <div class="cockpit-timeline__speeds">
        <button
          v-for="s in speeds"
          :key="s"
          class="tl-speed"
          :class="{ 'is-active': speed === s }"
          @click="emit('speedChange', s)"
        >
          {{ s }}x
        </button>
      </div>
    </div>
    <div class="cockpit-timeline__track">
      <div class="cockpit-timeline__fill" :style="{ width: progress + '%' }" />
      <div class="cockpit-timeline__marker" :style="{ left: progress + '%' }" />
      <div class="cockpit-timeline__labels">
        <span>00:00</span><span>06:00</span><span>12:00</span><span>18:00</span><span>24:00</span>
      </div>
    </div>
    <div class="cockpit-timeline__scene-btns">
      <button class="tl-scene">旋转</button>
      <button class="tl-scene">缩放</button>
      <button class="tl-scene">复位</button>
    </div>
  </footer>
</template>

<style scoped lang="scss">
@use '@/assets/styles/cockpit.scss' as *;

.cockpit-timeline {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 10px 20px;
  border-top: 1px solid rgba(0, 212, 255, 0.12);
  background: rgba(4, 11, 20, 0.92);
  backdrop-filter: blur(12px);

  &__controls {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
  }

  &__speeds {
    display: flex;
    gap: 4px;
    margin-left: 8px;
  }

  &__track {
    flex: 1;
    position: relative;
    height: 28px;
    background: rgba(255, 255, 255, 0.04);
    border-radius: 4px;
    border: 1px solid rgba(0, 212, 255, 0.15);
    overflow: visible;
  }

  &__fill {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    background: linear-gradient(90deg, rgba(0, 212, 255, 0.2), rgba(0, 212, 255, 0.05));
    border-radius: 4px;
    transition: width 0.3s;
  }

  &__marker {
    position: absolute;
    top: -4px;
    width: 3px;
    height: 36px;
    background: $cockpit-cyan;
    box-shadow: 0 0 12px $cockpit-cyan;
    transform: translateX(-50%);
    transition: left 0.3s;
  }

  &__labels {
    position: absolute;
    bottom: -18px;
    left: 0;
    right: 0;
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: $cockpit-text-dim;
    font-family: 'SF Mono', monospace;
  }

  &__scene-btns {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
  }
}

.tl-btn {
  width: 32px;
  height: 32px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.04);
  color: $cockpit-text-dim;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;

  &:hover {
    border-color: $cockpit-cyan;
    color: $cockpit-cyan;
  }
  &--primary {
    background: rgba(0, 212, 255, 0.15);
    border-color: rgba(0, 212, 255, 0.4);
    color: $cockpit-cyan;
  }
}

.tl-speed {
  padding: 4px 8px;
  font-size: 10px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 4px;
  background: transparent;
  color: $cockpit-text-dim;
  cursor: pointer;

  &.is-active {
    background: rgba(0, 212, 255, 0.15);
    border-color: $cockpit-cyan;
    color: $cockpit-cyan;
  }
}

.tl-scene {
  padding: 6px 12px;
  font-size: 11px;
  border: 1px solid rgba(0, 212, 255, 0.2);
  border-radius: 6px;
  background: rgba(0, 212, 255, 0.06);
  color: $cockpit-cyan;
  cursor: pointer;
  transform: perspective(400px) rotateX(3deg);
  transition: all 0.2s;

  &:hover {
    box-shadow: 0 4px 16px rgba(0, 212, 255, 0.2);
    transform: perspective(400px) rotateX(0deg);
  }
}
</style>
