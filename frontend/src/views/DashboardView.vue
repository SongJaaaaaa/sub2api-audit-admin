<script setup lang="ts">
import {
  AlertOutlined,
  DatabaseOutlined,
  DollarOutlined,
  ReloadOutlined,
  RiseOutlined,
  TeamOutlined,
} from '@ant-design/icons-vue'
import { message } from 'ant-design-vue'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import { BarChart, LineChart } from 'echarts/charts'
import { DataZoomComponent, GraphicComponent, GridComponent, LegendComponent, TooltipComponent } from 'echarts/components'
import { init, use, type ECharts } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
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
    + val.reconcile_issue_count
    + val.external_adjustment_count
    + val.audit_orphan_count
})

const alertCards = computed(() => {
  const val = stats.value?.alerts
  if (!val) return []

  return [
    { label: '未关联成功单', value: val.unlinked_adjustment_count, path: '/ledger', tone: 'orange' },
    { label: '对账问题', value: val.reconcile_issue_count, path: '/reconcile', tone: 'red' },
    { label: '外部后台调额', value: val.external_adjustment_count, path: '/balance-events', tone: 'blue' },
    { label: '审计孤儿', value: val.audit_orphan_count, path: '/balance-events', tone: 'purple' },
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
  if (!financeEl.value || !stats.value) return
  financeChart ||= init(financeEl.value)
  const rows = stats.value.finance.trend

  financeChart.setOption({
    backgroundColor: 'transparent',
    color: ['#1677ff', '#9254de', '#52c41a', '#ff4d4f'],
    tooltip: { trigger: 'axis' },
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
  if (!costEl.value || !stats.value) return
  costChart ||= init(costEl.value)
  const rows = stats.value.usage.trend

  costChart.setOption({
    backgroundColor: 'transparent',
    color: ['#fa8c16'],
    tooltip: { trigger: 'axis' },
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
  if (!el) return chart
  chart ||= init(el)
  const rows = [...source].sort((a, b) => value(b) - value(a))
  chart.setOption({
    grid: { left: 16, right: 18, top: 18, bottom: rows.length > 8 ? 66 : 48, containLabel: true },
    tooltip: {
      trigger: 'item',
      formatter: ({ dataIndex }: { dataIndex: number }) => {
        const row = rows[dataIndex]
        return row ? [`<strong>${userLabel(row)}</strong>`, ...details(row)].join('<br>') : ''
      },
    },
    xAxis: { type: 'category', data: rows.map(userLabel), axisLabel: { color: labelColor(), rotate: -28, interval: 0, formatter: (val: string) => shortLabel(val) }, axisLine: { lineStyle: { color: axisColor() } } },
    yAxis: { type: 'value', axisLabel: { color: labelColor(), formatter: compactAxis }, splitLine: { lineStyle: { color: splitColor() } } },
    dataZoom: rows.length > 8 ? [{ type: 'slider', xAxisIndex: 0, bottom: 4, startValue: 0, endValue: 7 }, { type: 'inside', xAxisIndex: 0 }] : [],
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
              <h2><AlertOutlined /> 对账告警</h2>
            </div>
            <a-badge :count="alertTotal" :overflow-count="9999" />
          </div>
          <div class="alertGrid">
            <RouterLink v-for="item in alertCards" :key="item.label" :to="item.path" class="alertItem" :class="item.tone">
              <span>{{ item.label }}</span>
              <strong>{{ item.value }}</strong>
            </RouterLink>
          </div>
          <div class="lastReconcile">最近对账日期：{{ stats.alerts.last_reconciled_date || '尚未对账' }}</div>
        </section>

        <div class="chartGrid">
          <section class="panel financePanel">
            <div class="sectionHead">
              <div><h2>财务趋势</h2></div>
            </div>
            <div ref="financeEl" class="chart chartWide"></div>
          </section>
          <section class="panel usagePanel">
            <div class="sectionHead">
              <div><h2>实际消费趋势</h2></div>
            </div>
            <div ref="costEl" class="chart chartSmall"></div>
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

      <a-empty v-else-if="!loading" description="暂无可展示的真实统计数据" />
    </a-spin>
  </section>
</template>

<style scoped>
.dashboardV2 { display: grid; gap: 18px; }
.dashboardHead { gap: 18px; }
.statsAlert { margin-bottom: 2px; }
.kpiGrid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-top: 16px; }
.kpiCard :deep(.ant-card-body) { display: flex; align-items: center; gap: 16px; min-height: 138px; }
.kpiCard { overflow: hidden; box-shadow: 0 10px 28px rgba(30, 42, 70, .08); }
.kpiIcon { display: grid; place-items: center; flex: 0 0 50px; width: 50px; height: 50px; border-radius: 15px; color: #fff; font-size: 22px; }
.cashKpi .kpiIcon { background: linear-gradient(135deg, #1677ff, #69b1ff); }
.sourceKpi .kpiIcon { background: #5b6472; }
.costKpi .kpiIcon { background: linear-gradient(135deg, #fa8c16, #ffc069); }
.balanceKpi .kpiIcon { background: linear-gradient(135deg, #13a8a8, #5cdbd3); }
.kpiBody { min-width: 0; display: grid; gap: 5px; }
.kpiBody span { color: var(--text-secondary, #6f7788); }
.kpiBody strong { font-size: clamp(24px, 2.2vw, 34px); line-height: 1.15; font-variant-numeric: tabular-nums; }
.kpiBody em { color: var(--text-secondary, #7a8395); font-size: 13px; font-style: normal; }
.panel, .alertPanel { padding: 18px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 14px; background: var(--card-bg, #fff); box-shadow: 0 8px 24px rgba(30, 42, 70, .05); }
.alertPanel { margin-top: 16px; }
.sectionHead { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 14px; }
.sectionHead h2 { margin: 0; font-size: 17px; }
.alertGrid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.alertItem { display: flex; align-items: center; justify-content: space-between; min-height: 70px; padding: 14px; border-radius: 10px; color: inherit; background: #f6f8fb; }
.alertItem strong { font-size: 25px; }
.alertItem.orange { border-left: 4px solid #fa8c16; }
.alertItem.red { border-left: 4px solid #ff4d4f; }
.alertItem.blue { border-left: 4px solid #1677ff; }
.alertItem.purple { border-left: 4px solid #9254de; }
.lastReconcile { margin-top: 10px; color: var(--text-secondary, #7a8395); font-size: 12px; text-align: right; }
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
.money { color: #d46b08; }
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
</style>
