<script setup lang="ts">
import { ReloadOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import dayjs, { type Dayjs } from 'dayjs'
import { onMounted, reactive, ref } from 'vue'
import {
  createReconciliation,
  getReconciliationDiffs,
  getReconciliations,
  type ReconcileBatch,
  type ReconcileDiff,
  type ReconcileStatus,
  type ReconcileSummary,
} from '../api/reconcile'

import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useAdminOptions } from '../composables/useAdminOptions'
import { useTableColumns } from '../composables/useTableColumns'
const adminOptions = useAdminOptions()
const loading = ref(false)
const creating = ref(false)
const diffLoading = ref(false)
const drawerOpen = ref(false)
const bizDate = ref(dayjs().subtract(1, 'day').format('YYYY-MM-DD'))
const items = ref<ReconcileBatch[]>([])
const diffs = ref<ReconcileDiff[]>([])
const selected = ref<ReconcileBatch | null>(null)
const filters = reactive({ dates: null as [Dayjs, Dayjs] | null, status: '' as ReconcileStatus | '', hasExternal: '' as '' | '0' | '1', hasOrphan: '' as '' | '0' | '1', operator: undefined as number | undefined })
const summary = reactive<ReconcileSummary>({ batch_count: 0, ok_count: 0, warning_count: 0, error_count: 0, diff_count: 0, diff_amount: '0.00', healthy_rate: 0, last_success_date: null, unreconciled_days: null })
const page = reactive({ current: 1, pageSize: 20, total: 0, showSizeChanger: true })

const allColumns = [
  { title: '业务日期', dataIndex: 'biz_date', width: 120, fixed: 'left' },
  { title: '实际对账区间', dataIndex: 'period', width: 305 },
  { title: '本地成功', dataIndex: 'local_success_count', width: 105, align: 'right' },
  { title: '本地净调额', dataIndex: 'local_adjustment_net', width: 135, align: 'right' },
  { title: '远端匹配', dataIndex: 'remote_matched_count', width: 105, align: 'right' },
  { title: '远端匹配净额', dataIndex: 'remote_matched_net', width: 145, align: 'right' },
  { title: '外部事件', dataIndex: 'external_count', width: 105, align: 'right' },
  { title: '审计孤儿', dataIndex: 'audit_orphan_count', width: 105, align: 'right' },
  { title: '问题数', dataIndex: 'issue_count', width: 90, align: 'right' },
  { title: '状态', dataIndex: 'status', width: 100 },
  { title: '更新时间', dataIndex: 'updated_at', width: 168 },
  { title: '操作', dataIndex: 'action', fixed: 'right', width: 90 },
] as const

const diffColumns = [
  { title: '差异类型', dataIndex: 'type', width: 175 },
  { title: '本地调整 ID', dataIndex: 'local_adjustment_id', width: 120 },
  { title: '远端事件 ID', dataIndex: 'remote_event_id', width: 120 },
  { title: '用户 ID', dataIndex: 'sub2api_user_id', width: 105 },
  { title: '方向', dataIndex: 'direction', width: 90 },
  { title: '本地金额', dataIndex: 'local_amount', width: 125, align: 'right' },
  { title: '远端金额', dataIndex: 'remote_amount', width: 125, align: 'right' },
  { title: '原因', dataIndex: 'reason', minWidth: 280 },
] as const
const mainTable = useTableColumns('reconcile-columns', allColumns, 1640)
const diffTable = useTableColumns('reconcile-diff-columns', diffColumns, 1200)

async function loadItems() {
  loading.value = true
  try {
    const res = await getReconciliations({
      page: page.current, page_size: page.pageSize,
      start_date: filters.dates?.[0].format('YYYY-MM-DD'), end_date: filters.dates?.[1].format('YYYY-MM-DD'),
      status: filters.status, has_external: filters.hasExternal, has_orphan: filters.hasOrphan, created_by: filters.operator,
    })
    items.value = res.items
    page.total = res.total
    if (res.summary) Object.assign(summary, res.summary)
  } catch (err) {
    message.error(apiMessage(err, '读取对账批次失败'))
  } finally {
    loading.value = false
  }
}

async function createBatch() {
  creating.value = true
  try {
    const res = await createReconciliation({ biz_date: bizDate.value })
    message.success(res.message)
    page.current = 1
    await loadItems()
  } catch (err) {
    message.error(apiMessage(err, '生成对账批次失败'))
  } finally {
    creating.value = false
  }
}

async function openDiff(row: ReconcileBatch) {
  selected.value = row
  diffs.value = []
  drawerOpen.value = true
  diffLoading.value = true

  try {
    const res = await getReconciliationDiffs(row.id)
    diffs.value = res.items
  } catch (err) {
    message.error(apiMessage(err, '读取差异明细失败'))
  } finally {
    diffLoading.value = false
  }
}

function search() { page.current = 1; loadItems() }
function resetFilters() { Object.assign(filters, { dates: null, status: '', hasExternal: '', hasOrphan: '', operator: undefined }); search() }

function change(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  loadItems()
}

function apiMessage(err: unknown, fallback: string) {
  return (err as { response?: { data?: { message?: string } } }).response?.data?.message || fallback
}

function statusMeta(status: ReconcileStatus) {
  if (status === 'ok') return { text: '正常', color: 'green' }
  if (status === 'warning') return { text: '告警', color: 'orange' }
  return { text: '错误', color: 'red' }
}

function diffType(type: string) {
  const labels: Record<string, string> = {
    local_missing_remote: '本地缺少远端事件',
    remote_external: '外部后台调额',
    remote_audit_orphan: '审计孤儿',
    user_mismatch: '用户不一致',
    direction_mismatch: '方向不一致',
    amount_mismatch: '金额不一致',
    duplicate_source_link: '远端事件重复关联',
  }

  return labels[type] || type
}

function directionText(direction: string | null) {
  if (direction === 'increment') return '调增'
  if (direction === 'decrement') return '调减'
  return '-'
}

function formatMoney(val: string | number | null | undefined) {
  if (val === null || val === undefined || val === '') return '-'
  return Number(val).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 8 })
}

onMounted(loadItems)
</script>

<template>
  <section class="page reconcilePage">
    <div class="pageHead pageHeadActionsOnly">
      <div class="headActions">
        <a-date-picker v-model:value="bizDate" value-format="YYYY-MM-DD" :allow-clear="false" />
        <a-button type="primary" :loading="creating" @click="createBatch">运行 / 重跑该日对账</a-button>
        <a-button :loading="loading" @click="loadItems">
          <template #icon><ReloadOutlined /></template>
        </a-button>
      </div>
    </div>

    <div class="filterBar">
      <a-range-picker v-model:value="filters.dates" class="filterDate" />
      <a-select v-model:value="filters.status" class="filterStatus" placeholder="对账状态"><a-select-option value="">全部状态</a-select-option><a-select-option value="ok">正常</a-select-option><a-select-option value="warning">告警</a-select-option><a-select-option value="error">错误</a-select-option></a-select>
      <a-select v-model:value="filters.hasExternal" class="filterExternal" placeholder="外部调额"><a-select-option value="">全部</a-select-option><a-select-option value="1">存在外部调额</a-select-option><a-select-option value="0">无外部调额</a-select-option></a-select>
      <a-select v-model:value="filters.hasOrphan" class="filterOrphan" placeholder="审计孤儿"><a-select-option value="">全部</a-select-option><a-select-option value="1">存在审计孤儿</a-select-option><a-select-option value="0">无审计孤儿</a-select-option></a-select>
      <a-select v-model:value="filters.operator" class="filterLg" placeholder="操作人" allow-clear><a-select-option v-for="row in adminOptions" :key="row.id" :value="row.id">{{ row.name }}（{{ row.email }}）</a-select-option></a-select>
      <a-button type="primary" @click="search">查询</a-button><a-button @click="resetFilters">重置</a-button>
    </div>

    <div class="summaryGrid">
      <section><span>对账批次数</span><strong>{{ summary.batch_count }}</strong></section>
      <section><span>正常 / 告警 / 错误</span><strong><em class="ok">{{ summary.ok_count }}</em> / <em class="warn">{{ summary.warning_count }}</em> / <em class="error">{{ summary.error_count }}</em></strong></section>
      <section><span>差异记录数</span><strong>{{ summary.diff_count }}</strong></section>
      <section><span>差异金额</span><strong class="money">{{ formatMoney(summary.diff_amount) }}</strong></section>
      <section><span>对账正常率</span><strong>{{ summary.healthy_rate.toFixed(2) }}%</strong></section>
      <section><span>最近成功对账日</span><strong class="dateValue">{{ summary.last_success_date || '-' }}</strong></section>
      <section><span>未对账天数</span><strong>{{ summary.unreconciled_days ?? '-' }}</strong></section>
    </div>

    <div class="tableTools"><ColumnSettings v-model:value="mainTable.visibleCols.value" v-model:width="mainTable.tableWidth.value" :options="mainTable.colOptions.value" @reset="mainTable.resetColumns" /></div>
    <a-table
      row-key="id"
      :columns="mainTable.columns.value"
      :data-source="items"
      :loading="loading"
      :pagination="page"
      :scroll="{ x: mainTable.tableWidth.value }"
      :locale="{ emptyText: '暂无对账批次' }"
      @resize-column="mainTable.resizeColumn"
      @change="change"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'period'">
          <div class="periodText">
            <span>{{ record.period_start || '-' }}</span>
            <small>至 {{ record.period_end || '-' }}（排他）</small>
          </div>
        </template>
        <template v-else-if="column.dataIndex === 'local_adjustment_net' || column.dataIndex === 'remote_matched_net'">
          <span class="money">{{ formatMoney(record[column.dataIndex]) }}</span>
        </template>
        <template v-else-if="column.dataIndex === 'external_count'">
          <span :class="{ warn: record.external_count > 0 }">{{ record.external_count }}</span>
        </template>
        <template v-else-if="column.dataIndex === 'audit_orphan_count'">
          <span :class="{ warn: record.audit_orphan_count > 0 }">{{ record.audit_orphan_count }}</span>
        </template>
        <template v-else-if="column.dataIndex === 'issue_count'">
          <strong :class="{ error: record.issue_count > 0 }">{{ record.issue_count }}</strong>
        </template>
        <template v-else-if="column.dataIndex === 'status'">
          <a-tag :color="statusMeta(record.status).color">{{ statusMeta(record.status).text }}</a-tag>
        </template>
        <template v-else-if="column.dataIndex === 'action'">
          <a-button size="small" @click="openDiff(record)">明细</a-button>
        </template>
      </template>
    </a-table>

    <a-drawer v-model:open="drawerOpen" title="对账批次与差异明细" width="960">
      <a-descriptions v-if="selected" :column="2" bordered size="small">
        <a-descriptions-item label="批次号">{{ selected.batch_no }}</a-descriptions-item>
        <a-descriptions-item label="业务日期">{{ selected.biz_date }}</a-descriptions-item>
        <a-descriptions-item label="实际开始">{{ selected.period_start || '-' }}</a-descriptions-item>
        <a-descriptions-item label="实际结束（排他）">{{ selected.period_end || '-' }}</a-descriptions-item>
        <a-descriptions-item label="本地成功笔数">{{ selected.local_success_count }}</a-descriptions-item>
        <a-descriptions-item label="本地调额净额"><span class="money">{{ formatMoney(selected.local_adjustment_net) }}</span></a-descriptions-item>
        <a-descriptions-item label="远端匹配笔数">{{ selected.remote_matched_count }}</a-descriptions-item>
        <a-descriptions-item label="远端匹配净额"><span class="money">{{ formatMoney(selected.remote_matched_net) }}</span></a-descriptions-item>
        <a-descriptions-item label="外部事件">{{ selected.external_count }} 笔 / {{ formatMoney(selected.external_net) }}</a-descriptions-item>
        <a-descriptions-item label="审计孤儿">{{ selected.audit_orphan_count }} 笔 / {{ formatMoney(selected.audit_orphan_net) }}</a-descriptions-item>
        <a-descriptions-item label="缺失或不一致">{{ selected.issue_count }}</a-descriptions-item>
        <a-descriptions-item label="状态"><a-tag :color="statusMeta(selected.status).color">{{ statusMeta(selected.status).text }}</a-tag></a-descriptions-item>
      </a-descriptions>

      <div class="drawerBlock">
        <div class="sectionHead">
          <div>
            <h3>差异明细</h3>
          </div>
        </div>
            <div class="tableTools"><ColumnSettings v-model:value="diffTable.visibleCols.value" v-model:width="diffTable.tableWidth.value" :options="diffTable.colOptions.value" @reset="diffTable.resetColumns" /></div>
        <a-table
          row-key="id"
          size="small"
          :columns="diffTable.columns.value"
          :data-source="diffs"
          :loading="diffLoading"
          :pagination="false"
          :scroll="{ x: diffTable.tableWidth.value }"
          :locale="{ emptyText: '该批次没有差异' }"
          @resize-column="diffTable.resizeColumn"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'type'">
              <strong>{{ record.title || diffType(record.type) }}</strong>
              <small>{{ record.type }}</small>
            </template>
            <template v-else-if="column.dataIndex === 'direction'">
              {{ directionText(record.direction) }}
            </template>
            <template v-else-if="column.dataIndex === 'local_amount' || column.dataIndex === 'remote_amount'">
              <span class="money">{{ formatMoney(record[column.dataIndex]) }}</span>
            </template>
            <template v-else-if="['local_adjustment_id', 'remote_event_id', 'sub2api_user_id'].includes(String(column.dataIndex))">
              {{ record[column.dataIndex] ?? '-' }}
            </template>
          </template>
        </a-table>
      </div>
    </a-drawer>
  </section>
</template>

<style scoped>
.filterBar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.filterStatus { flex: 0 0 140px; }.filterOrphan { flex: 0 0 160px; }.filterExternal { flex: 0 0 170px; }.filterLg { flex: 0 0 220px; }.filterDate { flex: 0 0 250px; }
.summaryGrid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.summaryGrid section { padding: 14px 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 12px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; color: var(--text-secondary, #7a8395); font-size: 12px; margin-bottom: 6px; }
.summaryGrid strong { font-size: 21px; } .summaryGrid em { font-style: normal; } .ok { color: #389e0d; } .dateValue { font-size: 16px !important; }
.reconcilePage { display: grid; gap: 16px; }
.periodText { display: grid; gap: 3px; font-variant-numeric: tabular-nums; }
.periodText small, .drawerBlock small { color: var(--text-secondary, #7a8395); }
.money { color: #d46b08; font-weight: 600; font-variant-numeric: tabular-nums; }
.warn { color: #d46b08; font-weight: 700; }
.error { color: #cf1322; }
.drawerBlock { margin-top: 22px; }
.sectionHead h3 { margin: 0; }
@media (max-width: 760px) {
  .filterBar > * { flex: 1 1 100%; width: 100% !important; } .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .headActions, .headActions :deep(.ant-picker) { width: 100%; }
}
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
</style>
