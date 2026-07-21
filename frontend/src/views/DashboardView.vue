<script setup lang="ts">
import {
  AlertOutlined,
  DatabaseOutlined,
  DollarOutlined,
  ReloadOutlined,
  RiseOutlined,
  TeamOutlined,
} from '@ant-design/icons-vue'
import { App as AntApp } from 'ant-design-vue'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import { BarChart, LineChart } from 'echarts/charts'
import { DataZoomComponent, GraphicComponent, GridComponent, LegendComponent, TooltipComponent } from 'echarts/components'
import { init, use, type ECharts } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useAppMode } from '../app/composables/useAppMode'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useTableColumns } from '../composables/useTableColumns'
import {
  getDashboardStats,
  type DashboardStatsRes,
  type RechargeUserRank,
  type RecentAdjustment,
  type UserActualCostRank,
  type UserTokenRank,
} from '../api/dashboard'
import { useThemeStore } from '../stores/theme'

type RangeKey = 'today' | 'week' | 'month' | 'seven' | 'thirty' | 'custom'

use([BarChart, LineChart, DataZoomComponent, GraphicComponent, GridComponent, LegendComponent, TooltipComponent, CanvasRenderer])

const { message } = AntApp.useApp()
const { isAppMode } = useAppMode()
const themeStore = useThemeStore()
const loading = ref(false)
const rangeKey = ref<RangeKey>('seven')
const customRange = ref<[Dayjs, Dayjs]>([dayjs().subtract(6, 'day'), dayjs()])
const stats = ref<DashboardStatsRes | null>(null)
const statsError = ref('')
const financeEl = ref<HTMLDivElement | null>(null)
const costEl = ref<HTMLDivElement | null>(null)
const rechargeRankEl = ref<HTMLDivElement | null>(null)
const costRankEl = ref<HTMLDivElement | null>(null)
let financeChart: ECharts | null = null
let costChart: ECharts | null = null
let rechargeRankChart: ECharts | null = null
let costRankChart: ECharts | null = null
let loadSeq = 0

const rangeOptions = [
  { label: '今天', value: 'today' },
  { label: '本周', value: 'week' },
  { label: '本月', value: 'month' },
  { label: '近 7 天', value: 'seven' },
  { label: '近 30 天', value: 'thirty' },
  { label: '自定义', value: 'custom' },
]

const recentColumns = [
  { title: '时间', dataIndex: 'event_at', width: 165 },
  { title: '本地单号', dataIndex: 'ledger_no', width: 180 },
  { title: '用户', dataIndex: 'user', width: 230 },
  { title: '方向', dataIndex: 'operation', width: 72 },
  { title: '金额', dataIndex: 'amount', width: 120, align: 'right' },
  { title: '状态', dataIndex: 'status', width: 82 },
  { title: '远端事件', dataIndex: 'source', width: 110 },
  { title: '原因', dataIndex: 'adjust_reason', minWidth: 160 },
] as const
const { columns: visibleRecentColumns, visibleCols, colOptions, tableWidth, resizeColumn, resetColumns } = useTableColumns('dashboard-recent-columns', recentColumns, 1050)


const alertTotal = computed(() => {
  const val = stats.value?.alerts
  if (!val) return 0
  return val.unlinked_adjustment_count
})

const alertCards = computed(() => {
  const val = stats.value?.alerts
  if (!val) return []

  return [
    { label: '未关联成功单', value: val.unlinked_adjustment_count, path: '/ledger', tone: 'orange' },
  ]
})

function activeRange(): [Dayjs, Dayjs] {
  const now = dayjs()
  if (rangeKey.value === 'today') return [now, now]
  if (rangeKey.value === 'week') return [weekStart(now), now]
  if (rangeKey.value === 'month') return [now.startOf('month'), now]
  if (rangeKey.value === 'thirty') return [now.subtract(29, 'day'), now]
  if (rangeKey.value === 'custom') return customRange.value
  return [now.subtract(6, 'day'), now]
}

function weekStart(date: Dayjs) {
  return date.subtract((date.day() + 6) % 7, 'day')
}

async function loadDashboard() {
  const seq = ++loadSeq
  const [start, end] = activeRange()
  loading.value = true
  statsError.value = ''

  try {
    const res = await getDashboardStats({
      start_date: start.format('YYYY-MM-DD'),
      end_date: end.format('YYYY-MM-DD'),
      limit: 10,
    })
    if (seq !== loadSeq) return
    disposeCharts()
    stats.value = res
  } catch (err) {
    if (seq !== loadSeq) return
    stats.value = null
    disposeCharts()
    const data = (err as { response?: { data?: { code?: string; message?: string } } }).response?.data
    statsError.value = data?.code === 'SUB2API_STATS_UNAVAILABLE'
      ? 'Sub2API 官方统计暂不可用，页面不会用 0 代替真实数据。'
      : (data?.message || '读取首页看板失败。')
    message.error(statsError.value)
  } finally {
    if (seq !== loadSeq) return
    loading.value = false
    renderCharts()
  }
}

function changeCustomRange(val: [Dayjs, Dayjs] | null) {
  if (!val) return
  customRange.value = val
  loadDashboard()
}

function renderCharts() {
  nextTick(() => {
    if (!stats.value) return
    drawFinance()
    drawCost()
    drawRankings()
  })
}

function drawFinance() {
  if (!stats.value) return
  const rows = stats.value.finance.trend
  if (!financeEl.value || rows.length === 0) {
    financeChart?.dispose()
    financeChart = null
    return
  }
  financeChart ||= init(financeEl.value)

  financeChart.setOption({
    backgroundColor: 'transparent',
    color: ['#1677ff', '#9254de', '#52c41a', '#ff4d4f'],
    tooltip: { trigger: 'axis', ...tooltipTheme() },
    legend: { top: 0, textStyle: { color: labelColor() } },
    grid: { left: 18, right: 18, top: 44, bottom: 18, containLabel: true },
    xAxis: {
      type: 'category',
      data: rows.map(row => row.date.slice(5)),
      axisLabel: { color: labelColor() },
      axisLine: { lineStyle: { color: axisColor() } },
    },
    yAxis: {
      type: 'value',
      name: '金额',
      nameTextStyle: { color: labelColor() },
      axisLabel: { color: labelColor(), formatter: moneyAxis },
      splitLine: { lineStyle: { color: splitColor() } },
    },
    series: [
      { name: '现金', type: 'bar', data: rows.map(row => Number(row.cash_total)), barMaxWidth: 22 },
      { name: '赠送', type: 'bar', data: rows.map(row => Number(row.gift_total)), barMaxWidth: 22 },
      { name: '调增', type: 'line', smooth: true, data: rows.map(row => Number(row.adjustment_in_total)) },
      { name: '调减', type: 'line', smooth: true, data: rows.map(row => Number(row.adjustment_out_total)) },
    ],
  }, true)
}

function drawCost() {
  if (!stats.value) return
  const rows = stats.value.usage.trend
  if (!costEl.value || rows.length === 0) {
    costChart?.dispose()
    costChart = null
    return
  }
  costChart ||= init(costEl.value)

  costChart.setOption({
    backgroundColor: 'transparent',
    color: ['#fa8c16'],
    tooltip: { trigger: 'axis', ...tooltipTheme() },
    grid: { left: 12, right: 16, top: 28, bottom: 16, containLabel: true },
    xAxis: {
      type: 'category',
      data: rows.map(row => row.date.slice(5)),
      axisLabel: { color: labelColor() },
      axisLine: { lineStyle: { color: axisColor() } },
    },
    yAxis: {
      type: 'value',
      name: '实际消费',
      nameTextStyle: { color: labelColor() },
      axisLabel: { color: labelColor(), formatter: moneyAxis },
      splitLine: { lineStyle: { color: splitColor() } },
    },
    series: [{
      name: '实际消费',
      type: 'line',
      smooth: true,
      areaStyle: { opacity: 0.12 },
      data: rows.map(row => Number(row.actual_cost)),
    }],
  }, true)
}

function drawRankings() {
  if (!stats.value) return
  rechargeRankChart = drawRankChart(rechargeRankEl.value, rechargeRankChart, stats.value.rankings.recharge_users, row => Number(row.cash_total), '#d4a017', (row) => [
    `用户 ID：${row.user_id}`,
    `入账笔数：${count(row.entry_count)}`,
    `实收入账：${money(row.cash_total)}`,
  ])
  costRankChart = drawRankChart(costRankEl.value, costRankChart, stats.value.rankings.user_actual_cost, row => Number(row.actual_cost), '#35a853', (row) => [
    `用户 ID：${row.user_id}`,
    `请求次数：${count(row.request_count)}`,
    `总 Token：${tokens(row.total_tokens)}`,
    `实际消费：${money(row.actual_cost)}`,
  ])

}

function drawRankChart<T extends RechargeUserRank | UserActualCostRank | UserTokenRank>(
  el: HTMLDivElement | null,
  chart: ECharts | null,
  source: T[],
  value: (row: T) => number,
  color: string,
  details: (row: T) => string[],
) {
  if (!el || source.length === 0) {
    chart?.dispose()
    return null
  }
  if (chart && chart.getDom() !== el) {
    chart.dispose()
    chart = null
  }
  chart ||= init(el)
  const rows = [...source].sort((a, b) => value(b) - value(a))
  chart.setOption({
    grid: { left: 16, right: 18, top: 18, bottom: rows.length > 8 ? 66 : 48, containLabel: true },
    tooltip: {
      trigger: 'item',
      ...tooltipTheme(),
      formatter: ({ dataIndex }: { dataIndex: number }) => {
        const row = rows[dataIndex]
        return row ? [`<strong>${userLabel(row)}</strong>`, ...details(row)].join('<br>') : ''
      },
    },
    xAxis: { type: 'category', data: rows.map(userLabel), axisLabel: { color: labelColor(), rotate: -28, interval: 0, formatter: (val: string) => shortLabel(val) }, axisLine: { lineStyle: { color: axisColor() } } },
    yAxis: { type: 'value', axisLabel: { color: labelColor(), formatter: compactAxis }, splitLine: { lineStyle: { color: splitColor() } } },
    dataZoom: rows.length > 8 ? [{ type: 'slider', xAxisIndex: 0, bottom: 4, startValue: 0, endValue: 7, ...zoomTheme() }, { type: 'inside', xAxisIndex: 0 }] : [],
    series: [{ type: 'bar', data: rows.map(value), barMaxWidth: 38, itemStyle: { color, borderRadius: [6, 6, 0, 0] }, emphasis: { itemStyle: { shadowBlur: 12, shadowColor: `${color}66` } } }],
  }, true)
  return chart
}

function shortLabel(val: string) {
  return val.length > 16 ? `${val.slice(0, 14)}…` : val
}

function userLabel(row: RechargeUserRank | UserTokenRank | UserActualCostRank) {
  return row.email || `用户 #${row.user_id}`
}

function statusText(status: RecentAdjustment['status']) {
  return { succeeded: '成功', exception: '异常', voided: '作废' }[status]
}

function statusColor(status: RecentAdjustment['status']) {
  return { succeeded: 'green', exception: 'red', voided: 'default' }[status]
}

function directionText(op: RecentAdjustment['operation']) {
  return op === 'increment' ? '调增' : '调减'
}

function signedAmount(row: RecentAdjustment) {
  return `${row.operation === 'increment' ? '+' : '-'}${money(row.amount)}`
}

function money(val: string | number | null | undefined, digits = 2) {
  if (val === null || val === undefined || val === '') return '--'
  return Number(val).toLocaleString('zh-CN', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  })
}

function tokens(val: number | null | undefined) {
  return val === null || val === undefined ? '--' : val.toLocaleString('zh-CN')
}

function count(val: number | null | undefined) {
  return val === null || val === undefined ? '--' : val.toLocaleString('zh-CN')
}

function moneyAxis(val: number) {
  return Number(val).toLocaleString('zh-CN', { maximumFractionDigits: 2 })
}

function compactAxis(val: number) {
  if (Math.abs(val) >= 1_000_000_000) return `${(val / 1_000_000_000).toFixed(1)}B`
  if (Math.abs(val) >= 1_000_000) return `${(val / 1_000_000).toFixed(1)}M`
  if (Math.abs(val) >= 1_000) return `${(val / 1_000).toFixed(1)}K`
  return `${val}`
}

function labelColor() {
  return themeStore.themeName === 'dark' ? '#c5c8d0' : '#586174'
}

function axisColor() {
  return themeStore.themeName === 'dark' ? '#4a4f5d' : '#d9dce3'
}

function splitColor() {
  return themeStore.themeName === 'dark' ? '#2d313b' : '#eef0f4'
}

function tooltipTheme() {
  const dark = themeStore.themeName === 'dark'
  return {
    backgroundColor: dark ? 'rgba(17, 24, 39, 0.96)' : 'rgba(255, 255, 255, 0.96)',
    borderColor: dark ? '#374151' : '#e5e7eb',
    textStyle: { color: dark ? '#f8fafc' : '#172033' },
  }
}

function zoomTheme() {
  const dark = themeStore.themeName === 'dark'
  const primary = dark ? '#60a5fa' : '#2563eb'
  const fill = dark ? 'rgba(96, 165, 250, 0.2)' : 'rgba(37, 99, 235, 0.14)'
  return {
    backgroundColor: 'transparent',
    borderColor: axisColor(),
    fillerColor: fill,
    textStyle: { color: labelColor() },
    handleStyle: { color: primary, borderColor: primary },
    moveHandleStyle: { color: primary },
    dataBackground: { lineStyle: { color: axisColor() }, areaStyle: { color: splitColor() } },
    selectedDataBackground: { lineStyle: { color: primary }, areaStyle: { color: fill } },
  }
}

function resizeCharts() {
  financeChart?.resize()
  costChart?.resize()
  rechargeRankChart?.resize()
  costRankChart?.resize()
}

function disposeCharts() {
  financeChart?.dispose()
  costChart?.dispose()
  rechargeRankChart?.dispose()
  costRankChart?.dispose()
  financeChart = null
  costChart = null
  rechargeRankChart = null
  costRankChart = null
}

watch(rangeKey, (key) => {
  if (key !== 'custom') loadDashboard()
})
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
  <section class="page dashboardV2">
    <div class="pageHead dashboardHead pageHeadActionsOnly">
      <div class="headActions">
        <a-segmented v-model:value="rangeKey" :options="rangeOptions" />
        <a-range-picker
          v-if="rangeKey === 'custom'"
          :value="customRange"
          format="YYYY-MM-DD"
          :allow-clear="false"
          @change="changeCustomRange"
        />
        <a-button :loading="loading" @click="loadDashboard">
          <template #icon><ReloadOutlined /></template>
          刷新
        </a-button>
      </div>
    </div>

    <a-alert
      v-if="statsError"
      class="statsAlert"
      type="error"
      show-icon
      :message="statsError"
      description="请检查 Sub2API Admin API 配置或服务状态后重试。"
    />

    <a-spin :spinning="loading">
      <template v-if="stats">
        <template v-if="isAppMode">
          <div class="appDashboardKpis">
            <article class="appKpiCard appKpiCash">
              <span>实收入账</span>
              <strong>{{ money(stats.finance.cash_total) }}</strong>
              <small>本地现金账</small>
            </article>
            <article class="appKpiCard appKpiSource">
              <span>累计充值</span>
              <strong>{{ money(stats.balance.total_recharged) }}</strong>
              <small>Sub2API 当前快照</small>
            </article>
            <article class="appKpiCard appKpiCost">
              <span>实际消费</span>
              <strong>{{ money(stats.usage.actual_cost) }}</strong>
              <small>{{ count(stats.usage.request_count) }} 次请求</small>
            </article>
            <article class="appKpiCard appKpiBalance">
              <span>启用用户余额</span>
              <strong>{{ money(stats.balance.active_user_balance) }}</strong>
              <small>{{ count(stats.balance.active_user_count) }} 位用户</small>
            </article>
          </div>

          <section v-if="alertCards.length" class="appPanel appAlertPanel">
            <div class="appSectionHead"><h2>账务告警</h2><a-badge :count="alertTotal" :overflow-count="9999" /></div>
            <RouterLink v-for="item in alertCards" :key="item.label" :to="item.path" class="appAlertCard">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </RouterLink>
          </section>

          <section class="appPanel">
            <div class="appSectionHead"><h2>财务趋势</h2><span>{{ rangeOptions.find(item => item.value === rangeKey)?.label }}</span></div>
            <a-empty v-if="stats.finance.trend.length === 0" description="所选范围暂无财务趋势" />
            <div v-else ref="financeEl" class="appChart appChartFinance"></div>
          </section>
          <section class="appPanel">
            <div class="appSectionHead"><h2>实际消费趋势</h2><span>{{ rangeOptions.find(item => item.value === rangeKey)?.label }}</span></div>
            <a-empty v-if="stats.usage.trend.length === 0" description="所选范围暂无消费趋势" />
            <div v-else ref="costEl" class="appChart appChartCost"></div>
          </section>

          <section class="appPanel">
            <div class="appSectionHead"><h2>本地现金入账用户榜</h2></div>
            <a-empty v-if="!stats.rankings.recharge_users.length" description="暂无本地现金入账" />
            <div v-else ref="rechargeRankEl" class="appChart appChartRank"></div>
          </section>
          <section class="appPanel">
            <div class="appSectionHead"><h2>用户实际消费榜</h2></div>
            <a-empty v-if="!stats.rankings.user_actual_cost.length" description="暂无消费数据" />
            <div v-else ref="costRankEl" class="appChart appChartRank"></div>
          </section>

          <section class="appPanel">
            <div class="appSectionHead"><h2>最近调额记录</h2><RouterLink to="/ledger">全部记录</RouterLink></div>
            <div v-if="stats.recent_adjustments.length" class="appRecordList">
              <article v-for="record in stats.recent_adjustments" :key="record.id" class="appRecordCard">
                <div class="appRecordHead">
                  <strong>{{ record.sub2api_user_email || `用户 #${record.sub2api_user_id}` }}</strong>
                  <a-tag :color="statusColor(record.status)">{{ statusText(record.status) }}</a-tag>
                </div>
                <div class="appRecordMetric" :class="record.operation === 'increment' ? 'positive' : 'negative'">{{ signedAmount(record) }}</div>
                <div class="appRecordMeta"><span>{{ record.event_at || '-' }}</span><span>{{ record.ledger_no || '-' }}</span></div>
                <div class="appRecordMeta"><span>{{ directionText(record.operation) }}</span><span>{{ record.sub2api_source_id || '未关联' }}</span></div>
                <p v-if="record.adjust_reason" class="appRecordNote">{{ record.adjust_reason }}</p>
              </article>
            </div>
            <a-empty v-else description="所选范围暂无调额记录" />
          </section>
        </template>

        <template v-else>
        <div class="kpiGrid">
          <a-card class="kpiCard cashKpi" :bordered="false">
            <div class="kpiIcon"><DollarOutlined /></div>
            <div class="kpiBody">
              <span>实收入账</span>
              <strong>{{ money(stats.finance.cash_total) }}</strong>
              <em>本地现金账 · 随所选日期变化</em>
            </div>
          </a-card>
          <a-card class="kpiCard sourceKpi" :bordered="false">
            <div class="kpiIcon"><DatabaseOutlined /></div>
            <div class="kpiBody">
              <span>Sub2API 累计充值字段</span>
              <strong>{{ money(stats.balance.total_recharged) }}</strong>
              <em>当前快照 · 不等于本地现金实收</em>
            </div>
          </a-card>
          <a-card class="kpiCard costKpi" :bordered="false">
            <div class="kpiIcon"><RiseOutlined /></div>
            <div class="kpiBody">
              <span>实际消费</span>
              <strong>{{ money(stats.usage.actual_cost) }}</strong>
              <em>Token {{ tokens(stats.usage.total_tokens) }} · 请求 {{ count(stats.usage.request_count) }}</em>
            </div>
          </a-card>
          <a-card class="kpiCard balanceKpi" :bordered="false">
            <div class="kpiIcon"><TeamOutlined /></div>
            <div class="kpiBody">
              <span>普通启用用户当前余额</span>
              <strong>{{ money(stats.balance.active_user_balance) }}</strong>
              <em>{{ count(stats.balance.active_user_count) }} 位用户 · 当前快照，不随日期变化</em>
            </div>
          </a-card>
        </div>

        <section class="alertPanel">
          <div class="sectionHead">
            <div>
              <h2><AlertOutlined /> 账务告警</h2>
            </div>
            <a-badge :count="alertTotal" :overflow-count="9999" />
          </div>
          <div class="alertGrid">
            <RouterLink v-for="item in alertCards" :key="item.label" :to="item.path" class="alertItem" :class="item.tone">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </RouterLink>
          </div>
        </section>

        <div class="chartGrid">
          <section class="panel financePanel">
            <div class="sectionHead">
              <div><h2>财务趋势</h2></div>
            </div>
            <a-empty v-if="stats.finance.trend.length === 0" description="所选范围暂无财务趋势" />
            <div v-else ref="financeEl" class="chart chartWide"></div>
          </section>
          <section class="panel usagePanel">
            <div class="sectionHead">
              <div><h2>实际消费趋势</h2></div>
            </div>
            <a-empty v-if="stats.usage.trend.length === 0" description="所选范围暂无消费趋势" />
            <div v-else ref="costEl" class="chart chartSmall"></div>
          </section>
        </div>

        <div class="rankingGrid">
          <section class="panel rankPanel">
            <div class="sectionHead"><div><h2>本地现金入账用户榜</h2></div></div>
            <a-empty v-if="!stats.rankings.recharge_users.length" description="暂无本地现金入账" />
            <div v-else ref="rechargeRankEl" class="rankChart"></div>
          </section>
          <section class="panel rankPanel">
            <div class="sectionHead"><div><h2>用户实际消费榜</h2></div></div>
            <a-empty v-if="!stats.rankings.user_actual_cost.length" description="暂无消费数据" />
            <div v-else ref="costRankEl" class="rankChart"></div>
          </section>

        </div>

        <section class="panel recentPanel">
          <div class="sectionHead">
            <div><h2>最近调额记录</h2></div>
            <RouterLink to="/ledger">全部记录</RouterLink>
          </div>
          <div class="tableTools"><ColumnSettings v-model:value="visibleCols" v-model:width="tableWidth" :options="colOptions" @reset="resetColumns" /></div>
          <a-table row-key="id" size="small" :columns="visibleRecentColumns" :data-source="stats.recent_adjustments" :pagination="false" :scroll="{ x: tableWidth }" :locale="{ emptyText: '所选范围暂无调额记录' }" @resize-column="resizeColumn">
            <template #bodyCell="{ column, record }">
              <template v-if="column.dataIndex === 'user'">{{ record.sub2api_user_email || `用户 #${record.sub2api_user_id}` }}</template>
              <template v-else-if="column.dataIndex === 'operation'">{{ directionText(record.operation) }}</template>
              <template v-else-if="column.dataIndex === 'amount'"><span class="money">{{ signedAmount(record) }}</span></template>
              <template v-else-if="column.dataIndex === 'status'"><a-tag :color="statusColor(record.status)">{{ statusText(record.status) }}</a-tag></template>
              <template v-else-if="column.dataIndex === 'source'">{{ record.sub2api_source_id || '未关联' }}</template>
            </template>
          </a-table>
        </section>
        </template>
      </template>

      <a-empty v-else-if="!loading" description="暂无可展示的真实统计数据" />
    </a-spin>
  </section>
</template>

<style scoped>
/* Dashboard app-mode specific overrides & desktop styles only.
   Base .appKpiCard / .appPanel / etc now live in app/styles/app.css for consistency. */
.appDashboardKpis { gap: 10px; }
.appPanel { margin-bottom: 12px; }
.appChartFinance { height: 260px; }
.appChartCost { height: 232px; }
.appChartRank { height: 262px; }

.dashboardV2 { display: grid; gap: 18px; }
.dashboardHead { gap: 18px; }
.statsAlert { margin-bottom: 2px; }
.kpiGrid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-top: 16px; }
.kpiCard :deep(.ant-card-body) { display: flex; align-items: center; gap: 16px; min-height: 138px; }
.kpiCard { overflow: hidden; box-shadow: var(--shadow-card); }
.kpiIcon { display: grid; place-items: center; flex: 0 0 50px; width: 50px; height: 50px; border-radius: 15px; color: #fff; font-size: 22px; }
.cashKpi .kpiIcon { background: linear-gradient(135deg, #1677ff, #69b1ff); }
.sourceKpi .kpiIcon { background: #5b6472; }
.costKpi .kpiIcon { background: linear-gradient(135deg, #fa8c16, #ffc069); }
.balanceKpi .kpiIcon { background: linear-gradient(135deg, #13a8a8, #5cdbd3); }
.kpiBody { min-width: 0; display: grid; gap: 5px; }
.kpiBody span { color: var(--text-secondary, #6f7788); }
.kpiBody strong { font-size: clamp(24px, 2.2vw, 34px); line-height: 1.15; font-variant-numeric: tabular-nums; }
.kpiBody em { color: var(--text-secondary, #7a8395); font-size: 13px; font-style: normal; }
.panel, .alertPanel { padding: 18px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 14px; background: var(--card-bg, #fff); box-shadow: var(--shadow-card); }
.alertPanel { margin-top: 16px; }
.sectionHead { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
.sectionHead h2 { margin: 0; font-size: 17px; }
.alertGrid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.alertItem { display: flex; align-items: center; justify-content: space-between; min-height: 70px; padding: 14px; border-radius: 10px; color: inherit; background: var(--surface2); }
.alertItem strong { font-size: 25px; }
.alertItem.orange { border-left: 4px solid #fa8c16; }
.alertItem.red { border-left: 4px solid #ff4d4f; }
.alertItem.blue { border-left: 4px solid #1677ff; }
.alertItem.purple { border-left: 4px solid #9254de; }
.chartGrid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr); gap: 16px; margin-top: 16px; }
.chart { width: 100%; }
.chartWide { height: 370px; }
.chartSmall { height: 315px; }
.rankingGrid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-top: 16px; }
.rankPanel { min-width: 0; }
.rankChart { width: 100%; height: 340px; }
.rankPanel :deep(.ant-table-cell strong) { display: block; font-weight: 600; }
.rankPanel :deep(.ant-table-cell small) { display: block; margin-top: 2px; color: var(--text-secondary, #7a8395); }
.money { font-variant-numeric: tabular-nums; font-weight: 600; }
.money { color: var(--warning); }
.recentPanel { margin-top: 16px; }

@media (max-width: 1180px) {
  .kpiGrid, .alertGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .chartGrid { grid-template-columns: 1fr; }
}
@media (max-width: 820px) {
  .kpiGrid, .alertGrid, .rankingGrid { grid-template-columns: 1fr; }
  .headActions { align-items: stretch; }
}
@media (max-width: 640px) {
  .dashboardV2 { gap: 12px; }
  .dashboardHead { gap: 10px; }
  .dashboardHead .headActions { display: grid; gap: 8px; width: 100%; overflow: hidden; }
  .dashboardHead :deep(.ant-segmented) { width: 100%; overflow-x: auto; }
  .dashboardHead :deep(.ant-segmented-group) { min-width: max-content; }
  .dashboardHead :deep(.ant-picker), .dashboardHead :deep(.ant-btn) { width: 100%; }
  .kpiGrid, .alertGrid, .chartGrid, .rankingGrid { grid-template-columns: minmax(0, 1fr); gap: 10px; margin-top: 10px; }
  .kpiCard :deep(.ant-card-body) { min-height: 104px; padding: 14px; }
  .kpiIcon { flex-basis: 42px; width: 42px; height: 42px; border-radius: 12px; }
  .kpiBody strong { font-size: 23px; }
  .panel, .alertPanel { min-width: 0; padding: 12px; border-radius: 11px; }
  .alertPanel { margin-top: 10px; }
  .alertItem { min-height: 58px; padding: 11px; }
  .sectionHead { gap: 8px; margin-bottom: 8px; }
  .chartWide, .chartSmall { height: 285px; }
  .rankChart { height: 310px; }
  .recentPanel { margin-top: 10px; overflow: hidden; }
}
@media (max-width: 360px) {
  .appDashboardKpis { grid-template-columns: 1fr; }
  .appKpiCard strong { font-size: 20px; }
}
</style>
