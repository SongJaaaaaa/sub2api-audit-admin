<script setup lang="ts">
import { ApiOutlined, BarChartOutlined, TeamOutlined, ThunderboltOutlined } from '@ant-design/icons-vue'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import { BarChart } from 'echarts/charts'
import {
  GraphicComponent,
  GridComponent,
  LegendComponent,
  TooltipComponent,
} from 'echarts/components'
import { init, use, type ECharts } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { message } from 'ant-design-vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { getModelStats, type ModelRank, type ModelStatsRes, type UserModelRank } from '../api/sub2api'
import { useThemeStore } from '../stores/theme'

use([BarChart, GraphicComponent, GridComponent, LegendComponent, TooltipComponent, CanvasRenderer])

const themeStore = useThemeStore()
const loading = ref(false)
const stats = ref<ModelStatsRes | null>(null)
const range = ref<[Dayjs, Dayjs]>([dayjs().subtract(7, 'day').startOf('day'), dayjs().endOf('day')])
const userFilter = ref('')

const barChartEl = ref<HTMLDivElement | null>(null)
const userModelChartEl = ref<HTMLDivElement | null>(null)
let barChart: ECharts | null = null
let userModelChart: ECharts | null = null

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

function drawUserModelChart() {
  if (!userModelChartEl.value) return
  if (!userModelChart) userModelChart = init(userModelChartEl.value, isDark.value ? 'dark' : undefined)
  if (!topUserModels.value.length) {
    userModelChart.setOption(emptyChart('暂无用户模型消费数据'), true)
    return
  }

  const rows = [...topUserModels.value].reverse()

  userModelChart.setOption({
    backgroundColor: 'transparent',
    tooltip: {
      trigger: 'axis',
      formatter: (p: any) => {
        const row = rows[p[0].dataIndex]

        return `${userLabel(row)}<br/>${row.model}<br/>消费：<b>${Number(row.total_cost || 0).toFixed(6)}</b><br/>请求：${row.request_count.toLocaleString('zh-CN')} 次<br/>Token：${tokenText(row.token_total)}`
      },
    },
    grid: { left: 150, right: 78, top: 12, bottom: 12, containLabel: true },
    xAxis: {
      type: 'value',
      axisLabel: { color: labelColor.value, formatter: (v: number) => v.toFixed(4) },
      axisLine: { lineStyle: { color: axisColor.value } },
      splitLine: { lineStyle: { color: axisColor.value } },
    },
    yAxis: {
      type: 'category',
      data: rows.map(row => `${shortUser(userLabel(row))}\n${shortModel(row.model)}`),
      axisLabel: { color: labelColor.value, width: 140, overflow: 'truncate', lineHeight: 15 },
      axisTick: { show: false },
      axisLine: { lineStyle: { color: axisColor.value } },
    },
    series: [{
      type: 'bar',
      data: rows.map(row => Number(row.total_cost || 0)),
      barWidth: 10,
      itemStyle: { borderRadius: [0, 6, 6, 0], color: '#2563eb' },
      label: {
        show: true,
        position: 'right',
        color: labelColor.value,
        formatter: (p: any) => Number(p.value).toFixed(4),
        fontSize: 11,
      },
    }],
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
    drawUserModelChart()
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
  userModelChart?.dispose()
  barChart = null
  userModelChart = null
  await nextTick()
  drawBarChart()
  drawUserModelChart()
})

onMounted(loadStats)
onBeforeUnmount(() => {
  barChart?.dispose()
  userModelChart?.dispose()
})

function userLabel(row: UserModelRank) {
  return row.user_email || `用户 #${row.user_id}`
}

function userModelKey(row: UserModelRank) {
  return `${row.user_id}-${row.model}`
}

function shortModel(model: string) {
  return model.length > 26 ? model.slice(0, 26) + '...' : model
}

function shortUser(user: string) {
  return user.length > 22 ? user.slice(0, 22) + '...' : user
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
          <div class="modelChartTitle">用户-模型消费排行（Top 30）</div>
          <div ref="userModelChartEl" class="modelUserChart" />
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
