<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import dayjs, { type Dayjs } from 'dayjs'
import { BarChart } from 'echarts/charts'
import { DataZoomComponent, GridComponent, TooltipComponent } from 'echarts/components'
import { init, use, type ECharts } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { createOperationExpense, getOperationExpenses, type ExpenseCategory, type ExpenseSummary, type OperationExpense } from '../api/finance'
import AttachmentUploader from '../components/attachments/AttachmentUploader.vue'
import SafeRichTextDisplay from '../components/richtext/SafeRichTextDisplay.vue'
import SafeRichTextEditor from '../components/richtext/SafeRichTextEditor.vue'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useAdminOptions } from '../composables/useAdminOptions'
import { useTableColumns } from '../composables/useTableColumns'

use([BarChart, DataZoomComponent, GridComponent, TooltipComponent, CanvasRenderer])

const adminOptions = useAdminOptions()
const loading = ref(false)
const submitting = ref(false)
const modalOpen = ref(false)
const drawerOpen = ref(false)
const items = ref<OperationExpense[]>([])
const categories = ref<ExpenseCategory[]>([])
const summary = reactive<ExpenseSummary>({ record_count: 0, category_count: 0, amount_total: '0.00', max_amount: '0.00', daily_average: null })
const selected = ref<OperationExpense | null>(null)
const chartEl = ref<HTMLDivElement>()
let chart: ECharts | undefined
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const categoryOptions = [
  { label: '服务器', value: '服务器' },
  { label: '号池', value: '号池' },
  { label: '上游', value: '上游' },
  { label: '返点', value: '返点' },
  { label: '其他', value: '其他' },
]
const filters = reactive({
  category: '',
  dateRange: null as [Dayjs, Dayjs] | null,
  operator: undefined as number | undefined,
  minAmount: '',
  maxAmount: '',
  keyword: '',
})
const form = reactive({ category: '', customCategory: '', amount: '', paid_at: dayjs().format('YYYY-MM-DD'), remark: '', content_html: '' })
const isCustomCategory = computed(() => form.category === '其他')
const finalCategory = computed(() => (isCustomCategory.value ? form.customCategory : form.category))
const allColumns = [
  { title: '单号', dataIndex: 'expense_no', width: 220 },
  { title: '分类', dataIndex: 'category', width: 120 },
  { title: '金额', dataIndex: 'amount', align: 'right', width: 120 },
  { title: '发生日期', dataIndex: 'paid_at', width: 120 },
  { title: '操作人', dataIndex: 'operator_name', width: 140 },
  { title: '备注', dataIndex: 'remark' },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
  { title: '操作', dataIndex: 'action', fixed: 'right', width: 90 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resetColumns } = useTableColumns('operation-expense-columns', allColumns, 1260)

async function loadItems() {
  loading.value = true
  try {
    const res = await getOperationExpenses({
      page: page.current,
      page_size: page.pageSize,
      category: filters.category,
      from: filters.dateRange?.[0]?.format('YYYY-MM-DD'),
      to: filters.dateRange?.[1]?.format('YYYY-MM-DD'),
      created_by: filters.operator,
      min_amount: filters.minAmount,
      max_amount: filters.maxAmount,
      keyword: filters.keyword,
    })
    items.value = res.items
    categories.value = res.categories
    page.total = res.total
    Object.assign(summary, res.summary)
    await nextTick()
    renderChart()
  } catch {
    message.error('读取支出失败')
  } finally {
    loading.value = false
  }
}

function renderChart() {
  if (!chartEl.value || categories.value.length === 0) {
    chart?.dispose()
    chart = undefined
    return
  }
  chart ||= init(chartEl.value)
  const total = Number(summary.amount_total || 0)
  chart.setOption({
    grid: { left: 90, right: 30, top: 15, bottom: categories.value.length > 8 ? 45 : 20 },
    tooltip: {
      trigger: 'axis', axisPointer: { type: 'shadow' },
      formatter: (rows: any[]) => {
        const i = rows[0].dataIndex
        const row = categories.value[i]
        const rate = total > 0 ? (Number(row.amount_total) / total * 100).toFixed(2) : '0.00'
        return `${row.category}<br/>金额：${signedExpense(row.amount_total)}<br/>笔数：${row.record_count}<br/>占比：${rate}%`
      },
    },
    xAxis: { type: 'value' },
    yAxis: { type: 'category', inverse: true, data: categories.value.map(row => row.category) },
    dataZoom: categories.value.length > 8 ? [{ type: 'slider', yAxisIndex: 0, start: 0, end: 60 }] : [],
    series: [{ type: 'bar', data: categories.value.map(row => Number(row.amount_total)), itemStyle: { color: '#e85d3f' }, barMaxWidth: 28 }],
  }, true)
}
function resizeChart() { chart?.resize() }
function search() { page.current = 1; loadItems() }
function resetFilters() {
  filters.category = ''
  filters.dateRange = null
  filters.operator = undefined
  filters.minAmount = ''
  filters.maxAmount = ''
  filters.keyword = ''
  search()
}
function change(pager: TablePaginationConfig) { page.current = pager.current || 1; page.pageSize = pager.pageSize || 20; loadItems() }
function openCreate() {
  Object.assign(form, { category: '', customCategory: '', amount: '', paid_at: dayjs().format('YYYY-MM-DD'), remark: '', content_html: '' })
  modalOpen.value = true
}
async function submit() {
  if (!finalCategory.value) return void message.warning('请选择或填写分类')
  if (!form.amount) return void message.warning('请填写金额')
  submitting.value = true
  try {
    const res = await createOperationExpense({ category: finalCategory.value, amount: form.amount, paid_at: form.paid_at, remark: form.remark, content_html: form.content_html })
    message.success(res.message)
    modalOpen.value = false
    selected.value = res.expense
    drawerOpen.value = true
    loadItems()
  } catch { message.error('保存支出失败') } finally { submitting.value = false }
}
function openDetail(row: OperationExpense) { selected.value = row; drawerOpen.value = true }
function signedExpense(value: string | number) {
  const amount = Number(value || 0)
  return amount === 0 ? '0.00' : `-${Math.abs(amount).toFixed(2)}`
}

onMounted(() => { loadItems(); window.addEventListener('resize', resizeChart) })
onBeforeUnmount(() => { window.removeEventListener('resize', resizeChart); chart?.dispose() })
</script>

<template>
  <section class="page">
    <div class="pageHead pageHeadActionsOnly">
      <a-button type="primary" @click="openCreate">新增支出</a-button>
    </div>

    <div class="expenseFilterBar">
      <a-select v-model:value="filters.category" class="filterSm" placeholder="全部分类" allow-clear>
        <a-select-option value="">全部分类</a-select-option>
        <a-select-option v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</a-select-option>
      </a-select>
      <a-range-picker v-model:value="filters.dateRange" class="filterDate" :placeholder="['开始日期', '结束日期']" format="YYYY-MM-DD" />
      <a-select v-model:value="filters.operator" class="filterLg" placeholder="操作人" allow-clear>
        <a-select-option v-for="row in adminOptions" :key="row.id" :value="row.id">{{ row.name }}（{{ row.email }}）</a-select-option>
      </a-select>
      <a-input v-model:value="filters.minAmount" class="filterAmount" placeholder="最小金额" allow-clear />
      <a-input v-model:value="filters.maxAmount" class="filterAmount" placeholder="最大金额" allow-clear />
      <a-input v-model:value="filters.keyword" class="filterGrow" placeholder="备注关键词" allow-clear @press-enter="search" />
      <a-button type="primary" @click="search">查询</a-button>
      <a-button @click="resetFilters">重置</a-button>
    </div>

    <div class="summaryGrid">
      <section><span>支出合计</span><strong class="money expenseMoney">{{ signedExpense(summary.amount_total) }}</strong></section>
      <section><span>支出笔数</span><strong>{{ summary.record_count }}</strong></section>
      <section><span>分类数</span><strong>{{ summary.category_count }}</strong></section>
      <section><span>最大单笔支出</span><strong class="money expenseMoney">{{ signedExpense(summary.max_amount) }}</strong></section>
      <section v-if="summary.daily_average !== null"><span>日均支出</span><strong class="money expenseMoney">{{ signedExpense(summary.daily_average) }}</strong></section>
    </div>

    <div v-if="categories.length" class="chartCard"><h3>支出分类分布</h3><div ref="chartEl" class="categoryChart"></div></div>
    <a-empty v-else-if="!loading" description="当前筛选条件下暂无分类统计" />

    <div class="tableTools"><ColumnSettings v-model:value="visibleCols" v-model:width="tableWidth" :options="colOptions" @reset="resetColumns" /></div>
    <a-table row-key="id" :columns="columns" :data-source="items" :loading="loading" :pagination="page" :scroll="{ x: tableWidth }" :locale="{ emptyText: '暂无支出记录' }" @change="change">
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'amount'"><span class="money expenseMoney">{{ signedExpense(record.amount) }}</span></template>
        <template v-else-if="column.dataIndex === 'operator_name'">{{ record.operator_name || record.operator_email || '-' }}</template>
        <template v-else-if="column.dataIndex === 'action'"><a-button size="small" @click="openDetail(record)">详情</a-button></template>
      </template>
    </a-table>

    <a-modal v-model:open="modalOpen" title="新增支出" :confirm-loading="submitting" ok-text="保存" cancel-text="取消" @ok="submit">
      <a-form layout="vertical">
        <a-form-item label="分类" required><a-select v-model:value="form.category" placeholder="请选择分类"><a-select-option v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</a-select-option></a-select></a-form-item>
        <a-form-item v-if="isCustomCategory" label="自定义分类" required><a-input v-model:value="form.customCategory" placeholder="请填写分类名称" /></a-form-item>
        <a-form-item label="金额" required><a-input v-model:value="form.amount" placeholder="0.00" /></a-form-item>
        <a-form-item label="发生日期" required><a-date-picker v-model:value="form.paid_at" value-format="YYYY-MM-DD" class="fullField" /></a-form-item>
        <a-form-item label="备注"><a-input v-model:value="form.remark" /></a-form-item>
        <a-form-item label="说明"><SafeRichTextEditor v-model:value="form.content_html" /></a-form-item>
      </a-form>
    </a-modal>

    <a-drawer v-model:open="drawerOpen" title="支出详情" width="520">
      <a-descriptions v-if="selected" :column="1" bordered size="small">
        <a-descriptions-item label="单号">{{ selected.expense_no }}</a-descriptions-item>
        <a-descriptions-item label="分类">{{ selected.category }}</a-descriptions-item>
        <a-descriptions-item label="金额"><span class="money expenseMoney">{{ signedExpense(selected.amount) }}</span></a-descriptions-item>
        <a-descriptions-item label="日期">{{ selected.paid_at }}</a-descriptions-item>
        <a-descriptions-item label="操作人">{{ selected.operator_name || selected.operator_email || '-' }}</a-descriptions-item>
        <a-descriptions-item label="备注">{{ selected.remark || '-' }}</a-descriptions-item>
      </a-descriptions>
      <SafeRichTextDisplay v-if="selected?.content_html" :value="selected.content_html" />
      <div class="drawerBlock"><h3>附件</h3><AttachmentUploader attachable-type="operation_expense" :attachable-id="selected?.id || null" /></div>
    </a-drawer>
  </section>
</template>

<style scoped>
.expenseFilterBar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
.filterSm { flex: 0 0 150px; }.filterAmount { flex: 0 0 130px; }.filterLg { flex: 0 0 220px; }.filterDate { flex: 0 0 250px; }.filterGrow { flex: 1 1 200px; max-width: 320px; }
.summaryGrid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.summaryGrid section, .chartCard { padding: 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 12px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; color: var(--text-secondary, #7a8395); margin-bottom: 6px; font-size: 13px; }
.summaryGrid strong { font-size: 23px; }
.expenseMoney { color: #cf1322; }
.chartCard { margin-bottom: 14px; }
.chartCard h3 { margin: 0 0 8px; }
.categoryChart { height: 320px; }
@media (max-width: 760px) { .expenseFilterBar > * { flex: 1 1 100%; width: 100% !important; max-width: none; } .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
</style>
