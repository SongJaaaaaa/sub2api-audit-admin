<script setup lang="ts">
import { RedoOutlined } from '@ant-design/icons-vue'
import type { Dayjs } from 'dayjs'
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import { BarChart } from 'echarts/charts'
import { GridComponent, TooltipComponent } from 'echarts/components'
import { init, use, type ECharts } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { getLedgerAdjustments, retryLedgerAdjustment, type LedgerAdjustment, type LedgerSummary } from '../api/ledger'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useAdminOptions } from '../composables/useAdminOptions'
import { useTableColumns } from '../composables/useTableColumns'

use([BarChart, GridComponent, TooltipComponent, CanvasRenderer])

const adminOptions = useAdminOptions()
const loading = ref(false)
const retryingId = ref<number | null>(null)
const items = ref<LedgerAdjustment[]>([])
const chartEl = ref<HTMLDivElement>()
let chart: ECharts | undefined
const filters = reactive({ userId: '', email: '', operator: undefined as number | undefined, dates: undefined as [Dayjs, Dayjs] | undefined, minAmount: '', maxAmount: '' })
const summary = reactive<LedgerSummary>({ record_count: 0, user_count: 0, increment_total: '0.00', decrement_total: '0.00', net_total: '0.00', cash_total: '0.00', gift_total: '0.00', amount_total: '0.00', oldest_created_at: null, over_24h_count: 0, types: [] })
const page = reactive({ current: 1, pageSize: 20, total: 0 })
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
  { title: '操作', dataIndex: 'action', fixed: 'right', width: 90 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resizeColumn, resetColumns } = useTableColumns('exception-center-columns', allColumns, 1540)

async function loadItems() {
  loading.value = true
  try {
    const res = await getLedgerAdjustments({
      page: page.current, page_size: page.pageSize, status: 'abnormal',
      sub2api_user_id: filters.userId, sub2api_user_email: filters.email, created_by: filters.operator,
      start_date: filters.dates?.[0].format('YYYY-MM-DD'), end_date: filters.dates?.[1].format('YYYY-MM-DD'),
      min_amount: filters.minAmount, max_amount: filters.maxAmount,
    })
    items.value = res.items
    page.total = res.total
    Object.assign(summary, res.summary)
    await nextTick()
    renderChart()
  } catch { message.error('读取异常记录失败') } finally { loading.value = false }
}
function renderChart() {
  const rows = summary.types || []
  if (!chartEl.value || rows.length === 0) { chart?.dispose(); chart = undefined; return }
  chart ||= init(chartEl.value)
  chart.setOption({
    grid: { left: 70, right: 25, top: 15, bottom: 20 },
    tooltip: { trigger: 'axis', formatter: (data: any[]) => { const row = rows[data[0].dataIndex]; return `${row.type === 'exception' ? '异常' : '作废'}<br/>数量：${row.record_count}<br/>用户数：${row.user_count}<br/>涉及金额：${row.amount_total}` } },
    xAxis: { type: 'value', minInterval: 1 },
    yAxis: { type: 'category', inverse: true, data: rows.map(row => row.type === 'exception' ? '异常' : '作废') },
    series: [{ type: 'bar', data: rows.map(row => row.record_count), itemStyle: { color: '#cf1322' }, barMaxWidth: 28 }],
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
    await loadItems()
  } catch (err) {
    const data = (err as { response?: { data?: { message?: string } } }).response?.data
    message.error(data?.message || '重试失败')
  } finally {
    retryingId.value = null
  }
}
function resize() { chart?.resize() }
onMounted(() => { loadItems(); window.addEventListener('resize', resize) })
onBeforeUnmount(() => { window.removeEventListener('resize', resize); chart?.dispose() })
</script>

<template>
  <section class="page">
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
          <a-popconfirm title="确认按原单重试？" ok-text="确认" cancel-text="取消" @confirm="retryItem(record)">
            <a-button type="link" size="small" :loading="retryingId === record.id">
              <template #icon><RedoOutlined /></template>
              重试
            </a-button>
          </a-popconfirm>
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
.summaryGrid strong { font-size: 23px; }.timeValue { font-size: 15px !important; }.negative { color: #cf1322; }
.chartCard { margin-bottom: 14px; }.chartCard h3 { margin: 0; }.chart { height: 220px; }
@media (max-width: 760px) { .filterBar > * { flex: 1 1 100%; width: 100% !important; } .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
</style>
