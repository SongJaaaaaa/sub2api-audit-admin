<script setup lang="ts">
import { RedoOutlined, StopOutlined } from '@ant-design/icons-vue'
import type { Dayjs } from 'dayjs'
import type { TablePaginationConfig } from 'ant-design-vue'
import { App as AntApp } from 'ant-design-vue'
import { BarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import { init, use, type ECharts } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getLedgerAdjustments, retryLedgerAdjustment, voidLedgerAdjustment, type LedgerAdjustment, type LedgerSummary } from '../api/ledger'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useAdminOptions } from '../composables/useAdminOptions'
import { useTableColumns } from '../composables/useTableColumns'
import { useAppMode } from '../app/composables/useAppMode'
import { useThemeStore } from '../stores/theme'

use([BarChart, GridComponent, TooltipComponent, CanvasRenderer])

const { message } = AntApp.useApp()
const { isAppMode } = useAppMode()
const route = useRoute()
const router = useRouter()
const themeStore = useThemeStore()
const adminOptions = useAdminOptions()
const loading = ref(false)
const retryingId = ref<number | null>(null)
const voidingId = ref<number | null>(null)
const items = ref<LedgerAdjustment[]>([])
const chartEl = ref<HTMLDivElement>()
let chart: ECharts | undefined
let itemsVersion = 0
const filters = reactive({ userId: '', email: '', operator: undefined as number | undefined, dates: undefined as [Dayjs, Dayjs] | undefined, minAmount: '', maxAmount: '' })
const summary = reactive<LedgerSummary>({ record_count: 0, user_count: 0, increment_total: '0.00', decrement_total: '0.00', net_total: '0.00', cash_total: '0.00', gift_total: '0.00', amount_total: '0.00', oldest_created_at: null, over_24h_count: 0, types: [] })
const page = reactive({ current: 1, pageSize: 20, total: 0 })
const mobileFiltersOpen = ref(false)
const mobileRetryConfirmOpen = ref(false)
const mobileVoidConfirmOpen = ref(false)
const mobileLoadingMore = ref(false)
const loadError = ref('')
const selectedItem = ref<LedgerAdjustment | null>((window.history.state?.appDetail as LedgerAdjustment | undefined) || null)
const allColumns = [
  { title: '业务单号', dataIndex: 'ledger_no', width: 180 },
  { title: '状态', dataIndex: 'status', width: 100 },
  { title: '用户ID', dataIndex: 'sub2api_user_id', width: 100 },
  { title: '邮箱', dataIndex: 'sub2api_user_email', width: 210 },
  { title: '操作人', dataIndex: 'operator_name', width: 140 },
  { title: '方向', dataIndex: 'operation', width: 90 },
  { title: '额度', dataIndex: 'amount', align: 'right', width: 120 },
  { title: '调前', dataIndex: 'before_balance', align: 'right', width: 120 },
  { title: '调后', dataIndex: 'after_balance', align: 'right', width: 120 },
  { title: '异常原因', dataIndex: 'exception_reason' },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
  { title: '操作', dataIndex: 'action', fixed: 'right', width: 150 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resizeColumn, resetColumns } = useTableColumns('exception-center-columns', allColumns, 1540)
const appDetailPage = computed(() => route.name === 'exception-detail')
const hasMoreItems = computed(() => page.current * page.pageSize < page.total)
const mobileFilterTags = computed(() => {
  const tags: Array<{ key: string; label: string }> = []
  if (filters.userId.trim()) tags.push({ key: 'userId', label: `用户 #${filters.userId.trim()}` })
  if (filters.email.trim()) tags.push({ key: 'email', label: `邮箱：${filters.email.trim()}` })
  if (filters.operator) {
    const admin = adminOptions.value.find(row => row.id === filters.operator)
    tags.push({ key: 'operator', label: `操作人：${admin?.name || filters.operator}` })
  }
  if (filters.dates) tags.push({ key: 'dates', label: '发生日期' })
  if (filters.minAmount || filters.maxAmount) tags.push({ key: 'amount', label: '金额范围' })
  return tags
})

async function loadItems() {
  const version = ++itemsVersion
  loading.value = true
  loadError.value = ''
  try {
    const res = await getLedgerAdjustments({
      page: page.current, page_size: page.pageSize, status: 'abnormal',
      sub2api_user_id: filters.userId, sub2api_user_email: filters.email, created_by: filters.operator,
      start_date: filters.dates?.[0].format('YYYY-MM-DD'), end_date: filters.dates?.[1].format('YYYY-MM-DD'),
      min_amount: filters.minAmount, max_amount: filters.maxAmount,
    })
    if (version !== itemsVersion) return
    items.value = res.items
    if (appDetailPage.value && !selectedItem.value) {
      selectedItem.value = res.items.find(item => item.id === Number(route.params.adjustmentId)) || null
    }
    page.total = res.total
    Object.assign(summary, res.summary)
    await nextTick()
    renderChart()
  } catch {
    if (version !== itemsVersion) return
    loadError.value = '异常列表暂不可用，请重试'
    message.error('读取异常记录失败')
  } finally { if (version === itemsVersion) loading.value = false }
}

async function loadMoreItems() {
  if (loading.value || mobileLoadingMore.value || !hasMoreItems.value) return
  const version = ++itemsVersion
  mobileLoadingMore.value = true
  try {
    const next = page.current + 1
    const res = await getLedgerAdjustments({
      page: next, page_size: page.pageSize, status: 'abnormal',
      sub2api_user_id: filters.userId, sub2api_user_email: filters.email, created_by: filters.operator,
      start_date: filters.dates?.[0].format('YYYY-MM-DD'), end_date: filters.dates?.[1].format('YYYY-MM-DD'),
      min_amount: filters.minAmount, max_amount: filters.maxAmount,
    })
    if (version !== itemsVersion) return
    items.value = [...items.value, ...res.items]
    page.current = next
    page.total = res.total
    Object.assign(summary, res.summary)
  } catch {
    if (version !== itemsVersion) return
    loadError.value = '异常列表暂不可用，请重试'
    message.error('加载更多异常记录失败')
  } finally {
    mobileLoadingMore.value = false
  }
}
function renderChart() {
  const rows = summary.types || []
  if (!chartEl.value || rows.length === 0) { chart?.dispose(); chart = undefined; return }
  chart ||= init(chartEl.value)
  chart.setOption({
    grid: { left: 70, right: 25, top: 15, bottom: 20 },
    tooltip: { trigger: 'axis', ...tooltipTheme(), formatter: (data: any[]) => { const row = rows[data[0].dataIndex]; return `${row.type === 'exception' ? '异常' : '作废'}<br/>数量：${row.record_count}<br/>用户数：${row.user_count}<br/>涉及金额：${row.amount_total}` } },
    xAxis: { type: 'value', minInterval: 1, axisLabel: { color: chartText() }, axisLine: { lineStyle: { color: chartAxis() } }, splitLine: { lineStyle: { color: chartSplit() } } },
    yAxis: { type: 'category', inverse: true, data: rows.map(row => row.type === 'exception' ? '异常' : '作废'), axisLabel: { color: chartText() }, axisLine: { lineStyle: { color: chartAxis() } } },
    series: [{ type: 'bar', data: rows.map(row => row.record_count), itemStyle: { color: themeStore.themeName === 'dark' ? '#f87171' : '#cf1322' }, barMaxWidth: 28 }],
  }, true)
}
function search() { page.current = 1; loadItems() }
function resetFilters() { Object.assign(filters, { userId: '', email: '', operator: undefined, dates: undefined, minAmount: '', maxAmount: '' }); search() }
function change(pager: TablePaginationConfig) { page.current = pager.current || 1; page.pageSize = pager.pageSize || 20; loadItems() }
async function retryItem(row: LedgerAdjustment) {
  retryingId.value = row.id
  try {
    const res = await retryLedgerAdjustment(row.id)
    message.success(res.message)
    mobileRetryConfirmOpen.value = false
    await loadItems()
    if (appDetailPage.value) await router.replace({ name: 'exception' })
  } catch (err) {
    const data = (err as { response?: { data?: { message?: string } } }).response?.data
    message.error(data?.message || '重试失败')
  } finally {
    retryingId.value = null
  }
}

async function voidItem(row: LedgerAdjustment) {
  voidingId.value = row.id
  try {
    const res = await voidLedgerAdjustment(row.id)
    message.success(res.message)
    mobileVoidConfirmOpen.value = false
    await loadItems()
    if (appDetailPage.value) await router.replace({ name: 'exception' })
  } catch (err) {
    const data = (err as { response?: { data?: { message?: string } } }).response?.data
    message.error(data?.message || '作废失败')
  } finally {
    voidingId.value = null
  }
}

async function openMobileDetail(row: LedgerAdjustment) {
  selectedItem.value = row
  await router.push({
    name: 'exception-detail',
    params: { adjustmentId: row.id },
    state: { appDetail: { ...row } },
  })
}

function confirmMobileRetry() {
  if (selectedItem.value) mobileRetryConfirmOpen.value = true
}

function clearMobileFilter(key: string) {
  if (key === 'userId') filters.userId = ''
  if (key === 'email') filters.email = ''
  if (key === 'operator') filters.operator = undefined
  if (key === 'dates') filters.dates = undefined
  if (key === 'amount') {
    filters.minAmount = ''
    filters.maxAmount = ''
  }
  search()
}

function resetMobileFilters() {
  resetFilters()
  mobileFiltersOpen.value = false
}
function resize() { chart?.resize() }
function chartText() { return themeStore.themeName === 'dark' ? '#c5c8d0' : '#586174' }
function chartAxis() { return themeStore.themeName === 'dark' ? '#4a4f5d' : '#d9dce3' }
function chartSplit() { return themeStore.themeName === 'dark' ? '#2d313b' : '#eef0f4' }
function tooltipTheme() {
  const dark = themeStore.themeName === 'dark'
  return {
    backgroundColor: dark ? 'rgba(17, 24, 39, 0.96)' : 'rgba(255, 255, 255, 0.96)',
    borderColor: dark ? '#374151' : '#e5e7eb',
    textStyle: { color: dark ? '#f8fafc' : '#172033' },
  }
}
onMounted(() => { loadItems(); window.addEventListener('resize', resize) })
onBeforeUnmount(() => { window.removeEventListener('resize', resize); chart?.dispose() })
watch(() => themeStore.themeName, renderChart)
</script>

<template>
  <section v-if="isAppMode" class="app-page app-exception-page">
    <template v-if="!appDetailPage">
    <header class="app-header">
      <div><span class="app-eyebrow">风险处理</span><h1>异常中心</h1></div>
      <strong class="app-count">{{ summary.record_count }} 条</strong>
    </header>
    <div class="app-toolbar">
      <a-input v-model:value="filters.email" class="app-search" placeholder="用户邮箱" allow-clear @press-enter="search" />
      <a-button @click="mobileFiltersOpen = !mobileFiltersOpen">筛选<span v-if="mobileFilterTags.length">（{{ mobileFilterTags.length }}）</span></a-button>
    </div>
    <div v-if="mobileFiltersOpen" class="app-filter-sheet" @keydown.esc="mobileFiltersOpen = false">
      <label class="app-field"><span>用户 ID</span><a-input v-model:value="filters.userId" allow-clear /></label>
      <label class="app-field"><span>用户邮箱</span><a-input v-model:value="filters.email" allow-clear /></label>
      <label class="app-field"><span>操作人</span><a-select v-model:value="filters.operator" allow-clear placeholder="全部操作人"><a-select-option v-for="row in adminOptions" :key="row.id" :value="row.id">{{ row.name }}（{{ row.email }}）</a-select-option></a-select></label>
      <label class="app-field"><span>发生日期</span><a-range-picker v-model:value="filters.dates" /></label>
      <div class="app-amount-fields"><a-input v-model:value="filters.minAmount" placeholder="最小金额" allow-clear /><a-input v-model:value="filters.maxAmount" placeholder="最大金额" allow-clear /></div>
      <div class="app-filter-actions"><a-button @click="resetMobileFilters">重置</a-button><a-button type="primary" @click="mobileFiltersOpen = false; search()">查询</a-button></div>
    </div>
    <div v-if="mobileFilterTags.length" class="app-filter-tags" aria-label="已生效筛选">
      <button v-for="tag in mobileFilterTags" :key="tag.key" type="button" class="app-filter-tag" @click="clearMobileFilter(tag.key)">{{ tag.label }} ×</button>
    </div>
    <div v-if="loadError" class="app-error-bar">
      <a-alert type="error" show-icon :message="loadError" />
      <a-button size="small" @click="loadItems">重试</a-button>
    </div>
    <div class="app-summary-grid">
      <div><span>异常总数</span><strong>{{ summary.record_count }}</strong></div>
      <div><span>涉及用户</span><strong>{{ summary.user_count }}</strong></div>
      <div><span>涉及金额</span><strong class="app-money">{{ summary.amount_total }}</strong></div>
      <div><span>超过 24 小时</span><strong class="app-danger">{{ summary.over_24h_count || 0 }}</strong></div>
    </div>
    <div v-if="summary.types?.length" class="app-chart-card"><h2>异常类型分布</h2><div ref="chartEl" class="app-chart"></div></div>
    <a-spin :spinning="loading && items.length === 0">
      <a-empty v-if="!loading && !loadError && items.length === 0" description="暂无异常记录" />
      <div v-else class="app-card-list">
        <article v-for="item in items" :key="item.id" class="app-card app-exception-card" tabindex="0" role="button" @click="openMobileDetail(item)" @keydown.enter="openMobileDetail(item)">
          <div class="app-card-top"><strong>{{ item.sub2api_user_email || `用户 #${item.sub2api_user_id}` }}</strong><span class="app-status" :class="item.status === 'exception' ? 'app-danger' : 'app-warning'">{{ item.status === 'exception' ? '异常' : '作废' }}</span></div>
          <div class="app-card-metrics"><div><span>额度</span><strong class="app-money">{{ item.amount }}</strong></div><div><span>方向</span><strong>{{ item.operation === 'increment' ? '增加' : '扣减' }}</strong></div></div>
          <div class="app-card-foot"><span>{{ item.exception_reason || '未提供原因' }}</span><span>{{ item.created_at || '-' }}</span></div>
        </article>
      </div>
    </a-spin>
    <button v-if="hasMoreItems" type="button" class="app-load-more" :disabled="mobileLoadingMore" @click="loadMoreItems">{{ mobileLoadingMore ? '加载中…' : '加载更多' }}</button>
    <p v-else-if="items.length" class="app-end-state">已显示全部异常记录</p>
    </template>

    <div v-else-if="selectedItem" class="app-detail appRouteDetail">
      <div class="app-detail-hero"><span class="app-avatar app-risk-avatar">!</span><div><h2>{{ selectedItem.sub2api_user_email || `用户 #${selectedItem.sub2api_user_id}` }}</h2><p>{{ selectedItem.ledger_no }} · {{ selectedItem.created_at || '-' }}</p></div></div>
      <dl class="app-detail-grid"><div><dt>状态</dt><dd :class="selectedItem.status === 'exception' ? 'app-danger' : 'app-warning'">{{ selectedItem.status === 'exception' ? '异常' : '作废' }}</dd></div><div><dt>操作方向</dt><dd>{{ selectedItem.operation === 'increment' ? '增加' : '扣减' }}</dd></div><div><dt>额度</dt><dd class="app-money">{{ selectedItem.amount }}</dd></div><div><dt>操作人</dt><dd>{{ selectedItem.operator_name || selectedItem.operator_email || '-' }}</dd></div><div><dt>调前余额</dt><dd>{{ selectedItem.before_balance || '-' }}</dd></div><div><dt>调后余额</dt><dd>{{ selectedItem.after_balance || '-' }}</dd></div></dl>
      <div class="app-reason"><span>异常原因</span><p>{{ selectedItem.exception_reason || '未提供原因' }}</p></div>
      <a-space direction="vertical" style="width: 100%">
        <a-button type="primary" block danger :loading="retryingId === selectedItem.id" :disabled="voidingId !== null" @click="confirmMobileRetry"><template #icon><RedoOutlined /></template>重试该调额</a-button>
        <a-button v-if="selectedItem.status === 'exception'" block :loading="voidingId === selectedItem.id" :disabled="retryingId !== null" @click="mobileVoidConfirmOpen = true"><template #icon><StopOutlined /></template>确认作废</a-button>
      </a-space>
    </div>
    <div v-else-if="loading" class="appListState">正在加载异常详情…</div>
    <a-empty v-else description="未找到该异常记录，请返回列表重新打开" />
    <a-modal v-model:open="mobileRetryConfirmOpen" title="确认重试异常调额" ok-text="确认重试" cancel-text="取消" :confirm-loading="retryingId !== null" :width="'calc(100vw - 24px)'" @ok="selectedItem && retryItem(selectedItem)">
      <div v-if="selectedItem" class="app-confirm-summary"><p><span>用户</span><strong>{{ selectedItem.sub2api_user_email || `用户 #${selectedItem.sub2api_user_id}` }}</strong></p><p><span>业务单号</span><strong>{{ selectedItem.ledger_no }}</strong></p><p><span>操作</span><strong>{{ selectedItem.operation === 'increment' ? '增加' : '扣减' }}</strong></p><p><span>额度</span><strong>{{ selectedItem.amount }}</strong></p><p><span>异常原因</span><strong>{{ selectedItem.exception_reason || '-' }}</strong></p></div>
    </a-modal>
    <a-modal v-model:open="mobileVoidConfirmOpen" title="确认作废异常调额" ok-text="确认作废" ok-type="danger" cancel-text="取消" :confirm-loading="voidingId !== null" :width="'calc(100vw - 24px)'" @ok="selectedItem && voidItem(selectedItem)">
      <div v-if="selectedItem" class="app-confirm-summary"><p><span>用户</span><strong>{{ selectedItem.sub2api_user_email || `用户 #${selectedItem.sub2api_user_id}` }}</strong></p><p><span>业务单号</span><strong>{{ selectedItem.ledger_no }}</strong></p><p><span>金额影响</span><strong>{{ selectedItem.operation === 'increment' ? '+' : '-' }}{{ selectedItem.amount }}</strong></p><p><span>处理规则</span><strong>仅远端不存在该幂等流水时允许作废</strong></p></div>
    </a-modal>
  </section>

  <section v-else class="page">
    <div class="filterBar">
      <a-input v-model:value="filters.userId" class="filterId" placeholder="用户 ID" allow-clear />
      <a-input v-model:value="filters.email" class="filterLg" placeholder="用户邮箱" allow-clear />
      <a-select v-model:value="filters.operator" class="filterLg" placeholder="操作人" allow-clear><a-select-option v-for="row in adminOptions" :key="row.id" :value="row.id">{{ row.name }}（{{ row.email }}）</a-select-option></a-select>
      <a-range-picker v-model:value="filters.dates" class="filterDate" />
      <a-input v-model:value="filters.minAmount" class="filterAmount" placeholder="最小金额" allow-clear />
      <a-input v-model:value="filters.maxAmount" class="filterAmount" placeholder="最大金额" allow-clear />
      <a-button type="primary" @click="search">查询</a-button><a-button @click="resetFilters">重置</a-button>
    </div>
    <div class="summaryGrid">
      <section><span>异常总数</span><strong>{{ summary.record_count }}</strong></section>
      <section><span>用户数</span><strong>{{ summary.user_count }}</strong></section>
      <section><span>涉及金额</span><strong class="money">{{ summary.amount_total }}</strong></section>
      <section><span>超过 24 小时</span><strong class="negative">{{ summary.over_24h_count || 0 }}</strong></section>
      <section><span>最早异常时间</span><strong class="timeValue">{{ summary.oldest_created_at || '-' }}</strong></section>
    </div>
    <div v-if="summary.types?.length" class="chartCard"><h3>异常类型分布</h3><div ref="chartEl" class="chart"></div></div>
    <div class="tableTools"><ColumnSettings v-model:value="visibleCols" v-model:width="tableWidth" :options="colOptions" @reset="resetColumns" /></div>
    <a-table row-key="id" :columns="columns" :data-source="items" :loading="loading" :pagination="page" :scroll="{ x: tableWidth }" :locale="{ emptyText: '暂无异常记录' }" @resize-column="resizeColumn" @change="change">
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'status'"><a-tag :color="record.status === 'exception' ? 'red' : 'orange'">{{ record.status === 'exception' ? '异常' : '作废' }}</a-tag></template>
        <template v-else-if="column.dataIndex === 'operation'">{{ record.operation === 'increment' ? '增加' : '扣减' }}</template>
        <template v-else-if="column.dataIndex === 'operator_name'">{{ record.operator_name || record.operator_email || '-' }}</template>
        <template v-else-if="['amount', 'before_balance', 'after_balance'].includes(column.dataIndex as string)"><span class="money">{{ record[column.dataIndex] || '-' }}</span></template>
        <template v-else-if="column.dataIndex === 'action'">
          <a-space>
            <a-popconfirm :title="`确认按原单重试 ${record.ledger_no}（${record.amount}）？`" ok-text="确认" cancel-text="取消" @confirm="retryItem(record)"><a-button type="link" size="small" :loading="retryingId === record.id"><template #icon><RedoOutlined /></template>重试</a-button></a-popconfirm>
            <a-popconfirm v-if="record.status === 'exception'" title="系统将先核对远端幂等流水，确认不存在后才会作废。继续？" ok-text="确认作废" cancel-text="取消" @confirm="voidItem(record)"><a-button type="link" danger size="small" :loading="voidingId === record.id"><template #icon><StopOutlined /></template>作废</a-button></a-popconfirm>
          </a-space>
        </template>
      </template>
    </a-table>
  </section>
</template>

<style scoped>
.filterBar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
.filterId { flex: 0 0 120px; }.filterAmount { flex: 0 0 130px; }.filterLg { flex: 0 0 220px; }.filterDate { flex: 0 0 250px; }
.summaryGrid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.summaryGrid section, .chartCard { padding: 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 12px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; margin-bottom: 6px; color: var(--text-secondary, #7a8395); font-size: 13px; }
.summaryGrid strong { font-size: 23px; }.timeValue { font-size: 15px !important; }.negative { color: var(--danger); }
.chartCard { margin-bottom: 14px; }.chartCard h3 { margin: 0; }.chart { height: 220px; }
@media (max-width: 760px) { .filterBar > * { flex: 1 1 100%; width: 100% !important; } .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }

/* App shared styles centralized. */
.app-toolbar { grid-template-columns: minmax(0, 1fr) auto; }
.app-chart-card { padding: 13px; }
.app-chart { height: 192px; }
.app-card-list { gap: 9px; }
.app-card-foot { margin-top: 9px; }
.app-card-metrics { margin-top: 11px; }
.app-reason { padding: 10px; border-radius: var(--app-radius-sm); background: var(--surface2); }
@media (max-width: 360px) { .app-card-foot { align-items: flex-start; flex-direction: column; } }
</style>
