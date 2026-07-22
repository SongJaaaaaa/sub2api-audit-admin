<script setup lang="ts">
import { FilterOutlined, PlusOutlined, RightOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { App as AntApp } from 'ant-design-vue'
import dayjs from 'dayjs'
import type { Dayjs } from 'dayjs'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  createIncome,
  getCashEntries,
  type CashEntry,
  type FinanceSummary,
} from '../api/finance'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import SafeRichTextDisplay from '../components/richtext/SafeRichTextDisplay.vue'
import SafeRichTextEditor from '../components/richtext/SafeRichTextEditor.vue'
import MobileActionBar from '../app/components/MobileActionBar.vue'
import MobileFilterSheet from '../app/components/MobileFilterSheet.vue'
import MobileListState from '../app/components/MobileListState.vue'
import MobileLoadMore from '../app/components/MobileLoadMore.vue'
import { useAppMode } from '../app/composables/useAppMode'
import { useAdminOptions } from '../composables/useAdminOptions'
import { useTableColumns } from '../composables/useTableColumns'

const { message, modal } = AntApp.useApp()
const { isAppMode } = useAppMode()
const route = useRoute()
const router = useRouter()
const adminOptions = useAdminOptions()
const loading = ref(false)
const loadError = ref('')
const items = ref<CashEntry[]>([])
const detailOpen = ref(false)
const detail = ref<CashEntry | null>(null)
const modalOpen = ref(false)
const filterOpen = ref(false)
const submitting = ref(false)
const email = ref('')
const operator = ref<number | undefined>()
const dates = ref<[Dayjs, Dayjs] | null>([dayjs(), dayjs()])
const dateMode = ref<'day' | 'week' | 'month' | ''>('day')
const summary = reactive<FinanceSummary>({ record_count: 0, user_count: 0, amount_total: '0.00', linked_count: 0, unlinked_count: 0 })
const page = reactive({ current: 1, pageSize: 20, total: 0 })
let requestVersion = 0
const form = reactive({ amount: '', received_at: dayjs().format('YYYY-MM-DD'), content_html: '' })

const allColumns = [
  { title: '收入单号', dataIndex: 'entry_no', width: 180 },
  { title: '用户ID', dataIndex: 'sub2api_user_id', width: 100 },
  { title: '邮箱', dataIndex: 'sub2api_user_email', width: 220 },
  { title: '操作人', dataIndex: 'operator_name', width: 180 },
  { title: '收入金额', dataIndex: 'cash_amount', align: 'right', width: 130 },
  { title: '收入日期', dataIndex: 'received_at', width: 120 },
  { title: '来源', dataIndex: 'source', width: 160 },
  { title: '备注', dataIndex: 'remark', width: 180 },
  { title: '记录时间', dataIndex: 'created_at', width: 180 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resizeColumn, resetColumns } = useTableColumns('income-entry-columns', allColumns, 1250)

const activeFilters = computed(() => {
  const values = [
    email.value.trim() ? `邮箱：${email.value.trim()}` : '',
    operator.value ? `操作人：${adminOptions.value.find(row => row.id === operator.value)?.name || operator.value}` : '',
    dates.value ? `日期：${dates.value[0].format('MM-DD')} 至 ${dates.value[1].format('MM-DD')}` : '',
  ]
  return values.filter(Boolean)
})

const hasMore = computed(() => items.value.length < page.total)
const isAppDetail = computed(() => isAppMode.value && route.name === 'income-detail')

function restoreDetail() {
  if (!isAppDetail.value) return
  const id = Number(route.params.entryId)
  const stateDetail = window.history.state?.detail as CashEntry | undefined
  detail.value = Number(stateDetail?.id) === id
    ? stateDetail || null
    : items.value.find(row => row.id === id) || null
}

function filterParams() {
  return {
    sub2api_user_email: email.value,
    created_by: operator.value,
    start_date: dates.value?.[0].format('YYYY-MM-DD'),
    end_date: dates.value?.[1].format('YYYY-MM-DD'),
  }
}

async function loadItems(reset = false, append = false) {
  if (loading.value && !reset) return
  if (reset) page.current = 1
  const version = ++requestVersion
  loading.value = true
  loadError.value = ''
  try {
    const requestPage = append ? page.current + 1 : page.current
    const res = await getCashEntries({
      page: requestPage,
      page_size: isAppMode.value ? 20 : page.pageSize,
      ...filterParams(),
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
    loadError.value = err instanceof Error ? err.message : '读取收入记录失败'
    message.error('读取收入记录失败')
  } finally {
    if (version === requestVersion) loading.value = false
  }
}

function search() {
  loadItems(true)
}

function resetFilters() {
  email.value = ''
  operator.value = undefined
  dateMode.value = ''
  dates.value = null
  search()
}

function resetMobileFilters() {
  email.value = ''
  operator.value = undefined
  dateMode.value = ''
  dates.value = null
}

function change(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  loadItems()
}

function loadMore() {
  if (hasMore.value) loadItems(false, true)
}

function changeDateMode(mode: 'day' | 'week' | 'month') {
  dateMode.value = mode
  const now = dayjs()
  const start = mode === 'week' ? now.startOf('week') : mode === 'month' ? now.startOf('month') : now
  dates.value = [start, now]
  if (!isAppMode.value) search()
}

function changeDates() {
  dateMode.value = ''
}

async function openDetail(row: CashEntry) {
  detail.value = row
  if (isAppMode.value) {
    await router.push({ name: 'income-detail', params: { entryId: row.id }, state: { detail: { ...row } } })
    return
  }
  detailOpen.value = true
}

function rowProps(row: CashEntry) {
  return {
    class: 'clickableRow',
    onClick: () => openDetail(row),
  }
}

function signedMoney(value: string | number) {
  const amount = Number(value || 0)
  if (amount === 0) return '0.00'
  return `+${Math.abs(amount).toFixed(2)}`
}

function sourceLabel(source: string) {
  if (source === 'sub2api_external_adjustment') return 'Sub2API 外部调整'
  if (source === 'manual_income') return '手工收入'
  return '本系统入账'
}

function openCreate() {
  Object.assign(form, { amount: '', received_at: dayjs().format('YYYY-MM-DD'), content_html: '' })
  modalOpen.value = true
}

function requestSubmit() {
  if (submitting.value) return
  if (!form.amount) return void message.warning('请填写金额')
  modal.confirm({
    title: '确认新增收入？',
    content: `收入金额：+${Math.abs(Number(form.amount || 0)).toFixed(2)}；收入日期：${form.received_at}`,
    okText: '确认保存',
    cancelText: '取消',
    onOk: saveIncome,
  })
}

async function saveIncome() {
  if (submitting.value) return
  submitting.value = true
  try {
    const res = await createIncome(form)
    message.success(res.message)
    modalOpen.value = false
    detail.value = null
    detailOpen.value = false
    await loadItems(true)
  } catch {
    message.error('保存收入失败')
  } finally {
    submitting.value = false
  }
}

watch(() => [isAppDetail.value, route.params.entryId] as const, ([show]) => {
  if (show) restoreDetail()
}, { immediate: true })

onMounted(loadItems)
</script>

<template>
  <section class="page">
    <template v-if="isAppMode">
      <template v-if="isAppDetail">
        <div v-if="detail" class="appDetailBody appRouteDetail">
          <div class="appDetailList">
            <div><span>收入单号</span><strong>{{ detail.entry_no }}</strong></div>
            <div><span>用户</span><strong>{{ detail.sub2api_user_email || `用户 #${detail.sub2api_user_id || '-'}` }}</strong></div>
            <div><span>操作人</span><strong>{{ detail.operator_name || detail.operator_email || '-' }}</strong></div>
            <div><span>收入金额</span><strong class="positive">{{ signedMoney(detail.cash_amount) }}</strong></div>
            <div><span>收入日期</span><strong>{{ detail.received_at || '-' }}</strong></div>
            <div><span>来源</span><strong>{{ sourceLabel(detail.source) }}</strong></div>
            <div><span>记录时间</span><strong>{{ detail.created_at || '-' }}</strong></div>
          </div>
          <div v-if="detail.content_html" class="detailNotes"><h3>备注</h3><SafeRichTextDisplay :value="detail.content_html" /></div>
          <div v-else class="appDetailNote">备注：{{ detail.remark || '-' }}</div>
        </div>
        <MobileListState v-else :loading="loading" :error="loadError" :empty="!loading && !loadError" empty-text="未找到该收入记录" @retry="loadItems(true)" />
      </template>

      <template v-else>
      <div class="appFilterBar">
        <input v-model="email" class="appSearchInput" type="search" placeholder="搜索用户邮箱" @keyup.enter="search" />
        <button class="appSecondaryButton appFilterButton" type="button" @click="filterOpen = true"><FilterOutlined />筛选<span v-if="activeFilters.length">（{{ activeFilters.length }}）</span></button>
        <button class="appPrimaryButton" type="button" @click="openCreate"><PlusOutlined />新增</button>
      </div>
      <div v-if="activeFilters.length" class="appFilterTags"><span v-for="tag in activeFilters" :key="tag" class="appFilterTag">{{ tag }}</span></div>
      <div class="appSummaryGrid">
        <article class="appSummaryCard"><span>收入合计</span><strong class="positive">{{ signedMoney(summary.amount_total) }}</strong></article>
        <article class="appSummaryCard"><span>收入笔数</span><strong>{{ summary.record_count }}</strong></article>
        <article class="appSummaryCard"><span>收入用户</span><strong>{{ summary.user_count }}</strong></article>
        <article class="appSummaryCard"><span>外部调整</span><strong>{{ summary.unlinked_count }}</strong></article>
      </div>
      <div class="appResultMeta">共 {{ page.total }} 条收入记录</div>
      <MobileListState :loading="loading && !items.length" :error="items.length ? '' : loadError" :empty="!loading && !loadError && !items.length" empty-text="暂无符合条件的收入记录" @retry="loadItems(true)" />
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
          <div class="appCardTop"><strong>{{ row.sub2api_user_email || `用户 #${row.sub2api_user_id || '-'}` }}</strong><RightOutlined class="appCardArrow" /></div>
          <div class="appCardMetric"><strong class="positive">{{ signedMoney(row.cash_amount) }}</strong><span class="appStatus success">{{ sourceLabel(row.source) }}</span></div>
          <div class="appCardBottom"><span class="appCardMeta">{{ row.received_at || '-' }} · {{ row.entry_no }}</span><span class="appCardMeta">{{ row.operator_name || row.operator_email || '-' }}</span></div>
          <div v-if="row.remark" class="appCardMeta wrap">{{ row.remark }}</div>
        </article>
      </div>
      <MobileLoadMore :loading="loading && !!items.length" :has-more="hasMore" :loaded="items.length" :total="page.total" @load="loadMore" />

      <MobileFilterSheet v-model:open="filterOpen" :active-count="activeFilters.length" @reset="resetMobileFilters" @apply="search">
        <div class="appFormStack">
          <label>操作人<a-select v-model:value="operator" allow-clear placeholder="全部操作人" :options="adminOptions.map(row => ({ label: `${row.name}（${row.email}）`, value: row.id }))" /></label>
          <label>快捷日期<a-segmented :value="dateMode" :options="[{ label: '今日', value: 'day' }, { label: '本周', value: 'week' }, { label: '本月', value: 'month' }]" @change="changeDateMode" /></label>
          <label>日期范围<a-range-picker v-model:value="dates" class="appFullControl" @change="changeDates" /></label>
        </div>
      </MobileFilterSheet>

      <a-drawer v-model:open="modalOpen" placement="right" :width="'100%'" title="新增收入">
        <div class="appDetailBody">
          <a-form layout="vertical">
            <a-form-item label="金额" required><a-input v-model:value="form.amount" inputmode="decimal" placeholder="0.00" /></a-form-item>
            <a-form-item label="收入日期" required><a-date-picker v-model:value="form.received_at" value-format="YYYY-MM-DD" class="fullField" /></a-form-item>
            <a-form-item label="备注"><SafeRichTextEditor v-model:value="form.content_html" /></a-form-item>
          </a-form>
        </div>
        <MobileActionBar><button class="appPrimaryButton" type="button" :disabled="submitting" @click="requestSubmit">{{ submitting ? '保存中…' : '确认保存' }}</button></MobileActionBar>
      </a-drawer>
      </template>
    </template>

    <template v-else>
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
        <a-button type="primary" @click="openCreate"><template #icon><PlusOutlined /></template>新增收入</a-button>
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
          <template v-if="column.dataIndex === 'operator_name'"><span>{{ record.operator_name || record.operator_email || '-' }}</span></template>
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
          <a-descriptions-item label="收入日期">{{ detail.received_at || '-' }}</a-descriptions-item>
          <a-descriptions-item label="来源">{{ sourceLabel(detail.source) }}</a-descriptions-item>
          <a-descriptions-item v-if="!detail.content_html" label="备注">{{ detail.remark || '-' }}</a-descriptions-item>
          <a-descriptions-item label="记录时间">{{ detail.created_at || '-' }}</a-descriptions-item>
        </a-descriptions>
        <div v-if="detail?.content_html" class="detailNotes"><h3>备注</h3><SafeRichTextDisplay :value="detail.content_html" /></div>
      </a-drawer>

      <a-modal v-if="!isAppMode" v-model:open="modalOpen" title="新增收入" :confirm-loading="submitting" ok-text="保存" cancel-text="取消" @ok="requestSubmit">
        <a-form layout="vertical">
          <a-form-item label="金额" required><a-input v-model:value="form.amount" placeholder="0.00" /></a-form-item>
          <a-form-item label="收入日期" required><a-date-picker v-model:value="form.received_at" value-format="YYYY-MM-DD" class="fullField" /></a-form-item>
          <a-form-item label="备注"><SafeRichTextEditor v-model:value="form.content_html" /></a-form-item>
        </a-form>
      </a-modal>
    </template>
  </section>
</template>

<style scoped>
.ledgerHead { align-items: flex-start; }
:deep(.clickableRow) { cursor: pointer; }
:deep(.clickableRow:hover) > td { background: var(--row-hover) !important; }
.filterBar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
.filterItem { width: 220px; }
.dateFilter { width: 260px; }
.summaryGrid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
.summaryGrid section { padding: 16px 18px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 8px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; margin-bottom: 6px; color: var(--text-secondary, #7a8395); font-size: 13px; }
.summaryGrid strong { font-size: 24px; }
.positive { color: var(--success); }
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
.appDetailNote { margin-top: 14px; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--muted); }
.appFormStack { display: grid; gap: 14px; }
.appFormStack label { display: grid; gap: 6px; color: var(--muted); font-size: 13px; }
.appFullControl { width: 100%; }
@media (max-width: 700px) {
  .filterItem, .dateFilter { width: 100%; }
  .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .summaryGrid section { padding: 12px 14px; }
  .statsHead { align-items: flex-start; flex-direction: column; }
}
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
</style>
