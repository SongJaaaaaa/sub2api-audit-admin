<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import dayjs from 'dayjs'
import type { Dayjs } from 'dayjs'
import { onMounted, reactive, ref } from 'vue'
import {
  getLedgerAdjustments,
  type LedgerAdjustment,
  type LedgerSummary,
} from '../api/ledger'
import SafeRichTextDisplay from '../components/richtext/SafeRichTextDisplay.vue'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useAdminOptions } from '../composables/useAdminOptions'
import { useTableColumns } from '../composables/useTableColumns'

const adminOptions = useAdminOptions()
const loading = ref(false)
const items = ref<LedgerAdjustment[]>([])
const detailOpen = ref(false)
const detail = ref<LedgerAdjustment | null>(null)
const email = ref('')
const operator = ref<number | undefined>()
const dates = ref<[Dayjs, Dayjs]>([dayjs(), dayjs()])
const dateMode = ref<'day' | 'week' | 'month' | ''>('day')
const summary = reactive<LedgerSummary>({ record_count: 0, user_count: 0, increment_total: '0.00', decrement_total: '0.00', net_total: '0.00', cash_total: '0.00', gift_total: '0.00' })
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const allColumns = [
  { title: '业务单号', dataIndex: 'ledger_no', width: 180 },
  { title: '用户ID', dataIndex: 'sub2api_user_id', width: 100 },
  { title: '邮箱', dataIndex: 'sub2api_user_email', width: 220 },
  { title: '操作人', dataIndex: 'operator_name', width: 180 },
  { title: '方向', dataIndex: 'operation', width: 90 },
  { title: '额度', dataIndex: 'amount', align: 'right', width: 120 },
  { title: '现金', dataIndex: 'cash_amount', align: 'right', width: 120 },
  { title: '赠送', dataIndex: 'gift_quota_amount', align: 'right', width: 120 },
  { title: '调前', dataIndex: 'before_balance', align: 'right', width: 120 },
  { title: '调后', dataIndex: 'after_balance', align: 'right', width: 120 },
  { title: '原因', dataIndex: 'adjust_reason', width: 120 },
  { title: '确认时间', dataIndex: 'confirmed_at', width: 180 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resizeColumn, resetColumns } = useTableColumns('ledger-adjustment-columns', allColumns, 1580)

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
    const res = await getLedgerAdjustments({
      page: page.current,
      page_size: page.pageSize,
      status: 'succeeded',
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
  dateMode.value = 'day'
  dates.value = [dayjs(), dayjs()]
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

function rowProps(row: LedgerAdjustment) {
  return {
    class: 'clickableRow',
    onClick: () => {
      detail.value = row
      detailOpen.value = true
    },
  }
}

function signedMoney(value: string | number, mode: 'positive' | 'negative' | 'auto') {
  const amount = Number(value || 0)
  if (amount === 0) return '0.00'
  const sign = mode === 'negative' ? '-' : mode === 'positive' ? '+' : amount > 0 ? '+' : '-'
  return `${sign}${Math.abs(amount).toFixed(2)}`
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
      <section><span>现金入账</span><strong class="positive">{{ signedMoney(summary.cash_total, 'positive') }}</strong></section>
      <section><span>赠送额度</span><strong class="positive">{{ signedMoney(summary.gift_total, 'positive') }}</strong></section>
      <section><span>净调整金额</span><strong :class="Number(summary.net_total) > 0 ? 'positive' : Number(summary.net_total) < 0 ? 'negative' : ''">{{ signedMoney(summary.net_total, 'auto') }}</strong></section>
      <section><span>调减金额</span><strong class="negative">{{ signedMoney(summary.decrement_total, 'negative') }}</strong></section>
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
        <template v-else-if="column.dataIndex === 'operation'">
          <a-tag :color="record.operation === 'increment' ? 'green' : 'orange'">{{ record.operation === 'increment' ? '增加' : '扣减' }}</a-tag>
        </template>
        <template v-else-if="column.dataIndex === 'amount'">
          <span class="money" :class="record.operation === 'increment' ? 'positive' : 'negative'">{{ signedMoney(record.amount, record.operation === 'increment' ? 'positive' : 'negative') }}</span>
        </template>
        <template v-else-if="['cash_amount', 'gift_quota_amount'].includes(column.dataIndex as string)">
          <span class="money positive">{{ signedMoney(record[column.dataIndex], 'positive') }}</span>
        </template>
        <template v-else-if="['before_balance', 'after_balance'].includes(column.dataIndex as string)">
          <span class="money">{{ record[column.dataIndex] || '-' }}</span>
        </template>
      </template>
    </a-table>

    <a-drawer v-model:open="detailOpen" title="收入详情" width="640">
      <a-descriptions v-if="detail" :column="1" bordered size="small">
        <a-descriptions-item label="业务单号">{{ detail.ledger_no }}</a-descriptions-item>
        <a-descriptions-item label="用户">{{ detail.sub2api_user_email || `用户 #${detail.sub2api_user_id}` }}</a-descriptions-item>
        <a-descriptions-item label="操作人">{{ detail.operator_name || detail.operator_email || '-' }}</a-descriptions-item>
        <a-descriptions-item label="方向">
          <a-tag :color="detail.operation === 'increment' ? 'green' : 'orange'">{{ detail.operation === 'increment' ? '增加' : '扣减' }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item label="额度"><strong class="money" :class="detail.operation === 'increment' ? 'positive' : 'negative'">{{ signedMoney(detail.amount, detail.operation === 'increment' ? 'positive' : 'negative') }}</strong></a-descriptions-item>
        <a-descriptions-item label="现金"><span class="positive">{{ signedMoney(detail.cash_amount, 'positive') }}</span></a-descriptions-item>
        <a-descriptions-item label="赠送"><span class="positive">{{ signedMoney(detail.gift_quota_amount, 'positive') }}</span></a-descriptions-item>
        <a-descriptions-item label="调前">{{ detail.before_balance || '-' }}</a-descriptions-item>
        <a-descriptions-item label="调后">{{ detail.after_balance || '-' }}</a-descriptions-item>
        <a-descriptions-item label="原因">{{ detail.adjust_reason || '-' }}</a-descriptions-item>
        <a-descriptions-item label="管理员备注"><SafeRichTextDisplay :value="detail.admin_notes" empty-text="-" compact /></a-descriptions-item>
        <a-descriptions-item label="Sub2API 备注">{{ detail.sub2api_notes || '-' }}</a-descriptions-item>
        <a-descriptions-item label="确认时间">{{ detail.confirmed_at || '-' }}</a-descriptions-item>
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
.negative { color: #cf1322; }
@media (max-width: 700px) {
  .filterItem, .dateFilter { width: 100%; }
  .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .summaryGrid section { padding: 12px 14px; }
  .statsHead { align-items: flex-start; flex-direction: column; }
}
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
</style>
