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
  type BalanceEventSource,
} from '../api/balanceEvents'

import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useTableColumns } from '../composables/useTableColumns'
const loading = ref(false)
const exporting = ref(false)
const items = ref<BalanceEvent[]>([])
const dateRange = ref<[Dayjs, Dayjs] | null>(null)
const detailOpen = ref(false)
const detail = ref<BalanceEvent | null>(null)
const userId = ref('')
const keyword = ref('')
const source = ref<BalanceEventSource | undefined>()
const direction = ref<BalanceEventDirection | undefined>()
const linkStatus = ref<BalanceEventLinkStatus | undefined>()
const page = reactive({ current: 1, pageSize: 20, total: 0, showSizeChanger: true })

const allColumns = [
  { title: '来源', dataIndex: 'source', width: 130, fixed: 'left' },
  { title: '远端事件 ID', dataIndex: 'remote_event_id', width: 125 },
  { title: '用户', dataIndex: 'user', minWidth: 230 },
  { title: '方向', dataIndex: 'direction', width: 90 },
  { title: '金额', dataIndex: 'amount', width: 135, align: 'right' },
  { title: '关联状态', dataIndex: 'link_status', width: 120 },
  { title: '本地单号', dataIndex: 'ledger_no', width: 210 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resetColumns } = useTableColumns('balance-events-columns', allColumns, 1150)

function params(withPage = true): BalanceEventParams {
  const val: BalanceEventParams = { period: 'history' }

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

function resetFilters() {
  dateRange.value = null
  userId.value = ''
  keyword.value = ''
  source.value = undefined
  direction.value = undefined
  linkStatus.value = undefined
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

function rowProps(row: BalanceEvent) {
  return {
    class: 'clickableRow',
    onClick: () => {
      detail.value = row
      detailOpen.value = true
    },
  }
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
    <div class="pageHead pageHeadActionsOnly">
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

    <section class="filterPanel">
      <div class="filterGrid">
        <label class="filterDate">
          <span>时间</span>
          <a-range-picker v-model:value="dateRange" />
        </label>
        <label class="filterId">
          <span>用户 ID</span>
          <a-input v-model:value="userId" allow-clear placeholder="精确用户 ID" @press-enter="loadItems(true)" />
        </label>
        <label class="filterGrow">
          <span>用户关键字</span>
          <a-input v-model:value="keyword" allow-clear placeholder="邮箱 / 用户名 / ID" @press-enter="loadItems(true)" />
        </label>
        <label class="filterSource">
          <span>来源</span>
          <a-select v-model:value="source" allow-clear placeholder="全部来源">
            <a-select-option value="admin_adjustment">后台余额调额</a-select-option>
            <a-select-option value="balance_redeem">余额兑换码</a-select-option>
            <a-select-option value="payment_order">支付订单</a-select-option>
          </a-select>
        </label>
        <label class="filterDirection">
          <span>方向</span>
          <a-select v-model:value="direction" allow-clear placeholder="全部方向">
            <a-select-option value="increment">调增</a-select-option>
            <a-select-option value="decrement">调减</a-select-option>
          </a-select>
        </label>
        <label class="filterStatus">
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
      <div class="filterMeta">共 {{ page.total.toLocaleString('zh-CN') }} 条</div>
    </section>

    <div class="tableTools">
      <ColumnSettings v-model:value="visibleCols" v-model:width="tableWidth" :options="colOptions" @reset="resetColumns" />
    </div>
    <a-table
      :row-key="rowKey"
      :custom-row="rowProps"
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
      </template>
    </a-table>

    <a-drawer v-model:open="detailOpen" title="历史账详情" width="560">
      <a-descriptions v-if="detail" :column="1" bordered size="small">
        <a-descriptions-item label="时间">{{ detail.event_at || '-' }}</a-descriptions-item>
        <a-descriptions-item label="来源">
          <a-tag :color="sourceColor(detail.source)">{{ sourceText(detail.source) }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item label="远端事件 ID">{{ detail.remote_event_id }}</a-descriptions-item>
        <a-descriptions-item label="用户">{{ detail.user_email || detail.username || `用户 #${detail.sub2api_user_id}` }}</a-descriptions-item>
        <a-descriptions-item label="方向">
          <a-tag :color="directionMeta(detail.direction).color">{{ directionMeta(detail.direction).text }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item label="金额">
          <strong class="money" :class="detail.direction">{{ detail.direction === 'decrement' ? '-' : '+' }}{{ formatMoney(detail.amount) }}</strong>
        </a-descriptions-item>
        <a-descriptions-item label="关联状态">
          <a-tag :color="linkMeta(detail.link_status).color">{{ linkMeta(detail.link_status).text }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item label="本地单号">{{ detail.ledger_no || '-' }}</a-descriptions-item>
        <a-descriptions-item label="备注">{{ detail.notes || '-' }}</a-descriptions-item>
      </a-descriptions>
    </a-drawer>
  </section>
</template>

<style scoped>
.balanceEventsPage { display: grid; gap: 16px; }
:deep(.clickableRow) { cursor: pointer; }
:deep(.clickableRow:hover) > td { background: rgba(22, 119, 255, .06) !important; }
.increment { color: #389e0d; } .decrement { color: #cf1322; }
.filterPanel { padding: 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 12px; background: var(--card-bg, #fff); }
.filterGrid { display: flex; flex-wrap: wrap; gap: 14px; align-items: end; }
.filterGrid label { display: grid; gap: 6px; min-width: 0; }
.filterGrid label > span { color: var(--text-secondary, #70798c); font-size: 12px; }
.filterGrid label :deep(.ant-picker), .filterGrid label :deep(.ant-select) { width: 100%; }
.filterDate { flex: 0 0 250px; }
.filterId { flex: 0 0 130px; }
.filterGrow { flex: 1 1 220px; max-width: 320px; }
.filterSource { flex: 0 0 160px; }
.filterDirection { flex: 0 0 120px; }
.filterStatus { flex: 0 0 150px; }
.filterActions { display: flex; flex: 0 0 auto; gap: 9px; }
.filterMeta { display: flex; flex-wrap: wrap; gap: 12px 24px; margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--border-color, #e8eaf0); color: var(--text-secondary, #70798c); font-size: 12px; }
.money { font-weight: 700; font-variant-numeric: tabular-nums; }
.money.increment { color: #389e0d; }
.money.decrement { color: #cf1322; }
small { display: block; margin-top: 3px; color: var(--text-secondary, #7a8395); }
.notes { white-space: pre-wrap; word-break: break-word; }
@media (max-width: 700px) {
  .filterGrid label { flex: 1 1 100%; max-width: none; }
  .filterGrid label > * { min-width: 0; }
  .filterActions, .filterActions button { flex: 1; }
}
</style>
