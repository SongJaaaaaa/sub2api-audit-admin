<script setup lang="ts">
import { DownloadOutlined, ReloadOutlined, SearchOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import { onMounted, reactive, ref } from 'vue'
import {
  exportBalanceEvents,
  getBalanceEvents,
  type BalanceEvent,
  type BalanceEventDirection,
  type BalanceEventLinkStatus,
  type BalanceEventParams,
  type BalanceEventPeriod,
  type BalanceEventSource,
  type BalanceEventSummary,
} from '../api/balanceEvents'

import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useTableColumns } from '../composables/useTableColumns'
const loading = ref(false)
const exporting = ref(false)
const items = ref<BalanceEvent[]>([])
const dateRange = ref<[Dayjs, Dayjs] | null>(null)
const cutoverAt = ref<string | null>(null)
const summary = reactive<BalanceEventSummary>({ record_count: 0, user_count: 0, increment_total: '0.00', decrement_total: '0.00', net_total: '0.00', linked_count: 0, external_count: 0, audit_orphan_count: 0, linked_rate: 0 })
const userId = ref('')
const keyword = ref('')
const source = ref<BalanceEventSource | undefined>()
const direction = ref<BalanceEventDirection | undefined>()
const linkStatus = ref<BalanceEventLinkStatus | undefined>()
const period = ref<BalanceEventPeriod>('history')
const page = reactive({ current: 1, pageSize: 20, total: 0, showSizeChanger: true })

const allColumns = [
  { title: '事件时间（中国）', dataIndex: 'event_at', width: 175, fixed: 'left' },
  { title: '来源', dataIndex: 'source', width: 130 },
  { title: '远端事件 ID', dataIndex: 'remote_event_id', width: 125 },
  { title: '用户', dataIndex: 'user', minWidth: 230 },
  { title: '方向', dataIndex: 'direction', width: 90 },
  { title: '金额', dataIndex: 'amount', width: 135, align: 'right' },
  { title: '关联状态', dataIndex: 'link_status', width: 120 },
  { title: '本地单号', dataIndex: 'ledger_no', width: 210 },
  { title: '备注', dataIndex: 'notes', minWidth: 300 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resetColumns } = useTableColumns('balance-events-columns', allColumns, 1650)

function params(withPage = true): BalanceEventParams {
  const val: BalanceEventParams = { period: period.value }

  if (dateRange.value) {
    val.start_date = dateRange.value[0].format('YYYY-MM-DD')
    val.end_date = dateRange.value[1].format('YYYY-MM-DD')
  }
  if (userId.value.trim()) val.user_id = userId.value.trim()
  if (keyword.value.trim()) val.keyword = keyword.value.trim()
  if (source.value) val.source = source.value
  if (direction.value) val.direction = direction.value
  if (linkStatus.value) val.link_status = linkStatus.value
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
    const res = await getBalanceEvents(params())
    items.value = res.items
    page.total = res.total
    page.current = res.page
    page.pageSize = res.page_size
    cutoverAt.value = res.cutover_at
    Object.assign(summary, res.summary)
    dateRange.value = [dayjs(res.range.start_date), dayjs(res.range.end_date)]
  } catch (err) {
    message.error(apiMessage(err, '读取历史账失败'))
  } finally {
    loading.value = false
  }
}

async function downloadCsv() {
  exporting.value = true
  try {
    const blob = await exportBalanceEvents(params(false))
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    const start = dateRange.value?.[0].format('YYYY-MM-DD') || 'default'
    const end = dateRange.value?.[1].format('YYYY-MM-DD') || 'default'
    link.href = url
    link.download = `balance-events-${start}-${end}.csv`
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

function changePeriod(val: BalanceEventPeriod) {
  period.value = val
  dateRange.value = null
  loadItems(true)
}

function resetFilters() {
  dateRange.value = null
  userId.value = ''
  keyword.value = ''
  source.value = undefined
  direction.value = undefined
  linkStatus.value = undefined
  period.value = 'history'
  loadItems(true)
}

function changePage(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  loadItems()
}

function rowKey(row: BalanceEvent) {
  return `${row.source}-${row.remote_event_id}`
}

function sourceText(val: BalanceEventSource) {
  return {
    admin_adjustment: '后台余额调额',
    balance_redeem: '余额兑换码',
    payment_order: '支付订单',
  }[val]
}

function sourceColor(val: BalanceEventSource) {
  return {
    admin_adjustment: 'blue',
    balance_redeem: 'purple',
    payment_order: 'green',
  }[val]
}

function directionMeta(val: BalanceEventDirection) {
  return val === 'increment'
    ? { text: '调增', color: 'green' }
    : { text: '调减', color: 'red' }
}

function linkMeta(val: BalanceEventLinkStatus) {
  return {
    linked: { text: '已关联', color: 'green' },
    audit_orphan: { text: '审计孤儿', color: 'orange' },
    external: { text: '外部事件', color: 'blue' },
  }[val]
}

function periodText(val: BalanceEventPeriod) {
  return {
    history: '切账前历史账',
    current: '切账后当前期',
    all: '全部期间',
  }[val]
}

function formatMoney(val: string | number | null | undefined) {
  return Number(val || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 8 })
}

function apiMessage(err: unknown, fallback: string) {
  return (err as { response?: { data?: { message?: string } } }).response?.data?.message || fallback
}

onMounted(() => loadItems())
</script>

<template>
  <section class="page balanceEventsPage">
    <div class="pageHead">
      <div>
        <h1>历史账</h1>
        <p>只读查看 Sub2API 后台调额、余额兑换码和已实际改变余额的支付订单</p>
      </div>
      <div class="headActions">
        <a-button :loading="exporting" @click="downloadCsv">
          <template #icon><DownloadOutlined /></template>
          导出当前筛选 CSV
        </a-button>
        <a-button :loading="loading" @click="loadItems()">
          <template #icon><ReloadOutlined /></template>
        </a-button>
      </div>
    </div>

    <a-alert
      type="warning"
      show-icon
      message="历史账模块只读"
      description="这里不支持补录、认领、修改或删除，也不会反向影响首页实收入账；affiliate_balance 返利流水暂不纳入。"
    />

    <section class="filterPanel">
      <div class="filterGrid">
        <label>
          <span>期间</span>
          <a-select :value="period" @change="changePeriod">
            <a-select-option value="history">切账前历史账</a-select-option>
            <a-select-option value="current">切账后当前期</a-select-option>
            <a-select-option value="all">全部期间</a-select-option>
          </a-select>
        </label>
        <label class="dateFilter">
          <span>中国自然日（包含首尾）</span>
          <a-range-picker v-model:value="dateRange" />
        </label>
        <label>
          <span>用户 ID</span>
          <a-input v-model:value="userId" allow-clear placeholder="精确用户 ID" @press-enter="loadItems(true)" />
        </label>
        <label>
          <span>用户关键字</span>
          <a-input v-model:value="keyword" allow-clear placeholder="邮箱 / 用户名 / ID" @press-enter="loadItems(true)" />
        </label>
        <label>
          <span>来源</span>
          <a-select v-model:value="source" allow-clear placeholder="全部来源">
            <a-select-option value="admin_adjustment">后台余额调额</a-select-option>
            <a-select-option value="balance_redeem">余额兑换码</a-select-option>
            <a-select-option value="payment_order">支付订单</a-select-option>
          </a-select>
        </label>
        <label>
          <span>方向</span>
          <a-select v-model:value="direction" allow-clear placeholder="全部方向">
            <a-select-option value="increment">调增</a-select-option>
            <a-select-option value="decrement">调减</a-select-option>
          </a-select>
        </label>
        <label>
          <span>关联状态</span>
          <a-select v-model:value="linkStatus" allow-clear placeholder="全部状态">
            <a-select-option value="linked">已关联</a-select-option>
            <a-select-option value="audit_orphan">审计孤儿</a-select-option>
            <a-select-option value="external">外部事件</a-select-option>
          </a-select>
        </label>
        <div class="filterActions">
          <a-button type="primary" :loading="loading" @click="loadItems(true)">
            <template #icon><SearchOutlined /></template>
            查询
          </a-button>
          <a-button @click="resetFilters">重置</a-button>
        </div>
      </div>
      <div class="filterMeta">
        <span>当前期间：{{ periodText(period) }}</span>
        <span>切账时间：{{ cutoverAt || '尚未设置' }}</span>
        <span>共 {{ page.total.toLocaleString('zh-CN') }} 条</span>
      </div>
    </section>

    <div class="summaryGrid">
      <section><span>事件数</span><strong>{{ summary.record_count }}</strong></section>
      <section><span>用户数</span><strong>{{ summary.user_count }}</strong></section>
      <section><span>调增金额</span><strong class="increment">{{ formatMoney(summary.increment_total) }}</strong></section>
      <section><span>调减金额</span><strong class="decrement">{{ formatMoney(summary.decrement_total) }}</strong></section>
      <section><span>净变动</span><strong :class="Number(summary.net_total) >= 0 ? 'increment' : 'decrement'">{{ formatMoney(summary.net_total) }}</strong></section>
      <section><span>已关联数</span><strong>{{ summary.linked_count }}</strong></section>
      <section><span>外部事件数</span><strong>{{ summary.external_count }}</strong></section>
      <section><span>审计孤儿数</span><strong>{{ summary.audit_orphan_count }}</strong></section>
      <section><span>已关联率</span><strong>{{ summary.linked_rate.toFixed(2) }}%</strong></section>
    </div>

    <div class="tableTools">
      <ColumnSettings v-model:value="visibleCols" v-model:width="tableWidth" :options="colOptions" @reset="resetColumns" />
    </div>
    <a-table
      :row-key="rowKey"
      :columns="columns"
      :data-source="items"
      :loading="loading"
      :pagination="page"
      :scroll="{ x: tableWidth }"
      :locale="{ emptyText: '当前筛选范围没有余额事件' }"
      @change="changePage"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'source'">
          <a-tag :color="sourceColor(record.source)">{{ sourceText(record.source) }}</a-tag>
        </template>
        <template v-else-if="column.dataIndex === 'user'">
          <strong>{{ record.user_email || record.username || `用户 #${record.sub2api_user_id}` }}</strong>
          <small>ID：{{ record.sub2api_user_id }}<template v-if="record.username"> · {{ record.username }}</template></small>
        </template>
        <template v-else-if="column.dataIndex === 'direction'">
          <a-tag :color="directionMeta(record.direction).color">{{ directionMeta(record.direction).text }}</a-tag>
        </template>
        <template v-else-if="column.dataIndex === 'amount'">
          <span class="money" :class="record.direction">{{ record.direction === 'decrement' ? '-' : '+' }}{{ formatMoney(record.amount) }}</span>
        </template>
        <template v-else-if="column.dataIndex === 'link_status'">
          <a-tag :color="linkMeta(record.link_status).color">{{ linkMeta(record.link_status).text }}</a-tag>
        </template>
        <template v-else-if="column.dataIndex === 'ledger_no'">
          {{ record.ledger_no || '-' }}
        </template>
        <template v-else-if="column.dataIndex === 'notes'">
          <span class="notes">{{ record.notes || '-' }}</span>
        </template>
      </template>
    </a-table>
  </section>
</template>

<style scoped>
.balanceEventsPage { display: grid; gap: 16px; }
.summaryGrid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; }
.summaryGrid section { padding: 14px 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 12px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; color: var(--text-secondary, #70798c); font-size: 12px; margin-bottom: 6px; }
.summaryGrid strong { font-size: 21px; }
.increment { color: #389e0d; } .decrement { color: #cf1322; }
.filterPanel { padding: 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 12px; background: var(--card-bg, #fff); }
.filterGrid { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 14px; align-items: end; }
.filterGrid label { display: grid; gap: 6px; min-width: 0; }
.filterGrid label > span { color: var(--text-secondary, #70798c); font-size: 12px; }
.dateFilter { grid-column: span 2; }
.filterActions { display: flex; gap: 9px; }
.filterMeta { display: flex; flex-wrap: wrap; gap: 12px 24px; margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--border-color, #e8eaf0); color: var(--text-secondary, #70798c); font-size: 12px; }
.money { font-weight: 700; font-variant-numeric: tabular-nums; }
.money.increment { color: #389e0d; }
.money.decrement { color: #cf1322; }
small { display: block; margin-top: 3px; color: var(--text-secondary, #7a8395); }
.notes { white-space: pre-wrap; word-break: break-word; }
@media (max-width: 1180px) {
  .filterGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 760px) { .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 700px) {
  .filterGrid { grid-template-columns: 1fr; }
  .dateFilter { grid-column: auto; }
  .filterActions, .filterActions button { flex: 1; }
}
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
</style>
