<script setup lang="ts">
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

defineProps<{
  doors: DoorDef[]
  patrols: PatrolDef[]
  alarms: AlarmDef[]
}>()

defineEmits<{ simulateAlarm: [] }>()

const sc: Record<string, { color: string; label: string }> = {
  locked: { color: '#22c55e', label: '已锁' },
  unlocked: { color: '#f59e0b', label: '未锁' },
}
const alc: Record<string, string> = { warning: '#f59e0b', critical: '#ef4444' }
</script>

<template>
  <div class="srp">
    <!-- 门禁 -->
    <div class="srp__pn">
      <div class="srp__pn-title">门禁状态</div>
      <div v-for="d in doors" :key="d.id" class="srp__door">
        <span class="srp__door-dot" :style="{ background: sc[d.status].color }" />
        <span class="srp__door-name">{{ d.name }}</span>
        <span class="srp__door-status" :style="{ color: sc[d.status].color }">{{ sc[d.status].label }}</span>
        <span class="srp__door-last">{{ d.last }}</span>
      </div>
    </div>

    <!-- 巡检 -->
    <div class="srp__pn">
      <div class="srp__pn-title">巡检记录</div>
      <div v-for="p in patrols" :key="p.time" class="srp__patrol" :class="{ pen: p.result === '待执行' }">
        <span class="srp__patrol-time">{{ p.time }}</span>
        <span class="srp__patrol-route">{{ p.route }}</span>
        <span>{{ p.person }}</span>
        <span :style="{ color: p.result === '正常' ? '#22c55e' : p.result === '待执行' ? '#f59e0b' : '#6b7280' }">{{ p.result }}</span>
      </div>
    </div>

    <!-- 告警 -->
    <div class="srp__pn">
      <div class="srp__pn-title">
        最近告警
        <button class="srp__alarm-btn" @click="$emit('simulateAlarm')">模拟告警</button>
      </div>
      <div v-for="a in alarms" :key="a.time" class="srp__alarm">
        <span class="srp__alarm-dot" :style="{ background: alc[a.level] }" />
        <div>
          <div class="srp__alarm-head"><b>{{ a.loc }}</b> · {{ a.type }}</div>
          <div class="srp__alarm-foot">{{ a.time }} · {{ a.status }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
.srp {
  width: 380px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
  overflow-y: auto;

  &__pn {
    padding: 16px 18px;
    background: #fff;
    border: 1px solid #eef0f2;
    border-radius: 8px;
  }

  &__pn-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
  }

  // 门禁
  &__door {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    font-size: 14px;
    line-height: 1.45;
    & + & { border-top: 1px solid #f1f5f9; }
  }
  &__door-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  &__door-name { flex: 1; font-weight: 500; color: #1e293b; }
  &__door-status { font-size: 13px; font-weight: 600; }
  &__door-last { font-size: 13px; color: #64748b; }

  // 巡检
  &__patrol {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 0; font-size: 14px; line-height: 1.45; color: #475569;
    & + & { border-top: 1px solid #f1f5f9; }
    &.pen { opacity: 0.55; }
  }
  &__patrol-time { min-width: 48px; flex-shrink: 0; font-size: 13px; font-family: 'SF Mono', monospace; color: #64748b; }
  &__patrol-route { flex: 1; min-width: 0; overflow: hidden; font-weight: 500; color: #1e293b; text-overflow: ellipsis; white-space: nowrap; }

  // 告警
  &__alarm-btn {
    padding: 4px 14px; font-size: 12px; font-weight: 500; color: #fff;
    background: #ef4444; border: none; border-radius: 5px; cursor: pointer;
    &:hover { background: #dc2626; }
  }
  &__alarm {
    display: flex; gap: 10px; padding: 10px 0; line-height: 1.45;
    & + & { border-top: 1px solid #f1f5f9; }
  }
  &__alarm-dot { width: 8px; height: 8px; margin-top: 6px; border-radius: 50%; flex-shrink: 0; }
  &__alarm-head { font-size: 14px; color: #1e293b; b { font-weight: 600; } }
  &__alarm-foot { margin-top: 4px; font-size: 13px; color: #64748b; }
}
</style>
