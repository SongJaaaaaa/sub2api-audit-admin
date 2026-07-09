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
import { BarChart, LineChart } from 'echarts/charts'
import { GridComponent, GraphicComponent, LegendComponent, TooltipComponent } from 'echarts/components'
import { init, use, type ECharts } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { getDashboardStats, type DashboardStatsRes, type UserRank, type UserTokenRank } from '../api/dashboard'
import { getLedgerAdjustments, type LedgerAdjustment } from '../api/ledger'
import { useThemeStore } from '../stores/theme'

type RangeKey = 'today' | 'week' | 'month' | 'seven' | 'thirty' | 'custom'

use([BarChart, LineChart, GridComponent, GraphicComponent, LegendComponent, TooltipComponent, CanvasRenderer])

const fmt = 'YYYY-MM-DD HH:mm:ss'
const themeStore = useThemeStore()
const loading = ref(false)
const rangeKey = ref<RangeKey>('seven')
const customRange = ref<[Dayjs, Dayjs]>([dayjs().subtract(7, 'day').startOf('day'), dayjs().endOf('day')])
const stats = ref<DashboardStatsRes | null>(null)
const success = ref<LedgerAdjustment[]>([])
const rechargeRank = ref<UserRank[]>([])
const financeChartEl = ref<HTMLDivElement | null>(null)
const userCostChartEl = ref<HTMLDivElement | null>(null)

let financeChart: ECharts | null = null
let userCostChart: ECharts | null = null

const rangeOptions = [
  { label: '今天', value: 'today' },
  { label: '本周', value: 'week' },
  { label: '本月', value: 'month' },
  { label: '近 7 天', value: 'seven' },
  { label: '近 30 天', value: 'thirty' },
  { label: '自定义', value: 'custom' },
]

const summary = computed(() => stats.value?.summary)
const rechargeTop = computed(() => rechargeRank.value.slice(0, 10))
const userCostTop = computed(() => (stats.value?.user_cost_rank || stats.value?.user_token_rank || []).slice(0, 10))
const financeRows = computed(() => stats.value?.finance_trend || [])
const hasFinanceTrend = computed(() => financeRows.value.length > 0)

const topCards = computed(() => [
  {
    label: '充值金额',
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
    value: wanText(stats.value?.sub2api_balance_total || 0),
    sub: '当前用户余额汇总',
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

    const [mainStats, successList] = await Promise.all([
      getDashboardStats({ from: from.format(fmt), to: to.format(fmt), limit: 30 }),
      getLedgerAdjustments({ page: 1, page_size: 12, status: 'succeeded' }),
    ])

    stats.value = mainStats
    rechargeRank.value = mainStats.recharge_rank
    success.value = successList.items.filter((item) => item.operation === 'increment').slice(0, 6)
  } catch {
    stats.value = null
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
    renderFinanceChart()
    renderUserCostChart()
  })
}

function renderUserCostChart() {
  if (!userCostChartEl.value) return
  userCostChart ||= init(userCostChartEl.value)
  const rows = [...userCostTop.value].reverse()

  if (rows.length === 0) {
    userCostChart.setOption(emptyChart('暂无用户消费数据'), true)
    return
  }

  userCostChart.setOption(
    {
      backgroundColor: 'transparent',
      color: ['#4f7cff'],
      grid: { left: 8, right: 22, top: 16, bottom: 8, containLabel: true },
      tooltip: {
        trigger: 'axis',
        formatter: (params: any) => {
          const p = params[0]
          const row = rows[p.dataIndex]

          return `${userCostName(row)}<br/>消费金额：<b>${moneyText(row.total_cost)}</b><br/>请求：${intText(row.request_count)} 次<br/>Token：${tokenText(row.token_total || 0)}`
        },
      },
      xAxis: {
        type: 'value',
        axisLabel: { color: cssVar('--muted') },
        splitLine: { lineStyle: { color: cssVar('--border') } },
      },
      yAxis: {
        type: 'category',
        data: rows.map((item) => shortUser(userCostName(item))),
        axisLabel: { color: cssVar('--text'), width: 120, overflow: 'truncate' },
        axisTick: { show: false },
        axisLine: { show: false },
      },
      series: [
        {
          type: 'bar',
          data: rows.map((item) => Number(item.total_cost || 0)),
          barWidth: 12,
          itemStyle: { borderRadius: [0, 5, 5, 0] },
        },
      ],
    },
    true,
  )
}

function renderFinanceChart() {
  if (!financeChartEl.value) return
  financeChart ||= init(financeChartEl.value)

  if (financeRows.value.length === 0) {
    financeChart.setOption(emptyChart('暂无充值数据'), true)
    return
  }

  const isToday = rangeKey.value === 'today'
  const dates = financeRows.value.map((item) => item.date)
  const recharge = financeRows.value.map((item) => Number(item.sub2api_adjust_total || 0))

  financeChart.setOption(
    {
      backgroundColor: 'transparent',
      color: ['#2f7cff'],
      grid: { left: 8, right: 8, top: 42, bottom: 8, containLabel: true },
      tooltip: {
        trigger: 'axis',
        valueFormatter: (val: number | string) => moneyText(val),
      },
      legend: {
        top: 0,
        textStyle: { color: cssVar('--muted') },
      },
      xAxis: {
        type: 'category',
        data: isToday ? ['今日'] : dates,
        axisLabel: { color: cssVar('--muted') },
        axisTick: { show: false },
        axisLine: { lineStyle: { color: cssVar('--border') } },
      },
      yAxis: {
        type: 'value',
        name: '金额',
        axisLabel: { color: cssVar('--muted') },
        splitLine: { lineStyle: { color: cssVar('--border') } },
      },
      series: isToday
        ? [
            {
              name: '充值金额',
              type: 'bar',
              barWidth: 42,
              data: [sum(recharge)],
              itemStyle: { borderRadius: [6, 6, 0, 0] },
            },
          ]
        : [
            { name: '充值金额', type: 'line', smooth: true, symbolSize: 7, data: recharge },
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
  financeChart?.resize()
  userCostChart?.resize()
}

function disposeCharts() {
  financeChart?.dispose()
  userCostChart?.dispose()
}

function cssVar(name: string) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim()
}

function chinaWeekStart(val: Dayjs) {
  return val.subtract((val.day() + 6) % 7, 'day').startOf('day')
}

function intText(val: number | string) {
  return Number(val || 0).toLocaleString('zh-CN')
}

function moneyText(val: number | string) {
  return Number(val || 0).toFixed(2)
}

function wanText(val: number | string) {
  return `${(Number(val || 0) / 10000).toFixed(2)}万`
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

function userCostName(row: UserTokenRank) {
  return row.user_email || `用户 #${row.user_id}`
}

function shortUser(val: string) {
  return val.length > 22 ? `${val.slice(0, 22)}...` : val
}

function sum(rows: number[]) {
  return rows.reduce((total, val) => total + val, 0)
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

watch(rangeKey, loadDashboard)
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
        <p>Sub2API 充值统计、模型消费排行与审计记录</p>
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
            <h2>充值趋势</h2>
            <span class="panelMeta">{{ rangeOptions.find((item) => item.value === rangeKey)?.label }}</span>
          </div>
          <div v-show="hasFinanceTrend" ref="financeChartEl" class="chartBox trendChart"></div>
          <a-empty v-if="!hasFinanceTrend" class="chartEmpty" description="暂无充值趋势数据" />
          <div class="trendPills">
            <span>充值金额</span>
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

      <div class="soyHomeFinal">
        <section class="panel chartPanel tokenRankPanel">
          <div class="panelHead">
            <h2>Token 使用榜</h2>
            <span class="panelMeta">按消费金额 Top 10</span>
          </div>
          <div ref="userCostChartEl" class="chartBox userCostChart"></div>
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
