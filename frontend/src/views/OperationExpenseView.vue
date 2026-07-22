<script setup lang="ts">
import { FilterOutlined, PlusOutlined, RightOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { App as AntApp } from 'ant-design-vue'
import dayjs, { type Dayjs } from 'dayjs'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { createOperationExpense, getOperationExpenses, type ExpenseSummary, type OperationExpense } from '../api/finance'
import MobileActionBar from '../app/components/MobileActionBar.vue'
import MobileFilterSheet from '../app/components/MobileFilterSheet.vue'
import MobileListState from '../app/components/MobileListState.vue'
import MobileLoadMore from '../app/components/MobileLoadMore.vue'
import { useAppMode } from '../app/composables/useAppMode'
import AttachmentUploader from '../components/attachments/AttachmentUploader.vue'
import SafeRichTextDisplay from '../components/richtext/SafeRichTextDisplay.vue'
import SafeRichTextEditor from '../components/richtext/SafeRichTextEditor.vue'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useAdminOptions } from '../composables/useAdminOptions'
import { useTableColumns } from '../composables/useTableColumns'

const { message, modal } = AntApp.useApp()
const { isAppMode } = useAppMode()
const route = useRoute()
const router = useRouter()
const adminOptions = useAdminOptions()
const loading = ref(false)
const loadError = ref('')
const submitting = ref(false)
const modalOpen = ref(false)
const drawerOpen = ref(false)
const filterOpen = ref(false)
const items = ref<OperationExpense[]>([])
const summary = reactive<ExpenseSummary>({ record_count: 0, category_count: 0, amount_total: '0.00', max_amount: '0.00', daily_average: null })
const selected = ref<OperationExpense | null>(null)
const page = reactive({ current: 1, pageSize: 20, total: 0 })
let requestVersion = 0

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

const activeFilters = computed(() => {
  const values = [
    filters.dateRange ? `日期：${filters.dateRange[0].format('MM-DD')} 至 ${filters.dateRange[1].format('MM-DD')}` : '',
    filters.operator ? `操作人：${adminOptions.value.find(row => row.id === filters.operator)?.name || filters.operator}` : '',
    filters.minAmount ? `最低：${filters.minAmount}` : '',
    filters.maxAmount ? `最高：${filters.maxAmount}` : '',
    filters.keyword.trim() ? `关键词：${filters.keyword.trim()}` : '',
  ]
  return values.filter(Boolean)
})

const hasMore = computed(() => items.value.length < page.total)
const isAppDetail = computed(() => isAppMode.value && route.name === 'expense-detail')

function restoreDetail() {
  if (!isAppDetail.value) return
  const id = Number(route.params.expenseId)
  const stateDetail = window.history.state?.detail as OperationExpense | undefined
  selected.value = Number(stateDetail?.id) === id
    ? stateDetail || null
    : items.value.find(row => row.id === id) || null
}

async function loadItems(reset = false, append = false) {
  if (loading.value && !reset) return
  if (reset) page.current = 1
  const version = ++requestVersion
  loading.value = true
  loadError.value = ''
  try {
    const requestPage = append ? page.current + 1 : page.current
    const res = await getOperationExpenses({
      page: requestPage,
      page_size: isAppMode.value ? 20 : page.pageSize,
      from: filters.dateRange?.[0]?.format('YYYY-MM-DD'),
      to: filters.dateRange?.[1]?.format('YYYY-MM-DD'),
      created_by: filters.operator,
      min_amount: filters.minAmount,
      max_amount: filters.maxAmount,
      keyword: filters.keyword,
    })
    if (version !== requestVersion) return
    items.value = append ? [...items.value, ...res.items] : res.items
    page.total = res.total
    page.current = res.page
    page.pageSize = res.page_size
    Object.assign(summary, res.summary)
    restoreDetail()
  } catch (err) {
    if (version !== requestVersion) return
    loadError.value = err instanceof Error ? err.message : '读取支出失败'
    message.error('读取支出失败')
  } finally {
    if (version === requestVersion) loading.value = false
  }
}

function search() { loadItems(true) }
function resetFilters() {
  filters.dateRange = null
  filters.operator = undefined
  filters.minAmount = ''
  filters.maxAmount = ''
  filters.keyword = ''
  search()
}
function resetMobileFilters() {
  filters.dateRange = null
  filters.operator = undefined
  filters.minAmount = ''
  filters.maxAmount = ''
  filters.keyword = ''
}
function change(pager: TablePaginationConfig) { page.current = pager.current || 1; page.pageSize = pager.pageSize || 20; loadItems() }
function loadMore() { if (hasMore.value) loadItems(false, true) }
function openCreate() {
  Object.assign(form, { amount: '', paid_at: dayjs().format('YYYY-MM-DD'), content_html: '' })
  modalOpen.value = true
}
function requestSubmit() {
  if (submitting.value) return
  if (!form.amount) return void message.warning('请填写金额')
  modal.confirm({
    title: '确认新增支出？',
    content: `支出金额：-${Math.abs(Number(form.amount || 0)).toFixed(2)}；发生日期：${form.paid_at}`,
    okText: '确认保存',
    cancelText: '取消',
    onOk: saveExpense,
  })
}

async function saveExpense() {
  if (submitting.value) return
  submitting.value = true
  try {
    const res = await createOperationExpense(form)
    message.success(res.message)
    modalOpen.value = false
    selected.value = null
    drawerOpen.value = false
    await loadItems(true)
  } catch { message.error('保存支出失败') } finally { submitting.value = false }
}
async function openDetail(row: OperationExpense) {
  selected.value = row
  if (isAppMode.value) {
    await router.push({ name: 'expense-detail', params: { expenseId: row.id }, state: { detail: { ...row } } })
    return
  }
  drawerOpen.value = true
}
function rowProps(row: OperationExpense) {
  return { class: 'clickableRow', onClick: () => openDetail(row) }
}
function signedExpense(value: string | number) {
  const amount = Number(value || 0)
  return amount === 0 ? '0.00' : `-${Math.abs(amount).toFixed(2)}`
}

watch(() => [isAppDetail.value, route.params.expenseId] as const, ([show]) => {
  if (show) restoreDetail()
}, { immediate: true })

onMounted(loadItems)
</script>

<template>
  <section class="page">
    <template v-if="isAppMode">
      <template v-if="isAppDetail">
        <div v-if="selected" class="appDetailBody appRouteDetail">
          <div class="appDetailList">
            <div><span>单号</span><strong>{{ selected.expense_no }}</strong></div>
            <div><span>金额</span><strong class="expenseMoney">{{ signedExpense(selected.amount) }}</strong></div>
            <div><span>日期</span><strong>{{ selected.paid_at || '-' }}</strong></div>
            <div><span>分类</span><strong>{{ selected.category || '-' }}</strong></div>
            <div><span>操作人</span><strong>{{ selected.operator_name || selected.operator_email || '-' }}</strong></div>
            <div><span>创建时间</span><strong>{{ selected.created_at || '-' }}</strong></div>
          </div>
          <div v-if="selected.content_html" class="detailNotes"><h3>备注</h3><SafeRichTextDisplay :value="selected.content_html" /></div>
          <div class="drawerBlock"><h3>附件</h3><AttachmentUploader attachable-type="operation_expense" :attachable-id="selected.id" /></div>
        </div>
        <MobileListState v-else :loading="loading" :error="loadError" :empty="!loading && !loadError" empty-text="未找到该支出记录" @retry="loadItems(true)" />
      </template>

      <template v-else>
      <div class="appFilterBar">
        <input v-model="filters.keyword" class="appSearchInput" type="search" placeholder="搜索备注关键词" @keyup.enter="search" />
        <button class="appSecondaryButton appFilterButton" type="button" @click="filterOpen = true"><FilterOutlined />筛选<span v-if="activeFilters.length">（{{ activeFilters.length }}）</span></button>
        <button class="appPrimaryButton" type="button" @click="openCreate"><PlusOutlined />新增</button>
      </div>
      <div v-if="activeFilters.length" class="appFilterTags"><span v-for="tag in activeFilters" :key="tag" class="appFilterTag">{{ tag }}</span></div>
      <div class="appSummaryGrid">
        <article class="appSummaryCard"><span>支出合计</span><strong class="expenseMoney">{{ signedExpense(summary.amount_total) }}</strong></article>
        <article class="appSummaryCard"><span>支出笔数</span><strong>{{ summary.record_count }}</strong></article>
        <article class="appSummaryCard"><span>最大单笔</span><strong class="expenseMoney">{{ signedExpense(summary.max_amount) }}</strong></article>
        <article v-if="summary.daily_average !== null" class="appSummaryCard"><span>日均支出</span><strong class="expenseMoney">{{ signedExpense(summary.daily_average) }}</strong></article>
      </div>
      <div class="appResultMeta">共 {{ page.total }} 条支出记录</div>
      <MobileListState :loading="loading && !items.length" :error="items.length ? '' : loadError" :empty="!loading && !loadError && !items.length" empty-text="暂无支出记录" @retry="loadItems(true)" />
      <div v-if="items.length" class="appCardList">
        <article
          v-for="row in items"
          :key="row.id"
          class="appRecordCard"
          role="button"
          tabindex="0"
          @click="openDetail(row)"
          @keydown.enter="openDetail(row)"
        >
          <div class="appCardTop"><strong>{{ row.expense_no }}</strong><RightOutlined class="appCardArrow" /></div>
          <div class="appCardMetric"><strong class="expenseMoney">{{ signedExpense(row.amount) }}</strong><span class="appStatus warning">{{ row.category || '运营支出' }}</span></div>
          <div class="appCardBottom"><span class="appCardMeta">{{ row.paid_at || '-' }}</span><span class="appCardMeta">{{ row.operator_name || row.operator_email || '-' }}</span></div>
          <div v-if="row.remark" class="appCardMeta wrap">{{ row.remark }}</div>
        </article>
      </div>
      <MobileLoadMore :loading="loading && !!items.length" :has-more="hasMore" :loaded="items.length" :total="page.total" @load="loadMore" />

      <MobileFilterSheet v-model:open="filterOpen" :active-count="activeFilters.length" @reset="resetMobileFilters" @apply="search">
        <div class="appFormStack">
          <label>日期范围<a-range-picker v-model:value="filters.dateRange" class="appFullControl" :placeholder="['开始日期', '结束日期']" format="YYYY-MM-DD" /></label>
          <label>操作人<a-select v-model:value="filters.operator" allow-clear placeholder="全部操作人"><a-select-option v-for="row in adminOptions" :key="row.id" :value="row.id">{{ row.name }}（{{ row.email }}）</a-select-option></a-select></label>
          <label>最小金额<a-input v-model:value="filters.minAmount" inputmode="decimal" placeholder="不限" /></label>
          <label>最大金额<a-input v-model:value="filters.maxAmount" inputmode="decimal" placeholder="不限" /></label>
        </div>
      </MobileFilterSheet>

      <a-drawer v-model:open="modalOpen" placement="right" :width="'100%'" title="新增支出">
        <div class="appDetailBody">
          <a-form layout="vertical">
            <a-form-item label="金额" required><a-input v-model:value="form.amount" inputmode="decimal" placeholder="0.00" /></a-form-item>
            <a-form-item label="发生日期" required><a-date-picker v-model:value="form.paid_at" value-format="YYYY-MM-DD" class="fullField" /></a-form-item>
            <a-form-item label="备注"><SafeRichTextEditor v-model:value="form.content_html" /></a-form-item>
          </a-form>
        </div>
        <MobileActionBar><button class="appPrimaryButton" type="button" :disabled="submitting" @click="requestSubmit">{{ submitting ? '保存中…' : '确认保存' }}</button></MobileActionBar>
      </a-drawer>
      </template>
    </template>

    <template v-else>
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

      <a-modal v-if="!isAppMode" v-model:open="modalOpen" title="新增支出" :confirm-loading="submitting" ok-text="保存" cancel-text="取消" @ok="requestSubmit">
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
    </template>
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
.expenseMoney { color: var(--danger); }
.detailNotes { margin-top: 18px; }
.detailNotes h3 { margin: 0 0 8px; }
.appSummaryGrid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-bottom: 12px; }
.appSummaryCard { min-width: 0; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); }
.appSummaryCard span { display: block; margin-bottom: 4px; color: var(--muted); font-size: 12px; }
.appSummaryCard strong { font-size: 19px; font-variant-numeric: tabular-nums; }
.appCardList .appRecordCard { min-height: 128px; }
.appDetailBody { padding: 16px; padding-bottom: calc(28px + var(--app-safe-bottom)); }
.appRouteDetail { padding: 8px 0 calc(28px + var(--app-safe-bottom)); }
.appDetailList { display: grid; gap: 1px; overflow: hidden; border: 1px solid var(--border); border-radius: 8px; background: var(--border); }
.appDetailList > div { display: flex; justify-content: space-between; gap: 12px; padding: 13px 12px; background: var(--surface); }
.appDetailList span { color: var(--muted); font-size: 13px; }
.appDetailList strong { min-width: 0; color: var(--heading); text-align: right; overflow-wrap: anywhere; }
.appFormStack { display: grid; gap: 14px; }
.appFormStack label { display: grid; gap: 6px; color: var(--muted); font-size: 13px; }
.appFullControl { width: 100%; }
:deep(.clickableRow) { cursor: pointer; }
:deep(.clickableRow:hover) > td { background: var(--row-hover) !important; }
@media (max-width: 760px) { .expenseFilterBar > * { flex: 1 1 100%; width: 100% !important; max-width: none; } .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
</style>
