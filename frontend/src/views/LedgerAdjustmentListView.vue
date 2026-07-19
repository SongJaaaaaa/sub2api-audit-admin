<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import dayjs from 'dayjs'
import type { Dayjs } from 'dayjs'
import { onMounted, reactive, ref } from 'vue'
import {
  getCashEntries,
  type CashEntry,
  type FinanceSummary,
} from '../api/finance'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useAdminOptions } from '../composables/useAdminOptions'
import { useTableColumns } from '../composables/useTableColumns'

const adminOptions = useAdminOptions()
const loading = ref(false)
const items = ref<CashEntry[]>([])
const detailOpen = ref(false)
const detail = ref<CashEntry | null>(null)
const email = ref('')
const operator = ref<number | undefined>()
const dates = ref<[Dayjs, Dayjs] | null>([dayjs(), dayjs()])
const dateMode = ref<'day' | 'week' | 'month' | ''>('day')
const summary = reactive<FinanceSummary>({ record_count: 0, user_count: 0, amount_total: '0.00', linked_count: 0, unlinked_count: 0 })
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const allColumns = [
  { title: '收入单号', dataIndex: 'entry_no', width: 180 },
  { title: '用户ID', dataIndex: 'sub2api_user_id', width: 100 },
  { title: '邮箱', dataIndex: 'sub2api_user_email', width: 220 },
  { title: '操作人', dataIndex: 'operator_name', width: 180 },
  { title: '收入金额', dataIndex: 'cash_amount', align: 'right', width: 130 },
  { title: '来源', dataIndex: 'source', width: 160 },
  { title: '备注', dataIndex: 'remark', width: 180 },
  { title: '记录时间', dataIndex: 'created_at', width: 180 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resizeColumn, resetColumns } = useTableColumns('income-entry-columns', allColumns, 1250)

function filterParams() {
  return {
    sub2api_user_email: email.value,
    created_by: operator.value,
    start_date: dates.value?.[0].format('YYYY-MM-DD'),
    end_date: dates.value?.[1].format('YYYY-MM-DD'),
  }
}

async function loadItems() {
  loading.value = true
  try {
    const res = await getCashEntries({
      page: page.current,
      page_size: page.pageSize,
      ...filterParams(),
    })
    items.value = res.items
    page.total = res.total
    Object.assign(summary, res.summary)
  } catch {
    message.error('读取收入记录失败')
  } finally {
    loading.value = false
  }
}

function search() {
  page.current = 1
  loadItems()
}

function resetFilters() {
  email.value = ''
  operator.value = undefined
  dateMode.value = ''
  dates.value = null
  search()
}

function change(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  loadItems()
}

function changeDateMode(mode: 'day' | 'week' | 'month') {
  dateMode.value = mode
  const now = dayjs()
  const start = mode === 'week' ? now.startOf('week') : mode === 'month' ? now.startOf('month') : now
  dates.value = [start, now]
  search()
}

function changeDates() {
  dateMode.value = ''
}

function rowProps(row: CashEntry) {
  return {
    class: 'clickableRow',
    onClick: () => {
      detail.value = row
      detailOpen.value = true
    },
  }
}

function signedMoney(value: string | number) {
  const amount = Number(value || 0)
  if (amount === 0) return '0.00'
  return `+${Math.abs(amount).toFixed(2)}`
}

function sourceLabel(source: string) {
  return source === 'sub2api_external_adjustment' ? 'Sub2API 外部调整' : '本系统入账'
}

onMounted(loadItems)
</script>

<template>
  <section class="page">
    <div class="pageHead ledgerHead pageHeadActionsOnly">
      <ColumnSettings v-model:value="visibleCols" v-model:width="tableWidth" :options="colOptions" @reset="resetColumns" />
    </div>

    <div class="filterBar">
      <a-input v-model:value="email" class="filterItem" placeholder="用户邮箱" allow-clear @press-enter="search" />
      <a-select
        v-model:value="operator"
        class="filterItem"
        placeholder="操作人"
        :options="adminOptions.map(row => ({ label: `${row.name}（${row.email}）`, value: row.id }))"
        allow-clear
      />
      <a-segmented
        :value="dateMode"
        :options="[{ label: '今日', value: 'day' }, { label: '本周', value: 'week' }, { label: '本月', value: 'month' }]"
        @change="changeDateMode"
      />
      <a-range-picker v-model:value="dates" class="dateFilter" @change="changeDates" />
      <a-button type="primary" @click="search">查询</a-button>
      <a-button @click="resetFilters">重置</a-button>
    </div>

    <div class="summaryGrid">
      <section><span>收入合计</span><strong class="positive">{{ signedMoney(summary.amount_total) }}</strong></section>
      <section><span>收入笔数</span><strong>{{ summary.record_count }}</strong></section>
      <section><span>收入用户</span><strong>{{ summary.user_count }}</strong></section>
      <section><span>Sub2API 外部调整</span><strong>{{ summary.unlinked_count }}</strong></section>
    </div>

    <a-table
      row-key="id"
      :custom-row="rowProps"
      :columns="columns"
      :data-source="items"
      :loading="loading"
      :locale="{ emptyText: '暂无符合条件的收入记录' }"
      :pagination="page"
      :scroll="{ x: tableWidth }"
      @resize-column="resizeColumn"
      @change="change"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'operator_name'">
          <span>{{ record.operator_name || record.operator_email || '-' }}</span>
        </template>
        <template v-else-if="column.dataIndex === 'cash_amount'"><span class="money positive">{{ signedMoney(record.cash_amount) }}</span></template>
        <template v-else-if="column.dataIndex === 'source'"><a-tag :color="record.source === 'sub2api_external_adjustment' ? 'blue' : 'green'">{{ sourceLabel(record.source) }}</a-tag></template>
        <template v-else-if="column.dataIndex === 'remark'">{{ record.remark || '-' }}</template>
      </template>
    </a-table>

    <a-drawer v-model:open="detailOpen" title="收入详情" width="640">
      <a-descriptions v-if="detail" :column="1" bordered size="small">
        <a-descriptions-item label="收入单号">{{ detail.entry_no }}</a-descriptions-item>
        <a-descriptions-item label="用户">{{ detail.sub2api_user_email || `用户 #${detail.sub2api_user_id}` }}</a-descriptions-item>
        <a-descriptions-item label="操作人">{{ detail.operator_name || detail.operator_email || '-' }}</a-descriptions-item>
        <a-descriptions-item label="收入金额"><strong class="positive">{{ signedMoney(detail.cash_amount) }}</strong></a-descriptions-item>
        <a-descriptions-item label="来源">{{ sourceLabel(detail.source) }}</a-descriptions-item>
        <a-descriptions-item label="备注">{{ detail.remark || '-' }}</a-descriptions-item>
        <a-descriptions-item label="记录时间">{{ detail.created_at || '-' }}</a-descriptions-item>
      </a-descriptions>
    </a-drawer>
  </section>
</template>

<style scoped>
.ledgerHead { align-items: flex-start; }
:deep(.clickableRow) { cursor: pointer; }
:deep(.clickableRow:hover) > td { background: rgba(22, 119, 255, .06) !important; }
.filterBar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
.filterItem { width: 220px; }
.dateFilter { width: 260px; }
.summaryGrid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
.summaryGrid section { padding: 16px 18px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 8px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; margin-bottom: 6px; color: var(--text-secondary, #7a8395); font-size: 13px; }
.summaryGrid strong { font-size: 24px; }
.positive { color: #389e0d; }
@media (max-width: 700px) {
  .filterItem, .dateFilter { width: 100%; }
  .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .summaryGrid section { padding: 12px 14px; }
  .statsHead { align-items: flex-start; flex-direction: column; }
}
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
</style>
