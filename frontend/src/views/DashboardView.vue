<script setup lang="ts">
import {
  BarChartOutlined,
  CheckCircleOutlined,
  ClockCircleOutlined,
  DatabaseOutlined,
  FireOutlined,
  ReloadOutlined,
  RiseOutlined,
  WarningOutlined,
} from '@ant-design/icons-vue'
import { message } from 'ant-design-vue'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import { BarChart, LineChart, PieChart } from 'echarts/charts'
import { GridComponent, GraphicComponent, LegendComponent, TooltipComponent } from 'echarts/components'
import { init, use, type ECharts } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { getDashboardStats, type DashboardStatsRes, type UserRank } from '../api/dashboard'
import { getLedgerAdjustments, type LedgerAdjustment } from '../api/ledger'
import {
  getModelStats,
  getSub2Users,
  type ModelRank,
  type UsageSummary,
} from '../api/sub2api'
import { useThemeStore } from '../stores/theme'

type RangeKey = 'today' | 'week' | 'month' | 'seven' | 'thirty' | 'custom'
type ModelGroup = 'all' | 'gpt' | 'claude'

interface CompareItem {
  name: string
  summary: UsageSummary
}

use([BarChart, LineChart, PieChart, GridComponent, GraphicComponent, LegendComponent, TooltipComponent, CanvasRenderer])

const fmt = 'YYYY-MM-DD HH:mm:ss'
const themeStore = useThemeStore()
const loading = ref(false)
const rangeKey = ref<RangeKey>('seven')
const modelGroup = ref<ModelGroup>('all')
const customRange = ref<[Dayjs, Dayjs]>([dayjs().subtract(7, 'day').startOf('day'), dayjs().endOf('day')])
const userTotal = ref(0)
const stats = ref<DashboardStatsRes | null>(null)
const compareStats = ref<CompareItem[]>([])
const success = ref<LedgerAdjustment[]>([])
const abnormal = ref<LedgerAdjustment[]>([])
const rechargeRank = ref<UserRank[]>([])
const quotaRank = ref<UserRank[]>([])
const modelChartEl = ref<HTMLDivElement | null>(null)
const requestChartEl = ref<HTMLDivElement | null>(null)
const compareChartEl = ref<HTMLDivElement | null>(null)

let modelChart: ECharts | null = null
let requestChart: ECharts | null = null
let compareChart: ECharts | null = null

const rangeOptions = [
  { label: '今天', value: 'today' },
  { label: '本周', value: 'week' },
  { label: '本月', value: 'month' },
  { label: '近 7 天', value: 'seven' },
  { label: '近 30 天', value: 'thirty' },
  { label: '自定义', value: 'custom' },
]

const modelOptions = [
  { label: '全部模型', value: 'all' },
  { label: 'GPT', value: 'gpt' },
  { label: 'Claude', value: 'claude' },
]

const modelColumns = [
  { title: '模型', dataIndex: 'model' },
  { title: '请求', dataIndex: 'request_count', align: 'right', width: 92 },
  { title: '用户', dataIndex: 'user_count', align: 'right', width: 82 },
  { title: 'Total Cost', dataIndex: 'total_cost', align: 'right', width: 130 },
] as const

const userRankColumns = [
  { title: '用户', dataIndex: 'sub2api_user_email' },
  { title: '用户ID', dataIndex: 'sub2api_user_id', width: 92 },
  { title: '金额', dataIndex: 'total_amount', align: 'right', width: 120 },
  { title: '笔数', dataIndex: 'entry_count', align: 'right', width: 80 },
] as const

const filteredModels = computed(() => {
  const rows = stats.value?.models || []
  if (modelGroup.value === 'gpt') return rows.filter((item) => /gpt|chatgpt|o[1-9]/i.test(item.model))
  if (modelGroup.value === 'claude') return rows.filter((item) => /claude/i.test(item.model))
  return rows
})

const topModels = computed(() => filteredModels.value.slice(0, 10))
const requestModels = computed(() => topModels.value.filter((item) => item.request_count > 0))
const summary = computed(() => stats.value?.summary)
const abnormalTotal = computed(() => abnormal.value.length)
const successTotal = computed(() => success.value.length)

const statCards = computed(() => [
  {
    label: 'Sub2API 用户',
    value: intText(userTotal.value),
    sub: '线上用户表',
    icon: DatabaseOutlined,
    tone: 'blue',
  },
  {
    label: '请求数',
    value: intText(summary.value?.request_count || 0),
    sub: currentRangeText.value,
    icon: RiseOutlined,
    tone: 'green',
  },
  {
    label: '使用用户',
    value: intText(summary.value?.user_count || 0),
    sub: 'usage_logs 去重',
    icon: FireOutlined,
    tone: 'orange',
  },
  {
    label: '模型数',
    value: intText(summary.value?.model_count || 0),
    sub: '当前时间段',
    icon: BarChartOutlined,
    tone: 'purple',
  },
  {
    label: 'Total Cost',
    value: moneyText(summary.value?.total_cost || 0),
    sub: '单位：元',
    icon: ClockCircleOutlined,
    tone: 'cyan',
  },
  {
    label: '异常/作废',
    value: intText(abnormalTotal.value),
    sub: '待复核记录',
    icon: WarningOutlined,
    tone: 'red',
  },
])

const currentRangeText = computed(() => {
  const [from, to] = activeRange()
  return `${from.format('MM-DD HH:mm')} 至 ${to.format('MM-DD HH:mm')}`
})

function activeRange(): [Dayjs, Dayjs] {
  const now = dayjs()

  if (rangeKey.value === 'today') return [now.startOf('day'), now.endOf('day')]
  if (rangeKey.value === 'week') return [chinaWeekStart(now), now.endOf('day')]
  if (rangeKey.value === 'month') return [now.startOf('month'), now.endOf('day')]
  if (rangeKey.value === 'thirty') return [now.subtract(30, 'day').startOf('day'), now.endOf('day')]
  if (rangeKey.value === 'custom') return customRange.value

  return [now.subtract(7, 'day').startOf('day'), now.endOf('day')]
}

async function loadDashboard() {
  loading.value = true
  try {
    const [from, to] = activeRange()
    const now = dayjs()
    const compareRanges = [
      { name: '今天', range: [now.startOf('day'), now.endOf('day')] as [Dayjs, Dayjs] },
      { name: '本周', range: [chinaWeekStart(now), now.endOf('day')] as [Dayjs, Dayjs] },
      { name: '本月', range: [now.startOf('month'), now.endOf('day')] as [Dayjs, Dayjs] },
    ]

    const [users, mainStats, successList, abnormalList, ...periodStats] = await Promise.all([
      getSub2Users({ page: 1, page_size: 1 }),
      getDashboardStats({ from: from.format(fmt), to: to.format(fmt), limit: 30, model_group: modelGroup.value }),
      getLedgerAdjustments({ page: 1, page_size: 6, status: 'succeeded' }),
      getLedgerAdjustments({ page: 1, page_size: 6, status: 'abnormal' }),
      ...compareRanges.map((item) =>
        getModelStats({
          from: item.range[0].format(fmt),
          to: item.range[1].format(fmt),
          limit: 1,
        }),
      ),
    ])

    userTotal.value = users.total
    stats.value = mainStats
    rechargeRank.value = mainStats.recharge_rank
    quotaRank.value = mainStats.quota_rank
    success.value = successList.items
    abnormal.value = abnormalList.items
    compareStats.value = compareRanges.map((item, index) => ({
      name: item.name,
      summary: periodStats[index].summary,
    }))
  } catch {
    stats.value = null
    compareStats.value = []
    success.value = []
    abnormal.value = []
    rechargeRank.value = []
    quotaRank.value = []
    message.error('读取首页看板失败')
  } finally {
    loading.value = false
    renderCharts()
  }
}

function changeCustomRange(val: [Dayjs, Dayjs] | null) {
  if (!val) return
  customRange.value = val
  if (rangeKey.value !== 'custom') {
    rangeKey.value = 'custom'
    return
  }
  loadDashboard()
}

function renderCharts() {
  nextTick(() => {
    renderModelChart()
    renderRequestChart()
    renderCompareChart()
  })
}

function renderModelChart() {
  if (!modelChartEl.value) return
  modelChart ||= init(modelChartEl.value)
  const rows = [...topModels.value].reverse()

  if (rows.length === 0) {
    modelChart.setOption(emptyChart('暂无模型消费数据'), true)
    return
  }

  modelChart.setOption(
    {
      backgroundColor: 'transparent',
      color: ['#4f7cff'],
      grid: { left: 8, right: 16, top: 16, bottom: 8, containLabel: true },
      tooltip: {
        trigger: 'axis',
        valueFormatter: (val: number) => moneyText(val),
      },
      xAxis: {
        type: 'value',
        axisLabel: { color: cssVar('--muted') },
        splitLine: { lineStyle: { color: cssVar('--border') } },
      },
      yAxis: {
        type: 'category',
        data: rows.map((item) => shortModel(item.model)),
        axisLabel: { color: cssVar('--text'), width: 120, overflow: 'truncate' },
        axisTick: { show: false },
        axisLine: { show: false },
      },
      series: [
        {
          type: 'bar',
          data: rows.map((item) => Number(item.total_cost || 0)),
          barWidth: 10,
          itemStyle: { borderRadius: [0, 5, 5, 0] },
        },
      ],
    },
    true,
  )
}

function renderRequestChart() {
  if (!requestChartEl.value) return
  requestChart ||= init(requestChartEl.value)

  if (requestModels.value.length === 0) {
    requestChart.setOption(emptyChart('暂无请求占比数据'), true)
    return
  }

  requestChart.setOption(
    {
      backgroundColor: 'transparent',
      color: ['#4f7cff', '#13a66b', '#f59e0b', '#ef5da8', '#06b6d4', '#8b5cf6'],
      tooltip: { trigger: 'item' },
      legend: {
        bottom: 0,
        type: 'scroll',
        textStyle: { color: cssVar('--muted') },
      },
      series: [
        {
          type: 'pie',
          radius: ['48%', '70%'],
          center: ['50%', '42%'],
          label: { color: cssVar('--text'), formatter: '{b}' },
          data: requestModels.value.map((item) => ({
            name: shortModel(item.model),
            value: item.request_count,
          })),
        },
      ],
    },
    true,
  )
}

function renderCompareChart() {
  if (!compareChartEl.value) return
  compareChart ||= init(compareChartEl.value)

  if (compareStats.value.length === 0) {
    compareChart.setOption(emptyChart('暂无周期对比数据'), true)
    return
  }

  compareChart.setOption(
    {
      backgroundColor: 'transparent',
      color: ['#13a66b', '#f59e0b'],
      grid: { left: 8, right: 8, top: 42, bottom: 8, containLabel: true },
      tooltip: { trigger: 'axis' },
      legend: {
        top: 0,
        textStyle: { color: cssVar('--muted') },
      },
      xAxis: {
        type: 'category',
        data: compareStats.value.map((item) => item.name),
        axisLabel: { color: cssVar('--muted') },
        axisTick: { show: false },
        axisLine: { lineStyle: { color: cssVar('--border') } },
      },
      yAxis: [
        {
          type: 'value',
          name: '请求',
          axisLabel: { color: cssVar('--muted') },
          splitLine: { lineStyle: { color: cssVar('--border') } },
        },
        {
          type: 'value',
          name: '金额',
          axisLabel: { color: cssVar('--muted') },
          splitLine: { show: false },
        },
      ],
      series: [
        {
          name: '请求数',
          type: 'bar',
          barWidth: 24,
          data: compareStats.value.map((item) => item.summary.request_count),
          itemStyle: { borderRadius: [6, 6, 0, 0] },
        },
        {
          name: 'Total Cost',
          type: 'line',
          yAxisIndex: 1,
          smooth: true,
          symbolSize: 7,
          data: compareStats.value.map((item) => Number(item.summary.total_cost || 0)),
        },
      ],
    },
    true,
  )
}

function emptyChart(text: string) {
  return {
    graphic: {
      type: 'text',
      left: 'center',
      top: 'middle',
      style: {
        text,
        fill: cssVar('--muted'),
        fontSize: 13,
      },
    },
    xAxis: { show: false },
    yAxis: { show: false },
    series: [],
  }
}

function resizeCharts() {
  modelChart?.resize()
  requestChart?.resize()
  compareChart?.resize()
}

function disposeCharts() {
  modelChart?.dispose()
  requestChart?.dispose()
  compareChart?.dispose()
}

function cssVar(name: string) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim()
}

function chinaWeekStart(val: Dayjs) {
  return val.subtract((val.day() + 6) % 7, 'day').startOf('day')
}

function shortModel(val: string) {
  return val.length > 24 ? `${val.slice(0, 24)}...` : val
}

function intText(val: number | string) {
  return Number(val || 0).toLocaleString('zh-CN')
}

function moneyText(val: number | string) {
  return Number(val || 0).toFixed(2)
}

function opText(row: LedgerAdjustment) {
  return row.operation === 'increment' ? '增加' : '扣减'
}

function costText(row: ModelRank) {
  return moneyText(row.total_cost)
}

watch(rangeKey, loadDashboard)
watch(modelGroup, loadDashboard)
watch(() => themeStore.themeName, renderCharts)

onMounted(() => {
  loadDashboard()
  window.addEventListener('resize', resizeCharts)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', resizeCharts)
  disposeCharts()
})
</script>

<template>
  <section class="page dashboardPage">
    <div class="pageHead dashboardHead">
      <div>
        <h1>首页看板</h1>
        <p>Sub2API 统计、模型消费排行与额度调整审计</p>
      </div>
      <div class="headActions">
        <a-segmented v-model:value="rangeKey" :options="rangeOptions" />
        <a-range-picker
          v-if="rangeKey === 'custom'"
          :value="customRange"
          show-time
          class="range"
          format="YYYY-MM-DD HH:mm:ss"
          @change="changeCustomRange"
        />
        <a-select v-model:value="modelGroup" class="modelSelect" :options="modelOptions" />
        <a-button :loading="loading" @click="loadDashboard">
          <template #icon><ReloadOutlined /></template>
          刷新
        </a-button>
      </div>
    </div>

    <a-spin :spinning="loading">
      <div class="metricGrid dashboardMetrics">
        <div v-for="item in statCards" :key="item.label" class="statCard">
          <div class="statIcon" :class="item.tone">
            <component :is="item.icon" />
          </div>
          <div class="statBody">
            <span>{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
            <em>{{ item.sub }}</em>
          </div>
        </div>
      </div>

      <div class="dashboardGrid">
        <section class="panel chartPanel chartWide">
          <div class="panelHead">
            <h2>模型消费榜</h2>
            <RouterLink to="/sub2api/models">查看明细</RouterLink>
          </div>
          <div ref="modelChartEl" class="chartBox"></div>
        </section>

        <section class="panel chartPanel">
          <div class="panelHead">
            <h2>模型请求占比</h2>
            <span class="panelMeta">{{ modelOptions.find((item) => item.value === modelGroup)?.label }}</span>
          </div>
          <div ref="requestChartEl" class="chartBox"></div>
        </section>

        <section class="panel chartPanel">
          <div class="panelHead">
            <h2>周期对比</h2>
            <span class="panelMeta">今天 / 本周 / 本月</span>
          </div>
          <div ref="compareChartEl" class="chartBox"></div>
        </section>
      </div>

      <div class="dashboardBottom">
        <section class="panel rankPanel">
          <div class="panelHead">
            <h2>模型消费排行</h2>
            <RouterLink to="/sub2api/models">全部模型</RouterLink>
          </div>
          <a-table
            row-key="model"
            size="small"
            :columns="modelColumns"
            :data-source="topModels"
            :pagination="false"
            :scroll="{ x: 620 }"
          >
            <template #bodyCell="{ column, record }">
              <template v-if="column.dataIndex === 'total_cost'">
                <span class="money">{{ costText(record) }}</span>
              </template>
            </template>
          </a-table>
        </section>

        <section class="panel">
          <div class="panelHead">
            <h2>额度调整动态</h2>
            <RouterLink to="/ledger">记录</RouterLink>
          </div>
          <div class="auditColumns">
            <div>
              <div class="miniTitle">
                <CheckCircleOutlined />
                成功调额 {{ successTotal }}
              </div>
              <a-empty v-if="success.length === 0" description="暂无成功调额" />
              <div v-else class="miniList">
                <div v-for="item in success" :key="item.id" class="miniItem">
                  <div>
                    <strong>{{ item.sub2api_user_email || `#${item.sub2api_user_id}` }}</strong>
                    <span>{{ item.ledger_no }} · {{ opText(item) }}</span>
                  </div>
                  <span class="money">{{ moneyText(item.amount) }}</span>
                </div>
              </div>
            </div>

            <div>
              <div class="miniTitle dangerText">
                <WarningOutlined />
                异常/作废 {{ abnormalTotal }}
              </div>
              <a-empty v-if="abnormal.length === 0" description="暂无异常单" />
              <div v-else class="miniList">
                <div v-for="item in abnormal" :key="item.id" class="miniItem dangerItem">
                  <div>
                    <strong>{{ item.ledger_no }}</strong>
                    <span>{{ item.exception_reason || item.adjust_reason }}</span>
                  </div>
                  <a-tag :color="item.status === 'exception' ? 'red' : 'orange'">
                    {{ item.status === 'exception' ? '异常' : '作废' }}
                  </a-tag>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="panel sourcePanel">
          <div class="panelHead">
            <h2>充值榜</h2>
            <span class="panelMeta">现金账</span>
          </div>
          <a-table row-key="sub2api_user_id" size="small" :columns="userRankColumns" :data-source="rechargeRank" :pagination="false" :scroll="{ x: 620 }">
            <template #bodyCell="{ column, record }">
              <template v-if="column.dataIndex === 'sub2api_user_email'">{{ record.sub2api_user_email || '-' }}</template>
              <template v-if="column.dataIndex === 'total_amount'"><span class="money">{{ moneyText(record.total_amount) }}</span></template>
            </template>
          </a-table>
        </section>

        <section class="panel sourcePanel">
          <div class="panelHead">
            <h2>额度使用榜</h2>
            <span class="panelMeta">成功调额</span>
          </div>
          <a-table row-key="sub2api_user_id" size="small" :columns="userRankColumns" :data-source="quotaRank" :pagination="false" :scroll="{ x: 620 }">
            <template #bodyCell="{ column, record }">
              <template v-if="column.dataIndex === 'sub2api_user_email'">{{ record.sub2api_user_email || '-' }}</template>
              <template v-if="column.dataIndex === 'total_amount'"><span class="money">{{ moneyText(record.total_amount) }}</span></template>
            </template>
          </a-table>
        </section>
      </div>
    </a-spin>
  </section>
</template>
