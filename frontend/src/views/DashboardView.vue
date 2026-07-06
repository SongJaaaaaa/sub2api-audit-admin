<script setup lang="ts">
import {
  CheckCircleOutlined,
  CrownFilled,
  DollarOutlined,
  ReloadOutlined,
  RiseOutlined,
  ThunderboltOutlined,
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
import { type ModelRank, type UsageSummary } from '../api/sub2api'
import { useThemeStore } from '../stores/theme'

type RangeKey = 'today' | 'week' | 'month' | 'seven' | 'thirty' | 'custom'
type ModelGroup = 'all' | 'gpt' | 'claude'

interface CompareItem {
  name: string
  summary: UsageSummary
  rechargeTotal: string
  quotaTotal: string
}

use([BarChart, LineChart, PieChart, GridComponent, GraphicComponent, LegendComponent, TooltipComponent, CanvasRenderer])

const fmt = 'YYYY-MM-DD HH:mm:ss'
const themeStore = useThemeStore()
const loading = ref(false)
const rangeKey = ref<RangeKey>('seven')
const modelGroup = ref<ModelGroup>('all')
const customRange = ref<[Dayjs, Dayjs]>([dayjs().subtract(7, 'day').startOf('day'), dayjs().endOf('day')])
const stats = ref<DashboardStatsRes | null>(null)
const compareStats = ref<CompareItem[]>([])
const success = ref<LedgerAdjustment[]>([])
const rechargeRank = ref<UserRank[]>([])
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
  { title: 'Token', dataIndex: 'token_total', align: 'right', width: 120 },
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
const tokenTotal = computed(() => Number(summary.value?.token_total || 0))
const rechargeTop = computed(() => rechargeRank.value.slice(0, 10))
const tokenTopModels = computed(() =>
  [...topModels.value].sort((a, b) => Number(b.token_total || 0) - Number(a.token_total || 0)).slice(0, 5),
)

const topCards = computed(() => [
  {
    label: '成交额',
    value: moneyText(stats.value?.recharge_total || 0),
    sub: `${rechargeTop.value.length} 位上榜用户`,
    icon: DollarOutlined,
    tone: 'recharge',
  },
  {
    label: '总消费',
    value: moneyText(summary.value?.total_cost || 0),
    sub: `实际成本 ${moneyText(summary.value?.actual_cost || 0)}`,
    icon: RiseOutlined,
    tone: 'cost',
  },
  {
    label: 'Sub2API 总额度',
    value: moneyText(stats.value?.quota_total || 0),
    sub: '成功调额额度汇总',
    icon: ThunderboltOutlined,
    tone: 'quota',
  },
])

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

    const [mainStats, successList, ...periodStats] = await Promise.all([
      getDashboardStats({ from: from.format(fmt), to: to.format(fmt), limit: 30, model_group: modelGroup.value }),
      getLedgerAdjustments({ page: 1, page_size: 12, status: 'succeeded' }),
      ...compareRanges.map((item) =>
        getDashboardStats({
          from: item.range[0].format(fmt),
          to: item.range[1].format(fmt),
          limit: 10,
          model_group: modelGroup.value,
        }),
      ),
    ])

    stats.value = mainStats
    rechargeRank.value = mainStats.recharge_rank
    success.value = successList.items.filter((item) => item.operation === 'increment').slice(0, 6)
    compareStats.value = compareRanges.map((item, index) => ({
      name: item.name,
      summary: periodStats[index].summary,
      rechargeTotal: periodStats[index].recharge_total || '0',
      quotaTotal: periodStats[index].quota_total || '0',
    }))
  } catch {
    stats.value = null
    compareStats.value = []
    success.value = []
    rechargeRank.value = []
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
      color: ['#2f7cff', '#14b86a', '#f59e0b'],
      grid: { left: 8, right: 8, top: 42, bottom: 8, containLabel: true },
      tooltip: {
        trigger: 'axis',
        valueFormatter: (val: number | string) => Number(val).toLocaleString('zh-CN'),
      },
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
          name: '金额',
          axisLabel: { color: cssVar('--muted') },
          splitLine: { lineStyle: { color: cssVar('--border') } },
        },
        {
          type: 'value',
          name: '额度',
          axisLabel: { color: cssVar('--muted') },
          splitLine: { show: false },
        },
      ],
      series: [
        {
          name: '充值',
          type: 'bar',
          barWidth: 24,
          data: compareStats.value.map((item) => Number(item.rechargeTotal || 0)),
          itemStyle: { borderRadius: [6, 6, 0, 0] },
        },
        {
          name: '总消费',
          type: 'line',
          smooth: true,
          symbolSize: 7,
          data: compareStats.value.map((item) => Number(item.summary.total_cost || 0)),
        },
        {
          name: 'Sub2API总额度',
          type: 'line',
          yAxisIndex: 1,
          smooth: true,
          symbolSize: 7,
          data: compareStats.value.map((item) => Number(item.quotaTotal || 0)),
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

function tokenText(val: number | string) {
  const num = Number(val || 0)
  if (num >= 100000000) return `${(num / 100000000).toFixed(2)}亿`
  if (num >= 10000) return `${(num / 10000).toFixed(2)}万`
  return intText(num)
}

function userName(row: UserRank) {
  return row.sub2api_user_email || `用户 #${row.sub2api_user_id}`
}

function rankClass(index: number) {
  if (index === 0) return 'gold'
  if (index === 1) return 'silver'
  if (index === 2) return 'bronze'
  return ''
}

function opText(row: LedgerAdjustment) {
  return row.operation === 'increment' ? '充值' : '扣减'
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
      <div class="soyHomeKpis">
        <section v-for="item in topCards" :key="item.label" class="usageTopCard" :class="item.tone">
          <div class="usageTopIcon">
            <component :is="item.icon" />
          </div>
          <div>
            <span>{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
            <em>{{ item.sub }}</em>
          </div>
        </section>
      </div>

      <div class="soyHomeMain">
        <section class="panel chartPanel trendPanel">
          <div class="panelHead">
            <h2>成交额 / 消费 / 额度周期趋势</h2>
            <span class="panelMeta">今天 / 本周 / 本月</span>
          </div>
          <div ref="compareChartEl" class="chartBox trendChart"></div>
          <div class="trendPills">
            <span>成交额</span>
            <span>总消费</span>
            <span>Sub2API 总额度</span>
          </div>
        </section>

        <section class="panel rechargeRankPanel">
          <div class="panelHead">
            <h2>充值榜</h2>
            <RouterLink to="/operation-expense">查看现金账</RouterLink>
          </div>
          <div class="sub2Badge">
            <div class="sub2Logo">S2</div>
            <div>
              <strong>Sub2API</strong>
              <span>Top 10 充值用户</span>
            </div>
          </div>
          <a-empty v-if="rechargeTop.length === 0" description="暂无充值排行" />
          <div v-else class="rankScroll">
            <div
              v-for="(item, index) in rechargeTop"
              :key="item.sub2api_user_id"
              class="rechargeRankItem"
              :class="rankClass(index)"
            >
              <div class="rankBadge" :class="rankClass(index)">
                <CrownFilled v-if="index < 3" />
                <span v-else>{{ index + 1 }}</span>
              </div>
              <div class="rankUser">
                <strong>{{ userName(item) }}</strong>
                <span>ID {{ item.sub2api_user_id }} · {{ item.entry_count }} 笔</span>
              </div>
              <div class="rankMoney">
                <strong>{{ moneyText(item.total_amount) }}</strong>
                <span>充值</span>
              </div>
            </div>
          </div>
        </section>
      </div>

      <div class="soyHomeData">
        <section class="panel rankPanel">
          <div class="panelHead">
            <h2>模型消费榜</h2>
            <RouterLink to="/sub2api/models">查看明细</RouterLink>
          </div>
          <a-table
            row-key="model"
            size="small"
            :columns="modelColumns"
            :data-source="topModels"
            :pagination="false"
            :scroll="{ x: 720 }"
          >
            <template #bodyCell="{ column, record }">
              <template v-if="column.dataIndex === 'total_cost'">
                <span class="money">{{ costText(record) }}</span>
              </template>
              <template v-if="column.dataIndex === 'token_total'">
                <span>{{ tokenText(record.token_total || 0) }}</span>
              </template>
            </template>
          </a-table>
        </section>

        <section class="panel chartPanel">
          <div class="panelHead">
            <h2>模型请求占比</h2>
            <span class="panelMeta">{{ modelOptions.find((item) => item.value === modelGroup)?.label }}</span>
          </div>
          <div ref="requestChartEl" class="chartBox"></div>
        </section>
      </div>

      <div class="soyHomeFinal">
        <section class="panel tokenRankPanel">
          <div class="panelHead">
            <h2>Token 使用榜</h2>
            <span class="panelMeta">总计 {{ tokenText(tokenTotal) }}</span>
          </div>
          <a-empty v-if="tokenTopModels.length === 0" description="暂无 Token 数据" />
          <div v-else class="tokenUserList">
            <div v-for="(item, index) in tokenTopModels" :key="item.model" class="tokenUserItem">
              <span class="tokenIndex">{{ String(index + 1).padStart(2, '0') }}</span>
              <div class="tokenAvatar">{{ shortModel(item.model).slice(0, 1).toUpperCase() }}</div>
              <div class="tokenUserName">
                <strong>{{ shortModel(item.model) }}</strong>
                <span>{{ intText(item.request_count) }} 次请求</span>
              </div>
              <div class="tokenBar">
                <strong>{{ tokenText(item.token_total || 0) }}</strong>
                <span :style="{ width: `${Math.max(8, Math.min(100, Number(item.token_total || 0) / Math.max(1, Number(tokenTopModels[0]?.token_total || 1)) * 100))}%` }"></span>
              </div>
            </div>
          </div>
        </section>

        <section class="panel adminRechargePanel">
          <div class="panelHead">
            <h2>管理员充值记录动态</h2>
            <RouterLink to="/ledger">记录</RouterLink>
          </div>
          <a-empty v-if="success.length === 0" description="暂无管理员充值记录" />
          <div v-else class="adminTimeline">
            <div v-for="item in success" :key="item.id" class="adminTimelineItem">
              <span class="adminTimelineDot"><CheckCircleOutlined /></span>
              <div>
                <strong>{{ item.sub2api_user_email || `用户 #${item.sub2api_user_id}` }}</strong>
                <span>{{ item.ledger_no }} · {{ opText(item) }} · {{ item.confirmed_at || item.created_at || '-' }}</span>
                <em v-if="item.adjust_reason">备注：{{ item.adjust_reason }}</em>
              </div>
              <strong class="money">+ {{ moneyText(item.amount) }}</strong>
            </div>
          </div>
        </section>
      </div>
    </a-spin>
  </section>
</template>
