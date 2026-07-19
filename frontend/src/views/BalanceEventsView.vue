<script setup lang="ts">
import { DownloadOutlined, ReloadOutlined, SearchOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import type { Dayjs } from 'dayjs'
import { onMounted, reactive, ref } from 'vue'
import {
  exportFinanceHistory,
  getFinanceHistory,
  type FinanceHistoryItem,
  type FinanceHistoryParams,
  type FinanceHistorySummary,
  type FinanceHistoryType,
} from '../api/finance'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useAdminOptions } from '../composables/useAdminOptions'
import { useTableColumns } from '../composables/useTableColumns'

const adminOptions = useAdminOptions()
const loading = ref(false)
const exporting = ref(false)
const items = ref<FinanceHistoryItem[]>([])
const dateRange = ref<[Dayjs, Dayjs] | null>(null)
const detailOpen = ref(false)
const detail = ref<FinanceHistoryItem | null>(null)
const type = ref<FinanceHistoryType | undefined>()
const userId = ref('')
const keyword = ref('')
const operator = ref<number | undefined>()
const page = reactive({ current: 1, pageSize: 20, total: 0, showSizeChanger: true })
const summary = reactive<FinanceHistorySummary>({
  record_count: 0,
  income_count: 0,
  expense_count: 0,
  gift_count: 0,
  income_total: '0.00',
  expense_total: '0.00',
  gift_total: '0.00',
})

const allColumns = [
  { title: '业务日期', dataIndex: 'biz_date', width: 120, fixed: 'left' },
  { title: '类型', dataIndex: 'type', width: 90 },
  { title: '账单号', dataIndex: 'bill_no', width: 200 },
  { title: '用户', dataIndex: 'user', width: 230 },
  { title: '分类', dataIndex: 'category', width: 120 },
  { title: '金额', dataIndex: 'amount', width: 130, align: 'right' },
  { title: '操作人', dataIndex: 'operator', width: 150 },
  { title: '备注', dataIndex: 'remark', width: 220 },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resetColumns } = useTableColumns('finance-history-columns', allColumns, 1440)

function params(withPage = true): FinanceHistoryParams {
  const val: FinanceHistoryParams = {
    type: type.value,
    start_date: dateRange.value?.[0].format('YYYY-MM-DD'),
    end_date: dateRange.value?.[1].format('YYYY-MM-DD'),
    sub2api_user_id: userId.value.trim() || undefined,
    created_by: operator.value,
    keyword: keyword.value.trim() || undefined,
  }
  if (withPage) {
    val.page = page.current
    val.page_size = page.pageSize
  }
  return val
}

async function loadItems(reset = false) {
  if (reset) page.current = 1
  loading.value = true
  try {
    const res = await getFinanceHistory(params())
    items.value = res.items
    page.total = res.total
    page.current = res.page
    page.pageSize = res.page_size
    Object.assign(summary, res.summary)
  } catch (err) {
    message.error(apiMessage(err, '读取历史账失败'))
  } finally {
    loading.value = false
  }
}

async function downloadCsv() {
  exporting.value = true
  try {
    const blob = await exportFinanceHistory(params(false))
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    const start = dateRange.value?.[0].format('YYYY-MM-DD') || 'all'
    const end = dateRange.value?.[1].format('YYYY-MM-DD') || 'all'
    link.href = url
    link.download = `finance-history-${start}-${end}.csv`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch (err) {
    message.error(apiMessage(err, '导出历史账失败'))
  } finally {
    exporting.value = false
  }
}

function resetFilters() {
  dateRange.value = null
  type.value = undefined
  userId.value = ''
  keyword.value = ''
  operator.value = undefined
  loadItems(true)
}

function changePage(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  loadItems()
}

function rowKey(row: FinanceHistoryItem) {
  return `${row.type}-${row.source_id}`
}

function rowProps(row: FinanceHistoryItem) {
  return {
    class: 'clickableRow',
    onClick: () => {
      detail.value = row
      detailOpen.value = true
    },
  }
}

function typeMeta(value: FinanceHistoryType) {
  return {
    income: { text: '收入', color: 'green' },
    expense: { text: '支出', color: 'red' },
    gift: { text: '赠送', color: 'blue' },
  }[value]
}

function signedMoney(value: string | number, valueType: FinanceHistoryType) {
  const amount = Number(value || 0)
  if (amount === 0) return '0.00'
  return `${valueType === 'expense' ? '-' : '+'}${Math.abs(amount).toFixed(2)}`
}

function apiMessage(err: unknown, fallback: string) {
  return (err as { response?: { data?: { message?: string } } }).response?.data?.message || fallback
}

onMounted(() => loadItems())
</script>

<template>
  <section class="page historyPage">
    <div class="pageHead pageHeadActionsOnly">
      <div class="headActions">
        <a-button :loading="exporting" @click="downloadCsv"><template #icon><DownloadOutlined /></template>导出当前筛选 CSV</a-button>
        <a-button :loading="loading" @click="loadItems()"><template #icon><ReloadOutlined /></template></a-button>
      </div>
    </div>

    <section class="filterPanel">
      <div class="filterGrid">
        <label class="filterDate"><span>时间</span><a-range-picker v-model:value="dateRange" /></label>
        <label class="filterType">
          <span>类型</span>
          <a-select v-model:value="type" allow-clear placeholder="全部类型">
            <a-select-option value="income">收入</a-select-option>
            <a-select-option value="expense">支出</a-select-option>
            <a-select-option value="gift">赠送</a-select-option>
          </a-select>
        </label>
        <label class="filterId"><span>用户 ID</span><a-input v-model:value="userId" allow-clear placeholder="精确用户 ID" @press-enter="loadItems(true)" /></label>
        <label class="filterGrow"><span>关键词</span><a-input v-model:value="keyword" allow-clear placeholder="账单号 / 邮箱 / 分类 / 备注" @press-enter="loadItems(true)" /></label>
        <label class="filterOperator">
          <span>操作人</span>
          <a-select v-model:value="operator" allow-clear placeholder="全部操作人" :options="adminOptions.map(row => ({ label: `${row.name}（${row.email}）`, value: row.id }))" />
        </label>
        <div class="filterActions">
          <a-button type="primary" :loading="loading" @click="loadItems(true)"><template #icon><SearchOutlined /></template>查询</a-button>
          <a-button @click="resetFilters">重置</a-button>
        </div>
      </div>
    </section>

    <div class="summaryGrid">
      <section><span>收入 · {{ summary.income_count }} 笔</span><strong class="income">{{ signedMoney(summary.income_total, 'income') }}</strong></section>
      <section><span>支出 · {{ summary.expense_count }} 笔</span><strong class="expense">{{ signedMoney(summary.expense_total, 'expense') }}</strong></section>
      <section><span>赠送 · {{ summary.gift_count }} 笔</span><strong class="gift">{{ signedMoney(summary.gift_total, 'gift') }}</strong></section>
      <section><span>全部账单</span><strong>{{ summary.record_count }}</strong></section>
    </div>

    <div class="tableTools"><ColumnSettings v-model:value="visibleCols" v-model:width="tableWidth" :options="colOptions" @reset="resetColumns" /></div>
    <a-table
      :row-key="rowKey"
      :custom-row="rowProps"
      :columns="columns"
      :data-source="items"
      :loading="loading"
      :pagination="page"
      :scroll="{ x: tableWidth }"
      :locale="{ emptyText: '当前筛选范围暂无账单' }"
      @change="changePage"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'type'"><a-tag :color="typeMeta(record.type).color">{{ typeMeta(record.type).text }}</a-tag></template>
        <template v-else-if="column.dataIndex === 'user'">
          <template v-if="record.sub2api_user_id"><strong>{{ record.sub2api_user_email || `用户 #${record.sub2api_user_id}` }}</strong><small>ID：{{ record.sub2api_user_id }}</small></template>
          <span v-else>-</span>
        </template>
        <template v-else-if="column.dataIndex === 'category'">{{ record.category || '-' }}</template>
        <template v-else-if="column.dataIndex === 'amount'"><span class="money" :class="record.type">{{ signedMoney(record.amount, record.type) }}</span></template>
        <template v-else-if="column.dataIndex === 'operator'">{{ record.operator_name || record.operator_email || '-' }}</template>
        <template v-else-if="column.dataIndex === 'remark'">{{ record.remark || '-' }}</template>
      </template>
    </a-table>

    <a-drawer v-model:open="detailOpen" title="账单详情" width="560">
      <a-descriptions v-if="detail" :column="1" bordered size="small">
        <a-descriptions-item label="业务日期">{{ detail.biz_date }}</a-descriptions-item>
        <a-descriptions-item label="类型"><a-tag :color="typeMeta(detail.type).color">{{ typeMeta(detail.type).text }}</a-tag></a-descriptions-item>
        <a-descriptions-item label="账单号">{{ detail.bill_no }}</a-descriptions-item>
        <a-descriptions-item label="用户">{{ detail.sub2api_user_email || (detail.sub2api_user_id ? `用户 #${detail.sub2api_user_id}` : '-') }}</a-descriptions-item>
        <a-descriptions-item label="分类">{{ detail.category || '-' }}</a-descriptions-item>
        <a-descriptions-item label="金额"><strong class="money" :class="detail.type">{{ signedMoney(detail.amount, detail.type) }}</strong></a-descriptions-item>
        <a-descriptions-item label="操作人">{{ detail.operator_name || detail.operator_email || '-' }}</a-descriptions-item>
        <a-descriptions-item label="备注">{{ detail.remark || '-' }}</a-descriptions-item>
        <a-descriptions-item label="创建时间">{{ detail.created_at || '-' }}</a-descriptions-item>
      </a-descriptions>
    </a-drawer>
  </section>
</template>

<style scoped>
.historyPage { display: grid; gap: 16px; }
:deep(.clickableRow) { cursor: pointer; }
:deep(.clickableRow:hover) > td { background: rgba(22, 119, 255, .06) !important; }
.filterPanel { padding: 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 8px; background: var(--card-bg, #fff); }
.filterGrid { display: flex; flex-wrap: wrap; gap: 14px; align-items: end; }
.filterGrid label { display: grid; gap: 6px; min-width: 0; }
.filterGrid label > span { color: var(--text-secondary, #70798c); font-size: 12px; }
.filterGrid label :deep(.ant-picker), .filterGrid label :deep(.ant-select) { width: 100%; }
.filterDate { flex: 0 0 250px; }
.filterType { flex: 0 0 130px; }
.filterId { flex: 0 0 130px; }
.filterGrow { flex: 1 1 230px; max-width: 340px; }
.filterOperator { flex: 0 0 220px; }
.filterActions { display: flex; flex: 0 0 auto; gap: 9px; }
.summaryGrid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.summaryGrid section { padding: 14px 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 8px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; margin-bottom: 5px; color: var(--text-secondary, #70798c); font-size: 13px; }
.summaryGrid strong { font-size: 22px; font-variant-numeric: tabular-nums; }
.money { font-weight: 700; font-variant-numeric: tabular-nums; }
.income { color: #389e0d; }
.expense { color: #cf1322; }
.gift { color: #1677ff; }
small { display: block; margin-top: 3px; color: var(--text-secondary, #7a8395); }
@media (max-width: 760px) {
  .filterGrid label { flex: 1 1 100%; max-width: none; }
  .filterActions, .filterActions button { flex: 1; }
  .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
</style>
