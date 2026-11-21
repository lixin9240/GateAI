<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  visible: boolean
  tableData: any[]
  allData: any[]
}>()

const emit = defineEmits<{
  close: []
}>()

const reportData = computed(() => {
  const data = props.tableData.length > 0 ? props.tableData : props.allData.slice(-100)
  if (!data.length) return null
  const calc = (key: string) => ({
    max: Math.max(...data.map((d: any) => d[key])).toFixed(2),
    min: Math.min(...data.map((d: any) => d[key])).toFixed(2),
    avg: (data.reduce((s: number, d: any) => s + d[key], 0) / data.length).toFixed(2),
  })
  return {
    total: data.length,
    anomalies: data.filter((d: any) => d.event).length,
    upstreamLevel: calc('upstreamLevel'),
    inflowRate: calc('inflowRate'),
    powerOutput: calc('powerOutput'),
    gateOpening: calc('gateOpening'),
  }
})
</script>

<template>
  <Transition name="fade">
    <div v-if="visible" class="srm" @click.self="emit('close')">
      <div class="srm__box">
        <h2>智能分析报告</h2>
        <div v-if="reportData" class="srm__list">
          <div class="srm__item">
            <span>数据点数</span><b>{{ reportData.total }}</b>
          </div>
          <div class="srm__item">
            <span>异常点数</span><b style="color: #ef4444">{{ reportData.anomalies }}</b>
          </div>
          <div class="srm__item">
            <span>上游水位</span>
            <b>最大 {{ reportData.upstreamLevel.max }}m / 最小 {{ reportData.upstreamLevel.min }}m / 平均 {{ reportData.upstreamLevel.avg }}m</b>
          </div>
          <div class="srm__item">
            <span>入库流量</span>
            <b>最大 {{ reportData.inflowRate.max }}m³/s / 最小 {{ reportData.inflowRate.min }}m³/s / 平均 {{ reportData.inflowRate.avg }}m³/s</b>
          </div>
          <div class="srm__item">
            <span>发电功率</span>
            <b>最大 {{ reportData.powerOutput.max }}MW / 最小 {{ reportData.powerOutput.min }}MW / 平均 {{ reportData.powerOutput.avg }}MW</b>
          </div>
          <div class="srm__item">
            <span>阀门开度</span>
            <b>最大 {{ reportData.gateOpening.max }}% / 最小 {{ reportData.gateOpening.min }}% / 平均 {{ reportData.gateOpening.avg }}%</b>
          </div>
        </div>
        <div v-else class="srm__empty">暂无数据</div>
        <button class="srm__close-btn" @click="emit('close')">关闭</button>
      </div>
    </div>
  </Transition>
</template>

<style scoped lang="scss">
.srm {
  position: fixed;
  inset: 0;
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.4);
}

.srm__box {
  width: 560px;
  max-height: 80vh;
  padding: 28px;
  overflow-y: auto;
  background: #fff;
  border-radius: 12px;

  h2 {
    margin: 0 0 20px;
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
  }
}

.srm__empty {
  padding: 24px;
  text-align: center;
  color: #94a3b8;
  font-size: 15px;
}

.srm__list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 16px;
}

.srm__item {
  display: flex;
  gap: 12px;
  padding: 12px 14px;
  font-size: 15px;
  line-height: 1.45;
  background: #f8fafc;
  border-radius: 6px;

  span {
    min-width: 88px;
    font-weight: 500;
    color: #64748b;
  }

  b {
    font-weight: 600;
    color: #1e293b;
  }
}

.srm__close-btn {
  display: block;
  width: 100%;
  padding: 10px 0;
  font-size: 15px;
  font-weight: 600;
  color: #475569;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  cursor: pointer;

  &:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
  }
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
