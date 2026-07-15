<script setup lang="ts">
import { LineChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import { init, use, type ECharts } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import type { RebateTrendPoint } from '../../types'
import { formatCents, money, sumMoney, toCents } from '../../utils/money'
import AppCard from '../core/AppCard.vue'

use([LineChart, GridComponent, TooltipComponent, CanvasRenderer])

const props = withDefaults(defineProps<{
  items: RebateTrendPoint[]
  height?: number
  summary?: boolean
}>(), {
  height: 240,
  summary: false,
})

const chartEl = ref<HTMLDivElement | null>(null)
const total = computed(() => sumMoney(props.items, item => item.amount))
const average = computed(() => {
  if (!props.items.length) return 0n
  const days = BigInt(props.items.length)
  return (total.value + days / 2n) / days
})
const peak = computed(() => props.items.reduce((max, item) => {
  const amount = toCents(item.amount)
  return amount > max ? amount : max
}, 0n))
let chart: ECharts | null = null
let observer: ResizeObserver | null = null

function dayLabel(date: string) {
  return `${Number(date.slice(8, 10))}日`
}

function pointLabel(amount: string) {
  const value = money(amount).slice(1)
  return value.endsWith('.00') ? value.slice(0, -3) : value
}

function draw() {
  if (!chartEl.value) return
  chart ||= init(chartEl.value)
  const hasValue = props.items.some(item => toCents(item.amount) > 0n)

  chart.setOption({
    animationDuration: 350,
    grid: { left: 12, right: 14, top: 32, bottom: 8, containLabel: true },
    tooltip: {
      trigger: 'axis',
      backgroundColor: '#0f172a',
      borderWidth: 0,
      padding: [8, 10],
      textStyle: { color: '#fff', fontSize: 12 },
      valueFormatter: (value: unknown) => money(String(value)),
    },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: props.items.map(item => item.date),
      axisLine: { lineStyle: { color: '#e2e8f0' } },
      axisTick: { show: false },
      axisLabel: { color: '#64748b', fontSize: 12, margin: 14, formatter: dayLabel },
    },
    yAxis: {
      type: 'value',
      min: 0,
      max: hasValue ? undefined : 4,
      splitNumber: 4,
      axisLabel: { color: '#64748b', fontSize: 11 },
      splitLine: { lineStyle: { color: '#e8edf3', type: 'dashed' } },
    },
    series: [{
      type: 'line',
      data: props.items.map(item => item.amount),
      symbol: 'circle',
      symbolSize: 8,
      showSymbol: true,
      label: {
        show: true,
        position: 'top',
        distance: 7,
        color: '#0f172a',
        fontSize: 11,
        formatter: ({ dataIndex }: { dataIndex: number }) => pointLabel(props.items[dataIndex]?.amount || '0.00'),
      },
      lineStyle: { color: '#4648d4', width: 3 },
      itemStyle: { color: '#4648d4', borderColor: '#fff', borderWidth: 2 },
      areaStyle: { color: 'rgba(70, 72, 212, 0.08)' },
      emphasis: { scale: 1.25 },
    }],
  }, true)
}

watch(() => props.items, draw, { deep: true })

onMounted(() => {
  draw()
  observer = new ResizeObserver(() => chart?.resize())
  observer.observe(chartEl.value!)
})

onBeforeUnmount(() => {
  observer?.disconnect()
  chart?.dispose()
})
</script>

<template>
  <AppCard>
    <div class="trendHeader">
      <div>
        <h2>返利趋势（近 7 日）</h2>
        <p v-if="summary">
          合计 {{ formatCents(total) }} · 日均 {{ formatCents(average) }} · 峰值 {{ formatCents(peak) }}
        </p>
      </div>
      <span v-if="summary">单位：CNY</span>
    </div>
    <div ref="chartEl" class="trendCanvas" :style="{ height: `${height}px` }" />
  </AppCard>
</template>

<style scoped>
.trendHeader {
  display: flex;
  min-height: 32px;
  margin-bottom: 8px;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.trendHeader h2 {
  margin: 0;
  color: var(--rebate-text);
  font-size: 18px;
  line-height: 26px;
  letter-spacing: 0;
}

.trendHeader p,
.trendHeader span {
  color: var(--rebate-muted);
  font-size: 12px;
  line-height: 20px;
}

.trendHeader p { margin: 2px 0 0; }
.trendHeader span { white-space: nowrap; }
.trendCanvas { width: 100%; min-width: 0; }

@media (max-width: 640px) {
  .trendHeader { flex-direction: column; }
  .trendCanvas { height: 220px !important; }
}
</style>
