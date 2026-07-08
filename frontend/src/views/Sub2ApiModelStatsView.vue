<script setup lang="ts">
import { ApiOutlined, BarChartOutlined, TeamOutlined, ThunderboltOutlined } from '@ant-design/icons-vue'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import { BarChart, HeatmapChart } from 'echarts/charts'
import {
  GraphicComponent,
  GridComponent,
  LegendComponent,
  TooltipComponent,
  VisualMapComponent,
} from 'echarts/components'
import { init, use, type ECharts } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { message } from 'ant-design-vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { getModelStats, type ModelRank, type ModelStatsRes, type UserModelRank } from '../api/sub2api'
import { useThemeStore } from '../stores/theme'

use([BarChart, HeatmapChart, GraphicComponent, GridComponent, LegendComponent, TooltipComponent, VisualMapComponent, CanvasRenderer])

const themeStore = useThemeStore()
const loading = ref(false)
const stats = ref<ModelStatsRes | null>(null)
const range = ref<[Dayjs, Dayjs]>([dayjs().subtract(7, 'day').startOf('day'), dayjs().endOf('day')])
const userFilter = ref('')

const barChartEl = ref<HTMLDivElement | null>(null)
const heatChartEl = ref<HTMLDivElement | null>(null)
let barChart: ECharts | null = null
let heatChart: ECharts | null = null

const topModels = computed<ModelRank[]>(() => (stats.value?.models || []).slice(0, 12))
const userModels = computed<UserModelRank[]>(() => stats.value?.user_models || [])
const topUserModels = computed<UserModelRank[]>(() => userModels.value.slice(0, 30))

const metricCards = computed(() => [
  {
    label: '总请求数',
    value: (stats.value?.summary.request_count || 0).toLocaleString('zh-CN'),
    icon: ApiOutlined,
    color: '#4f46e5',
    bg: 'linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%)',
  },
  {
    label: '活跃用户',
    value: (stats.value?.summary.user_count || 0).toLocaleString('zh-CN'),
    icon: TeamOutlined,
    color: '#0891b2',
    bg: 'linear-gradient(135deg,#0891b2 0%,#0e7490 100%)',
  },
  {
    label: '模型数量',
    value: (stats.value?.summary.model_count || 0).toString(),
    icon: BarChartOutlined,
    color: '#059669',
    bg: 'linear-gradient(135deg,#059669 0%,#047857 100%)',
  },
  {
    label: 'Total Cost',
    value: Number(stats.value?.summary.total_cost || 0).toFixed(4),
    icon: ThunderboltOutlined,
    color: '#d97706',
    bg: 'linear-gradient(135deg,#d97706 0%,#b45309 100%)',
  },
])

function modelTag(model: string) {
  if (/gpt|chatgpt|o[1-9]|openai/i.test(model)) return { color: '#10b981', label: 'GPT' }
  if (/claude|anthropic|droid/i.test(model)) return { color: '#8b5cf6', label: 'Claude' }
  if (/gemini|google/i.test(model)) return { color: '#3b82f6', label: 'Gemini' }
  return { color: '#6b7280', label: '其他' }
}

const isDark = computed(() => themeStore.themeName === 'dark')
const axisColor = computed(() => isDark.value ? '#666' : '#ccc')
const labelColor = computed(() => isDark.value ? '#bbb' : '#555')

function drawBarChart() {
  if (!barChartEl.value) return
  if (!barChart) barChart = init(barChartEl.value, isDark.value ? 'dark' : undefined)
  if (!topModels.value.length) {
    barChart.setOption(emptyChart('暂无模型消费数据'), true)
    return
  }

  const models = [...topModels.value].reverse()
  barChart.setOption({
    backgroundColor: 'transparent',
    tooltip: { trigger: 'axis', formatter: (p: any) => `${p[0].name}<br/>Total Cost: <b>${Number(p[0].value).toFixed(6)}</b>` },
    grid: { left: 16, right: 80, top: 12, bottom: 12, containLabel: true },
    xAxis: { type: 'value', axisLine: { lineStyle: { color: axisColor.value } }, axisLabel: { color: labelColor.value, formatter: (v: number) => v.toFixed(4) } },
    yAxis: {
      type: 'category',
      data: models.map(m => m.model),
      axisLabel: {
        color: labelColor.value,
        width: 160,
        overflow: 'truncate',
        formatter: (v: string) => v.length > 22 ? v.slice(0, 22) + '…' : v,
      },
      axisLine: { lineStyle: { color: axisColor.value } },
    },
    series: [{
      type: 'bar',
      data: models.map(m => Number(m.total_cost)),
      itemStyle: {
        borderRadius: [0, 6, 6, 0],
        color: (p: any) => {
          const colors = ['#4f46e5', '#7c3aed', '#0891b2', '#059669', '#d97706', '#dc2626']
          return colors[p.dataIndex % colors.length]
        },
      },
      label: {
        show: true,
        position: 'right',
        color: labelColor.value,
        formatter: (p: any) => Number(p.value).toFixed(4),
        fontSize: 12,
      },
    }],
  })
}

function drawHeatChart() {
  if (!heatChartEl.value) return
  if (!heatChart) heatChart = init(heatChartEl.value, isDark.value ? 'dark' : undefined)
  if (!topUserModels.value.length) {
    heatChart.setOption(emptyChart('暂无用户模型消费数据'), true)
    return
  }

  const users = uniq(topUserModels.value.map(row => userLabel(row))).slice(0, 8)
  const models = uniq(topUserModels.value.map(row => shortModel(row.model))).slice(0, 8)
  const data: number[][] = topUserModels.value
    .map(row => [models.indexOf(shortModel(row.model)), users.indexOf(userLabel(row)), Number(row.total_cost || 0)])
    .filter(row => row[0] >= 0 && row[1] >= 0)
  const max = Math.max(...data.map(row => Number(row[2])), 1)

  heatChart.setOption({
    backgroundColor: 'transparent',
    tooltip: {
      position: 'top',
      formatter: (p: any) => `${users[p.value[1]]}<br/>${models[p.value[0]]}<br/>消费：<b>${Number(p.value[2]).toFixed(6)}</b>`,
    },
    grid: { left: 96, right: 24, top: 20, bottom: 78 },
    xAxis: {
      type: 'category',
      data: models,
      axisLabel: { color: labelColor.value, rotate: 32, width: 110, overflow: 'truncate' },
      axisTick: { show: false },
      axisLine: { lineStyle: { color: axisColor.value } },
    },
    yAxis: {
      type: 'category',
      data: users,
      axisLabel: { color: labelColor.value, width: 88, overflow: 'truncate' },
      axisTick: { show: false },
      axisLine: { lineStyle: { color: axisColor.value } },
    },
    visualMap: {
      min: 0,
      max,
      show: false,
      inRange: { color: ['#e0f2fe', '#3b82f6', '#7c3aed'] },
    },
    series: [{ type: 'heatmap', data, label: { show: false }, emphasis: { itemStyle: { borderColor: '#111827', borderWidth: 1 } } }],
  })
}

async function loadStats() {
  loading.value = true
  try {
    const [from, to] = range.value
    stats.value = await getModelStats({
      from: from.format('YYYY-MM-DD HH:mm:ss'),
      to: to.format('YYYY-MM-DD HH:mm:ss'),
      limit: 30,
      user_id: userFilter.value,
      user_keyword: userFilter.value,
    })
    await nextTick()
    drawBarChart()
    drawHeatChart()
  } catch {
    message.error('读取模型消耗统计失败')
  } finally {
    loading.value = false
  }
}

function changeRange(val: [Dayjs, Dayjs] | null) {
  if (!val) return
  range.value = val
  loadStats()
}

watch(() => themeStore.themeName, async () => {
  barChart?.dispose()
  heatChart?.dispose()
  barChart = null
  heatChart = null
  await nextTick()
  drawBarChart()
  drawHeatChart()
})

onMounted(loadStats)
onBeforeUnmount(() => {
  barChart?.dispose()
  heatChart?.dispose()
})

function userLabel(row: UserModelRank) {
  return row.user_email || `用户 #${row.user_id}`
}

function userModelKey(row: UserModelRank) {
  return `${row.user_id}-${row.model}`
}

function shortModel(model: string) {
  return model.length > 24 ? model.slice(0, 24) + '...' : model
}

function uniq(rows: string[]) {
  return Array.from(new Set(rows))
}

function tokenText(val: number | string | undefined) {
  const num = Number(val || 0)
  if (num >= 100000000) return `${(num / 100000000).toFixed(2)}亿`
  if (num >= 10000) return `${(num / 10000).toFixed(2)}万`
  return num.toLocaleString('zh-CN')
}

function emptyChart(text: string) {
  return {
    graphic: {
      type: 'text',
      left: 'center',
      top: 'middle',
      style: { text, fill: labelColor.value, fontSize: 13 },
    },
    xAxis: { show: false },
    yAxis: { show: false },
    series: [],
  }
}
</script>

<template>
  <section class="page">
    <div class="pageHead">
      <div>
        <h1>模型消耗统计</h1>
        <p>usage_logs 聚合 · 实时展示各模型 API 调用量与费用分布</p>
      </div>
      <div class="headActions">
        <a-input
          v-model:value="userFilter"
          placeholder="用户ID或邮箱"
          allow-clear
          style="width:160px;"
          @press-enter="loadStats"
        />
        <a-range-picker
          :value="range"
          show-time
          class="range"
          format="YYYY-MM-DD HH:mm:ss"
          @change="changeRange"
        />
        <a-button type="primary" @click="loadStats">查询</a-button>
      </div>
    </div>

    <a-spin :spinning="loading">
      <!-- 指标卡 -->
      <div class="modelMetricGrid">
        <div v-for="card in metricCards" :key="card.label" class="modelMetricCard">
          <div class="modelMetricIcon" :style="{ background: card.bg }">
            <component :is="card.icon" />
          </div>
          <div class="modelMetricInfo">
            <span>{{ card.label }}</span>
            <strong>{{ card.value }}</strong>
          </div>
        </div>
      </div>

      <!-- 图表区 -->
      <div v-if="topModels.length > 0" class="modelChartGrid">
        <div class="modelChartCard">
          <div class="modelChartTitle">模型消费排行（Top 12）</div>
          <div ref="barChartEl" class="modelBarChart" />
        </div>
        <div class="modelChartCard">
          <div class="modelChartTitle">用户-模型消费热力图（Top 30）</div>
          <div ref="heatChartEl" class="modelPieChart" />
        </div>
      </div>

      <div class="modelTableCard">
        <div class="modelTableTitle">用户模型消费明细</div>
        <a-table
          :row-key="userModelKey"
          :data-source="userModels"
          :locale="{ emptyText: '暂无用户模型消费数据' }"
          :pagination="{ pageSize: 12 }"
          :scroll="{ x: 980 }"
          size="small"
        >
          <a-table-column key="user" title="用户" :width="220">
            <template #default="{ record }">
              <div class="modelNameCell">
                <span>{{ userLabel(record) }}</span>
              </div>
            </template>
          </a-table-column>
          <a-table-column key="model" title="模型" data-index="model" />
          <a-table-column key="request_count" title="请求数" data-index="request_count" :width="110" align="right">
            <template #default="{ record }">
              {{ record.request_count.toLocaleString('zh-CN') }}
            </template>
          </a-table-column>
          <a-table-column key="token_total" title="Token" data-index="token_total" :width="130" align="right">
            <template #default="{ record }">
              {{ tokenText(record.token_total) }}
            </template>
          </a-table-column>
          <a-table-column key="total_cost" title="Total Cost" data-index="total_cost" :width="150" align="right">
            <template #default="{ record }">
              <span class="money">{{ Number(record.total_cost).toFixed(6) }}</span>
            </template>
          </a-table-column>
          <a-table-column key="actual_cost" title="Actual Cost" data-index="actual_cost" :width="150" align="right">
            <template #default="{ record }">
              <span class="money">{{ Number(record.actual_cost).toFixed(6) }}</span>
            </template>
          </a-table-column>
        </a-table>
      </div>

      <!-- 明细表格 -->
      <div class="modelTableCard">
        <div class="modelTableTitle">全量模型明细</div>
        <a-table
          row-key="model"
          :data-source="stats?.models || []"
          :locale="{ emptyText: '暂无模型消耗数据' }"
          :pagination="{ pageSize: 15 }"
          :scroll="{ x: 800 }"
          size="small"
        >
          <a-table-column key="model" title="模型" data-index="model">
            <template #default="{ record }">
              <div class="modelNameCell">
                <a-tag :color="modelTag(record.model).color" style="margin-right:6px;">{{ modelTag(record.model).label }}</a-tag>
                <span>{{ record.model }}</span>
              </div>
            </template>
          </a-table-column>
          <a-table-column key="request_count" title="请求数" data-index="request_count" :width="110" align="right">
            <template #default="{ record }">
              {{ record.request_count.toLocaleString('zh-CN') }}
            </template>
          </a-table-column>
          <a-table-column key="user_count" title="用户数" data-index="user_count" :width="100" align="right" />
          <a-table-column key="total_cost" title="Total Cost" data-index="total_cost" :width="150" align="right">
            <template #default="{ record }">
              <span class="money">{{ Number(record.total_cost).toFixed(6) }}</span>
            </template>
          </a-table-column>
          <a-table-column key="actual_cost" title="Actual Cost" data-index="actual_cost" :width="150" align="right">
            <template #default="{ record }">
              <span class="money">{{ Number(record.actual_cost).toFixed(6) }}</span>
            </template>
          </a-table-column>
        </a-table>
      </div>

      <!-- 充值来源摘要 -->
      <div v-if="stats?.sources" class="modelSourceRow">
        <span class="modelSourceLabel">充值来源：</span>
        <a-tag color="blue">支付订单 {{ stats.sources.payment_orders_completed }} 笔</a-tag>
        <a-tag v-for="s in stats.sources.redeem_codes_used" :key="s.type" color="purple">
          {{ s.type }} 兑换码 {{ s.count }} 个
        </a-tag>
      </div>
    </a-spin>
  </section>
</template>
