<script setup lang="ts">
import { PlusOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import dayjs, { type Dayjs } from 'dayjs'
import { onMounted, reactive, ref } from 'vue'
import { createOperationExpense, getOperationExpenses, type ExpenseSummary, type OperationExpense } from '../api/finance'
import AttachmentUploader from '../components/attachments/AttachmentUploader.vue'
import SafeRichTextDisplay from '../components/richtext/SafeRichTextDisplay.vue'
import SafeRichTextEditor from '../components/richtext/SafeRichTextEditor.vue'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useAdminOptions } from '../composables/useAdminOptions'
import { useTableColumns } from '../composables/useTableColumns'

const adminOptions = useAdminOptions()
const loading = ref(false)
const submitting = ref(false)
const modalOpen = ref(false)
const drawerOpen = ref(false)
const items = ref<OperationExpense[]>([])
const summary = reactive<ExpenseSummary>({ record_count: 0, category_count: 0, amount_total: '0.00', max_amount: '0.00', daily_average: null })
const selected = ref<OperationExpense | null>(null)
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const filters = reactive({
  dateRange: null as [Dayjs, Dayjs] | null,
  operator: undefined as number | undefined,
  minAmount: '',
  maxAmount: '',
  keyword: '',
})
const form = reactive({ amount: '', paid_at: dayjs().format('YYYY-MM-DD'), content_html: '' })
const allColumns = [
  { title: '单号', dataIndex: 'expense_no', width: 220 },
  { title: '金额', dataIndex: 'amount', align: 'right', width: 120 },
  { title: '发生日期', dataIndex: 'paid_at', width: 120 },
  { title: '操作人', dataIndex: 'operator_name', width: 140 },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resizeColumn, resetColumns } = useTableColumns('operation-expense-columns', allColumns, 800)

async function loadItems() {
  loading.value = true
  try {
    const res = await getOperationExpenses({
      page: page.current,
      page_size: page.pageSize,
      from: filters.dateRange?.[0]?.format('YYYY-MM-DD'),
      to: filters.dateRange?.[1]?.format('YYYY-MM-DD'),
      created_by: filters.operator,
      min_amount: filters.minAmount,
      max_amount: filters.maxAmount,
      keyword: filters.keyword,
    })
    items.value = res.items
    page.total = res.total
    Object.assign(summary, res.summary)
  } catch {
    message.error('读取支出失败')
  } finally {
    loading.value = false
  }
}

function search() { page.current = 1; loadItems() }
function resetFilters() {
  filters.dateRange = null
  filters.operator = undefined
  filters.minAmount = ''
  filters.maxAmount = ''
  filters.keyword = ''
  search()
}
function change(pager: TablePaginationConfig) { page.current = pager.current || 1; page.pageSize = pager.pageSize || 20; loadItems() }
function openCreate() {
  Object.assign(form, { amount: '', paid_at: dayjs().format('YYYY-MM-DD'), content_html: '' })
  modalOpen.value = true
}
async function submit() {
  if (!form.amount) return void message.warning('请填写金额')
  submitting.value = true
  try {
    const res = await createOperationExpense(form)
    message.success(res.message)
    modalOpen.value = false
    selected.value = res.expense
    drawerOpen.value = true
    loadItems()
  } catch { message.error('保存支出失败') } finally { submitting.value = false }
}
function openDetail(row: OperationExpense) { selected.value = row; drawerOpen.value = true }
function rowProps(row: OperationExpense) {
  return { class: 'clickableRow', onClick: () => openDetail(row) }
}
function signedExpense(value: string | number) {
  const amount = Number(value || 0)
  return amount === 0 ? '0.00' : `-${Math.abs(amount).toFixed(2)}`
}

onMounted(loadItems)
</script>

<template>
  <section class="page">
    <div class="expenseFilterBar">
      <a-range-picker v-model:value="filters.dateRange" class="filterDate" :placeholder="['开始日期', '结束日期']" format="YYYY-MM-DD" />
      <a-select v-model:value="filters.operator" class="filterLg" placeholder="操作人" allow-clear>
        <a-select-option v-for="row in adminOptions" :key="row.id" :value="row.id">{{ row.name }}（{{ row.email }}）</a-select-option>
      </a-select>
      <a-input v-model:value="filters.minAmount" class="filterAmount" placeholder="最小金额" allow-clear />
      <a-input v-model:value="filters.maxAmount" class="filterAmount" placeholder="最大金额" allow-clear />
      <a-input v-model:value="filters.keyword" class="filterGrow" placeholder="备注关键词" allow-clear @press-enter="search" />
      <a-button type="primary" @click="search">查询</a-button>
      <a-button @click="resetFilters">重置</a-button>
      <a-button type="primary" @click="openCreate"><template #icon><PlusOutlined /></template>新增支出</a-button>
    </div>

    <div class="summaryGrid">
      <section><span>支出合计</span><strong class="money expenseMoney">{{ signedExpense(summary.amount_total) }}</strong></section>
      <section><span>支出笔数</span><strong>{{ summary.record_count }}</strong></section>
      <section><span>最大单笔支出</span><strong class="money expenseMoney">{{ signedExpense(summary.max_amount) }}</strong></section>
      <div class="summaryTools"><ColumnSettings v-model:value="visibleCols" v-model:width="tableWidth" :options="colOptions" @reset="resetColumns" /></div>
      <section v-if="summary.daily_average !== null"><span>日均支出</span><strong class="money expenseMoney">{{ signedExpense(summary.daily_average) }}</strong></section>
    </div>

    <a-table row-key="id" :custom-row="rowProps" :columns="columns" :data-source="items" :loading="loading" :pagination="page" :scroll="{ x: tableWidth }" :locale="{ emptyText: '暂无支出记录' }" @resize-column="resizeColumn" @change="change">
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'amount'"><span class="money expenseMoney">{{ signedExpense(record.amount) }}</span></template>
        <template v-else-if="column.dataIndex === 'operator_name'">{{ record.operator_name || record.operator_email || '-' }}</template>
      </template>
    </a-table>

    <a-modal v-model:open="modalOpen" title="新增支出" :confirm-loading="submitting" ok-text="保存" cancel-text="取消" @ok="submit">
      <a-form layout="vertical">
        <a-form-item label="金额" required><a-input v-model:value="form.amount" placeholder="0.00" /></a-form-item>
        <a-form-item label="发生日期" required><a-date-picker v-model:value="form.paid_at" value-format="YYYY-MM-DD" class="fullField" /></a-form-item>
        <a-form-item label="备注"><SafeRichTextEditor v-model:value="form.content_html" /></a-form-item>
      </a-form>
    </a-modal>

    <a-drawer v-model:open="drawerOpen" title="支出详情" width="520">
      <a-descriptions v-if="selected" :column="1" bordered size="small">
        <a-descriptions-item label="单号">{{ selected.expense_no }}</a-descriptions-item>
        <a-descriptions-item label="金额"><span class="money expenseMoney">{{ signedExpense(selected.amount) }}</span></a-descriptions-item>
        <a-descriptions-item label="日期">{{ selected.paid_at }}</a-descriptions-item>
        <a-descriptions-item label="操作人">{{ selected.operator_name || selected.operator_email || '-' }}</a-descriptions-item>
      </a-descriptions>
      <div v-if="selected?.content_html" class="detailNotes"><h3>备注</h3><SafeRichTextDisplay :value="selected.content_html" /></div>
      <div class="drawerBlock"><h3>附件</h3><AttachmentUploader attachable-type="operation_expense" :attachable-id="selected?.id || null" /></div>
    </a-drawer>
  </section>
</template>

<style scoped>
.expenseFilterBar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
.filterAmount { flex: 0 0 130px; }.filterLg { flex: 0 0 220px; }.filterDate { flex: 0 0 250px; }.filterGrow { flex: 1 1 200px; max-width: 320px; }
.summaryGrid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.summaryGrid section { padding: 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 8px; background: var(--card-bg, #fff); }
.summaryTools { display: flex; justify-content: flex-end; align-items: flex-start; }
.summaryGrid span { display: block; color: var(--text-secondary, #7a8395); margin-bottom: 6px; font-size: 13px; }
.summaryGrid strong { font-size: 23px; }
.expenseMoney { color: #cf1322; }
.detailNotes { margin-top: 18px; }
.detailNotes h3 { margin: 0 0 8px; }
:deep(.clickableRow) { cursor: pointer; }
:deep(.clickableRow:hover) > td { background: rgba(22, 119, 255, .06) !important; }
@media (max-width: 760px) { .expenseFilterBar > * { flex: 1 1 100%; width: 100% !important; max-width: none; } .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
</style>
