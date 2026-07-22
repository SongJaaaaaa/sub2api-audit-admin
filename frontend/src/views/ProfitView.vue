<script setup lang="ts">
import { CheckOutlined, EyeOutlined, FilterOutlined, RightOutlined, SearchOutlined, UndoOutlined } from '@ant-design/icons-vue'
import type { TableColumnsType, TablePaginationConfig } from 'ant-design-vue'
import { App as AntApp } from 'ant-design-vue'
import dayjs, { type Dayjs } from 'dayjs'
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  createProfitSettlement,
  getProfitDetails,
  getProfitSettlementItems,
  getProfitSettlements,
  getProfitSummary,
  reverseProfitSettlement,
  type ProfitDay,
  type ProfitExpenseDetail,
  type ProfitIncomeDetail,
  type ProfitOwner,
  type ProfitSettlement,
  type ProfitSettlementItem,
  type ProfitSummary,
} from '../api/profit'
import MobileFilterSheet from '../app/components/MobileFilterSheet.vue'
import MobileListState from '../app/components/MobileListState.vue'
import MobileLoadMore from '../app/components/MobileLoadMore.vue'
import { useAppMode } from '../app/composables/useAppMode'

const { message, modal } = AntApp.useApp()
const { isAppMode } = useAppMode()
const route = useRoute()
const router = useRouter()
const emptySummary = (): ProfitSummary => ({ income_total: '0.00', expense_total: '0.00', profit_total: '0.00', income_count: 0, expense_count: 0 })
const activeTab = ref('pending')
const dates = ref<[Dayjs, Dayjs] | null>([dayjs().subtract(6, 'day'), dayjs()])
const loading = ref(false)
const settling = ref(false)
const confirmOpen = ref(false)
const owners = ref<ProfitOwner[]>([])
const days = ref<ProfitDay[]>([])
const summary = reactive<ProfitSummary>(emptySummary())
const pendingSummary = reactive<ProfitSummary>(emptySummary())

const detailOpen = ref(false)
const detailLoading = ref(false)
const detailDate = ref('')
const incomeDetails = ref<ProfitIncomeDetail[]>([])
const expenseDetails = ref<ProfitExpenseDetail[]>([])

const historyLoading = ref(false)
const historyStatus = ref('')
const settlements = ref<ProfitSettlement[]>([])
const historyPage = reactive({ current: 1, pageSize: 20, total: 0 })
let summaryVersion = 0
let settlementsVersion = 0
let detailVersion = 0
let batchVersion = 0
const batchOpen = ref(false)
const batchLoading = ref(false)
const selectedBatch = ref<ProfitSettlement | null>(null)
const batchItems = ref<ProfitSettlementItem[]>([])
const mobileDayLimit = ref(20)
const historyError = ref('')
const summaryError = ref('')
const filterOpen = ref(false)
const pageLoaded = ref(false)
const columnWidths = ref<Record<string, number>>(readColumnWidths())

interface ResizableColumn {
  title?: unknown
  dataIndex?: unknown
  key?: unknown
  width?: number
  children?: ResizableColumn[]
  [key: string]: unknown
}

const pendingCount = computed(() => pendingSummary.income_count + pendingSummary.expense_count)
const canSettle = computed(() => !loading.value && !settling.value && pendingCount.value > 0)
const mobileDays = computed(() => days.value.slice(0, mobileDayLimit.value))
const hasMoreDays = computed(() => mobileDayLimit.value < days.value.length)
const hasMoreSettlements = computed(() => settlements.value.length < historyPage.total)
const isDayDetail = computed(() => isAppMode.value && route.name === 'profit-day-detail')
const isBatchDetail = computed(() => isAppMode.value && route.name === 'profit-settlement-detail')
const activeFilters = computed(() => [
  dates.value ? `日期：${dates.value[0].format('MM-DD')} 至 ${dates.value[1].format('MM-DD')}` : '',
  activeTab.value === 'history' && historyStatus.value ? `状态：${historyStatus.value === 'confirmed' ? '已确认' : '已撤销'}` : '',
].filter(Boolean))
const incomeOwners = computed(() => owners.value.filter(owner => owner.income_count > 0))
const expenseOwners = computed(() => owners.value.filter(owner => owner.expense_count > 0))
const tableWidth = computed(() => 475 + (incomeOwners.value.length + expenseOwners.value.length) * 140)
const batchIncome = computed(() => batchItems.value.filter(row => row.item_type === 'cash_entry'))
const batchExpenses = computed(() => batchItems.value.filter(row => row.item_type === 'operation_expense'))
const profitColumns = computed(() => resizableColumns([
  { title: '日期', dataIndex: 'biz_date', key: 'biz_date', fixed: 'left', width: 110 },
  {
    title: '收入',
    children: [
      ...incomeOwners.value.map(owner => ({
        title: owner.id === 0 ? 'sub2api调整' : `${owner.name}入账`,
        key: `income-${owner.id}`,
        width: 140,
        align: 'right',
        customRender: ({ record }: { record: ProfitDay }) => cellMoney(record.income_by_owner[owner.id]),
      })),
      { title: '收入合计', dataIndex: 'income_total', key: 'income_total', width: 120, align: 'right' },
    ],
  },
  {
    title: '支出',
    children: [
      ...expenseOwners.value.map(owner => ({
        title: `${owner.name}支出`,
        key: `expense-${owner.id}`,
        width: 140,
        align: 'right',
        customRender: ({ record }: { record: ProfitDay }) => cellMoney(record.expense_by_owner[owner.id]),
      })),
      { title: '支出合计', dataIndex: 'expense_total', key: 'expense_total', width: 120, align: 'right' },
    ],
  },
  { title: '净利润', dataIndex: 'profit_total', key: 'profit_total', width: 125, align: 'right' },
], 'summary'))

const incomeRawColumns: ResizableColumn[] = [
  { title: '流水号', dataIndex: 'entry_no', width: 190 },
  { title: '管理员', dataIndex: 'owner_name', width: 130 },
  { title: '用户', dataIndex: 'sub2api_user_email', width: 220 },
  { title: '入账金额', dataIndex: 'amount', align: 'right', width: 120 },
  { title: '时间', dataIndex: 'created_at', width: 180 },
]
const expenseRawColumns: ResizableColumn[] = [
  { title: '单号', dataIndex: 'expense_no', width: 190 },
  { title: '管理员', dataIndex: 'owner_name', width: 130 },
  { title: '分类', dataIndex: 'category', width: 120 },
  { title: '支出金额', dataIndex: 'amount', align: 'right', width: 120 },
  { title: '备注', dataIndex: 'remark', width: 220 },
]
const historyRawColumns: ResizableColumn[] = [
  { title: '批次号', dataIndex: 'batch_no', width: 210 },
  { title: '日期范围', dataIndex: 'date_range', width: 220 },
  { title: '收入', dataIndex: 'income_total', align: 'right', width: 120 },
  { title: '支出', dataIndex: 'expense_total', align: 'right', width: 120 },
  { title: '净利润', dataIndex: 'profit_total', align: 'right', width: 120 },
  { title: '流水数', dataIndex: 'item_count', align: 'right', width: 100 },
  { title: '操作人', dataIndex: 'operator_name', width: 130 },
  { title: '状态', dataIndex: 'status', width: 100 },
  { title: '确认时间', dataIndex: 'created_at', width: 180 },
  { title: '操作', dataIndex: 'action', fixed: 'right', width: 150 },
]
const batchRawColumns: ResizableColumn[] = [
  { title: '日期', dataIndex: 'biz_date', width: 110 },
  { title: '单号', dataIndex: 'reference_no', width: 200 },
  { title: '管理员', dataIndex: 'owner_name', width: 130 },
  { title: '说明', dataIndex: 'description', width: 260 },
  { title: '金额', dataIndex: 'amount', align: 'right', width: 120 },
]
const incomeColumns = computed(() => resizableColumns(incomeRawColumns, 'income'))
const expenseColumns = computed(() => resizableColumns(expenseRawColumns, 'expense'))
const historyColumns = computed(() => resizableColumns(historyRawColumns, 'history'))
const batchColumns = computed(() => resizableColumns(batchRawColumns, 'batch'))

watch(columnWidths, value => localStorage.setItem('profit-column-widths', JSON.stringify(value)), { deep: true })

function resizableColumns(columns: ResizableColumn[], scope: string): TableColumnsType {
  return columns.map(column => {
    const key = columnKey(column)
    return {
      ...column,
      width: columnWidths.value[`${scope}:${key}`] || column.width,
      resizable: !!column.width,
      children: column.children ? resizableColumns(column.children, scope) : undefined,
    }
  }) as TableColumnsType
}

function resizeColumn(scope: string, width: number, column: TableColumnsType[number]) {
  const key = columnKey(column as ResizableColumn)
  columnWidths.value = { ...columnWidths.value, [`${scope}:${key}`]: Math.max(60, Math.round(width)) }
}

function columnKey(column: ResizableColumn) {
  return String(column.key || column.dataIndex || column.title || '')
}

const resizeSummaryColumn = (width: number, column: TableColumnsType[number]) => resizeColumn('summary', width, column)
const resizeHistoryColumn = (width: number, column: TableColumnsType[number]) => resizeColumn('history', width, column)
const resizeIncomeColumn = (width: number, column: TableColumnsType[number]) => resizeColumn('income', width, column)
const resizeExpenseColumn = (width: number, column: TableColumnsType[number]) => resizeColumn('expense', width, column)
const resizeBatchColumn = (width: number, column: TableColumnsType[number]) => resizeColumn('batch', width, column)

function readColumnWidths(): Record<string, number> {
  try {
    const value = JSON.parse(localStorage.getItem('profit-column-widths') || '{}')
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {}
  } catch {
    return {}
  }
}

function params() {
  if (!dates.value) return null
  return { start_date: dates.value[0].format('YYYY-MM-DD'), end_date: dates.value[1].format('YYYY-MM-DD') }
}

function cellMoney(val?: string) {
  return Number(val || 0) === 0 ? '-' : val
}

function apiMessage(err: unknown, fallback: string) {
  return (err as any)?.response?.data?.message || fallback
}

function clearPending() {
  Object.assign(pendingSummary, emptySummary())
}

async function loadSummary() {
  const range = params()
  if (!range) return void message.warning('请选择日期范围')
  clearPending()
  mobileDayLimit.value = 20
  const version = ++summaryVersion
  summaryError.value = ''
  loading.value = true
  try {
    const res = await getProfitSummary(range)
    if (version !== summaryVersion) return
    owners.value = res.owners
    days.value = res.days
    Object.assign(summary, res.summary)
    Object.assign(pendingSummary, res.pending_summary)
  } catch (err) {
    if (version !== summaryVersion) return
    summaryError.value = apiMessage(err, '读取利润统计失败')
    message.error(apiMessage(err, '读取利润统计失败'))
  } finally {
    if (version === summaryVersion) loading.value = false
  }
}

async function loadDayDetails(date: string) {
  const version = ++detailVersion
  detailDate.value = date
  incomeDetails.value = []
  expenseDetails.value = []
  detailLoading.value = true
  try {
    const res = await getProfitDetails(date)
    if (version !== detailVersion) return
    incomeDetails.value = res.income
    expenseDetails.value = res.expenses
  } catch (err) {
    if (version !== detailVersion) return
    message.error(apiMessage(err, '读取当日明细失败'))
  } finally {
    if (version === detailVersion) detailLoading.value = false
  }
}

async function openDay(row: ProfitDay) {
  if (isAppMode.value) {
    await router.push({ name: 'profit-day-detail', params: { date: row.biz_date } })
    return
  }
  detailOpen.value = true
  await loadDayDetails(row.biz_date)
}

function dayRow(row: ProfitDay) {
  return { class: 'clickableRow', onClick: () => openDay(row) }
}

async function confirmSettlement() {
  if (settling.value) return
  const range = params()
  if (!range) return
  settling.value = true
  try {
    const res = await createProfitSettlement(range)
    message.success(res.message)
    confirmOpen.value = false
    await Promise.all([loadSummary(), loadSettlements()])
  } catch (err) {
    message.error(apiMessage(err, '确认分账失败'))
  } finally {
    settling.value = false
  }
}

async function loadSettlements(reset = false, append = false) {
  if (historyLoading.value && !reset) return
  if (reset) historyPage.current = 1
  const version = ++settlementsVersion
  historyLoading.value = true
  historyError.value = ''
  try {
    const requestPage = append ? historyPage.current + 1 : historyPage.current
    const res = await getProfitSettlements({ page: requestPage, page_size: isAppMode.value ? 20 : historyPage.pageSize, status: historyStatus.value })
    if (version !== settlementsVersion) return
    settlements.value = append ? [...settlements.value, ...res.items] : res.items
    historyPage.total = res.total
    historyPage.current = res.page
    historyPage.pageSize = res.page_size
  } catch (err) {
    if (version !== settlementsVersion) return
    historyError.value = apiMessage(err, '读取分账记录失败')
    message.error(historyError.value)
  } finally {
    if (version === settlementsVersion) historyLoading.value = false
  }
}

function historyChange(pager: TablePaginationConfig) {
  historyPage.current = pager.current || 1
  historyPage.pageSize = pager.pageSize || 20
  loadSettlements()
}

function searchHistory() {
  loadSettlements(true)
}

function switchMobileTab(tab: string) {
  activeTab.value = tab
  if (tab === 'history' && !settlements.value.length) loadSettlements(true)
}

function applyMobileFilters() {
  if (activeTab.value === 'pending') loadSummary()
  else loadSettlements(true)
}

function resetMobileFilters() {
  dates.value = [dayjs().subtract(6, 'day'), dayjs()]
  historyStatus.value = ''
  applyMobileFilters()
}

function loadMoreDays() {
  mobileDayLimit.value += 20
}

function loadMoreSettlements() {
  if (hasMoreSettlements.value) loadSettlements(false, true)
}

function requestSettlement() {
  if (!canSettle.value) return
  if (!isAppMode.value) {
    confirmOpen.value = true
    return
  }
  modal.confirm({
    title: '确认分账？',
    content: `${params()?.start_date} 至 ${params()?.end_date}；收入 ${pendingSummary.income_total}（${pendingSummary.income_count} 笔），支出 ${pendingSummary.expense_total}（${pendingSummary.expense_count} 笔），净利润 ${pendingSummary.profit_total}。`,
    okText: '确认分账',
    cancelText: '取消',
    onOk: confirmSettlement,
  })
}

async function loadBatchDetails(id: number) {
  const version = ++batchVersion
  batchItems.value = []
  batchLoading.value = true
  try {
    const res = await getProfitSettlementItems(id)
    if (version !== batchVersion) return
    batchItems.value = res.items
  } catch (err) {
    if (version !== batchVersion) return
    message.error(apiMessage(err, '读取分账明细失败'))
  } finally {
    if (version === batchVersion) batchLoading.value = false
  }
}

async function openBatch(row: ProfitSettlement) {
  selectedBatch.value = row
  if (isAppMode.value) {
    await router.push({ name: 'profit-settlement-detail', params: { settlementId: row.id } })
    return
  }
  batchOpen.value = true
  await loadBatchDetails(row.id)
}

function reverseBatch(row: ProfitSettlement) {
  modal.confirm({
    title: '确认撤销该分账批次？',
        content: `${row.start_date} 至 ${row.end_date}；净利润 ${row.profit_total}，共 ${row.income_count + row.expense_count} 笔流水将重新进入待分账状态。`,
    okText: '撤销分账',
    okType: 'danger',
    cancelText: '取消',
    async onOk() {
      try {
        const res = await reverseProfitSettlement(row.id)
        message.success(res.message)
        await Promise.all([loadSummary(), loadSettlements()])
      } catch (err) {
        message.error(apiMessage(err, '撤销分账失败'))
        throw err
      }
    },
  })
}

async function loadPage() {
  await Promise.all([loadSummary(), loadSettlements()])
  pageLoaded.value = true
}

watch(
  () => [route.name, route.params.date, route.params.settlementId] as const,
  () => {
    if (isDayDetail.value) void loadDayDetails(String(route.params.date || ''))
    else if (isBatchDetail.value) void loadBatchDetails(Number(route.params.settlementId))
    else if (!pageLoaded.value) void loadPage()
  },
  { immediate: true },
)
</script>

<template>
  <section class="page profitPage">
    <template v-if="isAppMode">
      <template v-if="isDayDetail">
        <div class="appDetailBody">
          <MobileListState :loading="detailLoading" :empty="!detailLoading && !incomeDetails.length && !expenseDetails.length" empty-text="暂无明细" />
          <section v-if="incomeDetails.length" class="appDetailSection"><h2>收入明细</h2><div class="appCardList"><article v-for="row in incomeDetails" :key="row.id" class="appRecordCard"><div class="appCardTop"><strong>{{ row.entry_no }}</strong><span class="appStatus success">收入</span></div><div class="appCardMetric"><strong>{{ row.amount }}</strong></div><div class="appCardMeta">{{ row.biz_date }} · {{ row.sub2api_user_email || `用户 #${row.sub2api_user_id || '-'}` }} · {{ row.owner_name }}</div><div v-if="row.remark" class="appCardMeta wrap">{{ row.remark }}</div></article></div></section>
          <section v-if="expenseDetails.length" class="appDetailSection"><h2>支出明细</h2><div class="appCardList"><article v-for="row in expenseDetails" :key="row.id" class="appRecordCard"><div class="appCardTop"><strong>{{ row.expense_no }}</strong><span class="appStatus danger">支出</span></div><div class="appCardMetric"><strong class="expenseValue">{{ row.amount }}</strong></div><div class="appCardMeta">{{ row.biz_date }} · {{ row.category }} · {{ row.owner_name }}</div><div v-if="row.remark" class="appCardMeta wrap">{{ row.remark }}</div></article></div></section>
        </div>
      </template>

      <template v-else-if="isBatchDetail">
        <div class="appDetailBody">
          <MobileListState :loading="batchLoading" :empty="!batchLoading && !batchItems.length" empty-text="暂无分账明细" />
          <div v-if="batchItems.length" class="appCardList"><article v-for="row in batchItems" :key="row.id" class="appRecordCard"><div class="appCardTop"><strong>{{ row.reference_no }}</strong><span class="appStatus">{{ row.item_type === 'cash_entry' ? '收入' : '支出' }}</span></div><div class="appCardMetric"><strong :class="{ expenseValue: row.item_type === 'operation_expense' }">{{ row.amount }}</strong></div><div class="appCardBottom"><span class="appCardMeta">{{ row.biz_date }} · {{ row.owner_name || '-' }}</span><span class="appCardMeta">{{ row.description || '-' }}</span></div></article></div>
        </div>
      </template>

      <template v-else>
      <div class="appProfitTopbar">
        <div class="appTabSwitch" role="tablist" aria-label="利润视图">
          <button type="button" :class="{ active: activeTab === 'pending' }" @click="switchMobileTab('pending')">利润明细</button>
          <button type="button" :class="{ active: activeTab === 'history' }" @click="switchMobileTab('history')">分账记录</button>
        </div>
        <button class="appSecondaryButton appFilterButton" type="button" @click="filterOpen = true"><FilterOutlined />筛选<span v-if="activeFilters.length">（{{ activeFilters.length }}）</span></button>
      </div>
      <div v-if="activeFilters.length" class="appFilterTags"><span v-for="tag in activeFilters" :key="tag" class="appFilterTag">{{ tag }}</span></div>

      <template v-if="activeTab === 'pending'">
        <div class="appSummaryGrid">
          <article class="appSummaryCard"><span>收入合计</span><strong>{{ summary.income_total }}</strong></article>
          <article class="appSummaryCard"><span>经营支出</span><strong class="expenseValue">{{ summary.expense_total }}</strong></article>
          <article class="appSummaryCard"><span>净利润</span><strong :class="{ negative: Number(summary.profit_total) < 0 }">{{ summary.profit_total }}</strong></article>
          <article class="appSummaryCard"><span>待分账</span><strong>{{ pendingCount }} 笔</strong></article>
        </div>
        <div class="appProfitActions"><button class="appPrimaryButton" type="button" :disabled="!canSettle" @click="requestSettlement"><CheckOutlined />{{ settling ? '提交中…' : `确认分账（${pendingCount} 笔）` }}</button></div>
        <MobileListState :loading="loading && !days.length" :error="days.length ? '' : summaryError" :empty="!loading && !summaryError && !days.length" empty-text="当前日期范围暂无收支流水" @retry="loadSummary" />
        <div v-if="days.length" class="appCardList">
          <article v-for="row in mobileDays" :key="row.biz_date" class="appRecordCard" role="button" tabindex="0" @click="openDay(row)" @keydown.enter="openDay(row)">
            <div class="appCardTop"><strong>{{ row.biz_date }}</strong><RightOutlined class="appCardArrow" /></div>
            <div class="appCardMetric"><strong>{{ row.profit_total }}</strong><span class="appStatus" :class="Number(row.profit_total) < 0 ? 'danger' : 'success'">净利润</span></div>
            <div class="appCardBottom"><span class="appCardMeta">收入 {{ row.income_total }}</span><span class="appCardMeta">支出 {{ row.expense_total }}</span></div>
            <div class="appCardMeta">{{ row.income_count + row.expense_count }} 笔流水 · 点击查看明细</div>
          </article>
        </div>
        <MobileLoadMore :loading="false" :has-more="hasMoreDays" :loaded="mobileDays.length" :total="days.length" @load="loadMoreDays" />
      </template>

      <template v-else>
        <div class="appResultMeta">共 {{ historyPage.total }} 条分账记录</div>
        <MobileListState :loading="historyLoading && !settlements.length" :error="settlements.length ? '' : historyError" :empty="!historyLoading && !historyError && !settlements.length" empty-text="暂无分账记录" @retry="loadSettlements(true)" />
        <div v-if="settlements.length" class="appCardList">
          <article v-for="row in settlements" :key="row.id" class="appRecordCard" role="button" tabindex="0" @click="openBatch(row)" @keydown.enter="openBatch(row)">
            <div class="appCardTop"><strong>{{ row.batch_no }}</strong><span class="appStatus" :class="row.status === 'confirmed' ? 'success' : ''">{{ row.status === 'confirmed' ? '已确认' : '已撤销' }}</span></div>
            <div class="appCardMetric"><strong :class="{ negative: Number(row.profit_total) < 0 }">{{ row.profit_total }}</strong><RightOutlined class="appCardArrow" /></div>
            <div class="appCardBottom"><span class="appCardMeta">{{ row.start_date }} 至 {{ row.end_date }}</span><span class="appCardMeta">{{ row.income_count + row.expense_count }} 笔</span></div>
            <div class="appCardBottom"><span class="appCardMeta">操作人：{{ row.operator_name || '-' }}</span><button v-if="row.status === 'confirmed'" class="appSecondaryButton appSmallButton" type="button" @click.stop="reverseBatch(row)"><UndoOutlined />撤销</button></div>
          </article>
        </div>
        <MobileLoadMore :loading="historyLoading && !!settlements.length" :has-more="hasMoreSettlements" :loaded="settlements.length" :total="historyPage.total" @load="loadMoreSettlements" />
      </template>

      <MobileFilterSheet v-model:open="filterOpen" :active-count="activeFilters.length" @reset="resetMobileFilters" @apply="applyMobileFilters">
        <div class="appFormStack">
          <label>日期范围<a-range-picker v-model:value="dates" class="appFullControl" @change="clearPending" /></label>
          <label v-if="activeTab === 'history'">分账状态<a-select v-model:value="historyStatus" allow-clear placeholder="全部状态"><a-select-option value="confirmed">已确认</a-select-option><a-select-option value="reversed">已撤销</a-select-option></a-select></label>
        </div>
      </MobileFilterSheet>
      </template>
    </template>

    <template v-else>
    <a-tabs v-model:active-key="activeTab">
      <a-tab-pane key="pending" tab="利润明细">
        <div class="profitToolbar">
          <a-range-picker v-model:value="dates" @change="clearPending" />
          <a-button type="primary" :loading="loading" @click="loadSummary"><SearchOutlined />查询</a-button>
          <a-button type="primary" danger :disabled="!canSettle" @click="requestSettlement"><CheckOutlined />确认分账（{{ pendingCount }} 笔）</a-button>
        </div>

        <div class="profitSummary">
          <section><span>收入合计</span><strong class="money">{{ summary.income_total }}</strong></section>
          <section><span>经营支出</span><strong class="money expenseValue">{{ summary.expense_total }}</strong></section>
          <section><span>净利润</span><strong class="money" :class="{ negative: Number(summary.profit_total) < 0 }">{{ summary.profit_total }}</strong></section>
          <section><span>收入笔数</span><strong>{{ summary.income_count }}</strong></section>
          <section><span>支出笔数</span><strong>{{ summary.expense_count }}</strong></section>
        </div>

        <a-table
          row-key="biz_date"
          :custom-row="dayRow"
          :columns="profitColumns"
          :data-source="days"
          :loading="loading"
          :pagination="false"
          :scroll="{ x: tableWidth }"
          :locale="{ emptyText: '当前日期范围暂无收支流水' }"
          @resize-column="resizeSummaryColumn"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'income_total' || column.dataIndex === 'expense_total'">
              <span class="money">{{ cellMoney(record[column.dataIndex]) }}</span>
            </template>
            <template v-else-if="column.dataIndex === 'profit_total'">
              <strong class="money" :class="{ negative: Number(record.profit_total) < 0 }">{{ record.profit_total }}</strong>
            </template>
          </template>
          <template #summary>
            <a-table-summary fixed>
              <a-table-summary-row class="profitTotalRow">
                <a-table-summary-cell :index="0"><strong>区间合计</strong></a-table-summary-cell>
                <a-table-summary-cell
                  v-for="(owner, index) in incomeOwners"
                  :key="`income-total-${owner.id}`"
                  :index="index + 1"
                  align="right"
                >
                  <strong class="money">{{ owner.income_total }}</strong>
                </a-table-summary-cell>
                <a-table-summary-cell :index="incomeOwners.length + 1" align="right">
                  <strong class="money">{{ summary.income_total }}</strong>
                </a-table-summary-cell>
                <a-table-summary-cell
                  v-for="(owner, index) in expenseOwners"
                  :key="`expense-total-${owner.id}`"
                  :index="incomeOwners.length + index + 2"
                  align="right"
                >
                  <strong class="money expenseValue">{{ owner.expense_total }}</strong>
                </a-table-summary-cell>
                <a-table-summary-cell :index="incomeOwners.length + expenseOwners.length + 2" align="right">
                  <strong class="money expenseValue">{{ summary.expense_total }}</strong>
                </a-table-summary-cell>
                <a-table-summary-cell :index="incomeOwners.length + expenseOwners.length + 3" align="right">
                  <strong class="money" :class="{ negative: Number(summary.profit_total) < 0 }">{{ summary.profit_total }}</strong>
                </a-table-summary-cell>
              </a-table-summary-row>
            </a-table-summary>
          </template>
        </a-table>
      </a-tab-pane>

      <a-tab-pane key="history" tab="分账记录">
        <div class="profitToolbar">
          <a-select v-model:value="historyStatus" placeholder="全部状态" class="statusSelect">
            <a-select-option value="">全部状态</a-select-option>
            <a-select-option value="confirmed">已确认</a-select-option>
            <a-select-option value="reversed">已撤销</a-select-option>
          </a-select>
          <a-button type="primary" @click="searchHistory"><SearchOutlined />查询</a-button>
        </div>
        <a-table
          row-key="id"
          :columns="historyColumns"
          :data-source="settlements"
          :loading="historyLoading"
          :pagination="historyPage"
          :scroll="{ x: 1450 }"
          @resize-column="resizeHistoryColumn"
          @change="historyChange"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'date_range'">{{ record.start_date }} 至 {{ record.end_date }}</template>
            <template v-else-if="column.dataIndex === 'item_count'">{{ record.income_count + record.expense_count }}</template>
            <template v-else-if="['income_total', 'expense_total', 'profit_total'].includes(column.dataIndex)"><span class="money">{{ record[column.dataIndex] }}</span></template>
            <template v-else-if="column.dataIndex === 'status'"><a-tag :color="record.status === 'confirmed' ? 'green' : 'default'">{{ record.status === 'confirmed' ? '已确认' : '已撤销' }}</a-tag></template>
            <template v-else-if="column.dataIndex === 'action'">
              <a-space>
                <a-button size="small" @click="openBatch(record)"><EyeOutlined />明细</a-button>
                <a-button v-if="record.status === 'confirmed'" size="small" danger @click="reverseBatch(record)"><UndoOutlined />撤销</a-button>
              </a-space>
            </template>
          </template>
        </a-table>
      </a-tab-pane>
    </a-tabs>

    <a-modal v-model:open="confirmOpen" title="确认分账" :confirm-loading="settling" ok-text="确认分账" cancel-text="取消" @ok="confirmSettlement">
      <a-descriptions :column="1" bordered size="small">
        <a-descriptions-item label="日期范围">{{ params()?.start_date }} 至 {{ params()?.end_date }}</a-descriptions-item>
        <a-descriptions-item label="待分账收入">{{ pendingSummary.income_total }}（{{ pendingSummary.income_count }} 笔）</a-descriptions-item>
        <a-descriptions-item label="待分账支出">{{ pendingSummary.expense_total }}（{{ pendingSummary.expense_count }} 笔）</a-descriptions-item>
        <a-descriptions-item label="待分账净利润"><strong class="money">{{ pendingSummary.profit_total }}</strong></a-descriptions-item>
      </a-descriptions>
    </a-modal>

    <a-drawer v-model:open="detailOpen" :title="`${detailDate} 收支明细`" width="920">
      <a-spin :spinning="detailLoading">
        <section class="detailSection">
          <h3>收入明细</h3>
          <a-table row-key="id" :columns="incomeColumns" :data-source="incomeDetails" :pagination="false" :scroll="{ x: 840 }" size="small" @resize-column="resizeIncomeColumn">
            <template #bodyCell="{ column, record }"><template v-if="column.dataIndex === 'amount'"><span class="money">{{ record.amount }}</span></template></template>
          </a-table>
        </section>
        <section class="detailSection">
          <h3>支出明细</h3>
          <a-table row-key="id" :columns="expenseColumns" :data-source="expenseDetails" :pagination="false" :scroll="{ x: 780 }" size="small" @resize-column="resizeExpenseColumn">
            <template #bodyCell="{ column, record }"><template v-if="column.dataIndex === 'amount'"><span class="money">{{ record.amount }}</span></template></template>
          </a-table>
        </section>
      </a-spin>
    </a-drawer>

    <a-drawer v-model:open="batchOpen" :title="`分账明细 · ${selectedBatch?.batch_no || ''}`" width="920">
      <a-spin :spinning="batchLoading">
        <section class="detailSection"><h3>收入明细</h3><a-table row-key="id" :columns="batchColumns" :data-source="batchIncome" :pagination="false" :scroll="{ x: 820 }" size="small" @resize-column="resizeBatchColumn" /></section>
        <section class="detailSection"><h3>支出明细</h3><a-table row-key="id" :columns="batchColumns" :data-source="batchExpenses" :pagination="false" :scroll="{ x: 820 }" size="small" @resize-column="resizeBatchColumn" /></section>
      </a-spin>
    </a-drawer>
    </template>
  </section>
</template>

<style scoped>
.profitPage { display: grid; gap: 14px; }
.profitToolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
.statusSelect { width: 180px; }
.profitSummary { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
.profitSummary section { padding: 14px 16px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); }
.profitSummary span { display: block; margin-bottom: 5px; color: var(--muted); font-size: 13px; }
.profitSummary strong { font-size: 22px; }
.expenseValue, .negative { color: var(--danger); }
.detailSection + .detailSection { margin-top: 24px; }
.detailSection h3 { margin: 0 0 10px; font-size: 15px; }
:deep(.clickableRow) { cursor: pointer; }
:deep(.clickableRow:hover) > td { background: var(--row-hover) !important; }
:deep(.profitTotalRow > td) { background: var(--surface-muted, #fafafa); }
/* App-mode styles centralized in src/app/styles/app.css */
.appProfitTopbar { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
.appTabSwitch { display: inline-flex; padding: 3px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface2); }
.appTabSwitch button { min-height: 36px; padding: 0 12px; border: 0; border-radius: 6px; background: transparent; color: var(--muted); font: inherit; cursor: pointer; }
.appTabSwitch button.active { background: var(--surface); color: var(--primary); box-shadow: var(--shadow-card); }
.appProfitActions { display: flex; margin: 0 0 12px; }
.appProfitActions .appPrimaryButton { width: 100%; }
.appCardList .appRecordCard { min-height: 120px; }
.appDetailSection + .appDetailSection { margin-top: 18px; }
.appDetailSection h2 { margin: 0 0 8px; font-size: 16px; color: var(--heading); }
.appSmallButton { min-height: 34px; padding: 0 10px; font-size: 12px; }
@media (max-width: 760px) { .profitSummary { grid-template-columns: repeat(2, minmax(0, 1fr)); } .profitToolbar :deep(.ant-picker) { width: 100%; } }
@media (max-width: 420px) { .profitSummary { grid-template-columns: 1fr; } }
</style>
