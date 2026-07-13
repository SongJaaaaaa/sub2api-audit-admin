<script setup lang="ts">
import type { Dayjs } from 'dayjs'
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { getCashEntries, getGiftEntries, type CashEntry, type FinanceSummary, type GiftQuotaEntry } from '../api/finance'

import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useAdminOptions } from '../composables/useAdminOptions'
import { useTableColumns } from '../composables/useTableColumns'
const adminOptions = useAdminOptions()
const tab = ref<'cash' | 'gift'>('cash')
const loading = ref(false)
const userId = ref('')
const email = ref('')
const businessNo = ref('')
const linkStatus = ref<'linked' | 'unlinked' | ''>('')
const operator = ref<number | undefined>()
const dates = ref<[Dayjs, Dayjs] | undefined>()
const summary = reactive<FinanceSummary>({ record_count: 0, user_count: 0, amount_total: '0.00', linked_count: 0, unlinked_count: 0 })
const cashItems = ref<CashEntry[]>([])
const giftItems = ref<GiftQuotaEntry[]>([])
const detailOpen = ref(false)
const detailLoading = ref(false)
const detail = ref<CashEntry | GiftQuotaEntry | null>(null)
const relatedCash = ref<CashEntry | null>(null)
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const cashColumns = [
  { title: '流水号', dataIndex: 'entry_no', width: 180 },
  { title: '用户ID', dataIndex: 'sub2api_user_id', width: 100 },
  { title: '邮箱', dataIndex: 'sub2api_user_email' },
  { title: '现金金额', dataIndex: 'cash_amount', align: 'right', width: 130 },
  { title: '来源', dataIndex: 'source', width: 150 },
  { title: '备注', dataIndex: 'remark' },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
] as const

const giftColumns = [
  { title: '流水号', dataIndex: 'entry_no', width: 180 },
  { title: '用户ID', dataIndex: 'sub2api_user_id', width: 100 },
  { title: '邮箱', dataIndex: 'sub2api_user_email' },
  { title: '赠送额度', dataIndex: 'quota_amount', align: 'right', width: 130 },
  { title: '来源', dataIndex: 'source', width: 150 },
  { title: '备注', dataIndex: 'remark' },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
] as const
const cashTable = useTableColumns('cash-entry-columns', cashColumns, 1180)
const giftTable = useTableColumns('gift-entry-columns', giftColumns, 1180)

async function loadItems() {
  loading.value = true
  try {
    const params = {
      page: page.current,
      page_size: page.pageSize,
      sub2api_user_id: userId.value,
      sub2api_user_email: email.value,
      business_no: businessNo.value,
      link_status: linkStatus.value,
      created_by: operator.value,
      start_date: dates.value?.[0].format('YYYY-MM-DD'),
      end_date: dates.value?.[1].format('YYYY-MM-DD'),
    }
    if (tab.value === 'cash') {
      const res = await getCashEntries(params)
      cashItems.value = res.items
      page.total = res.total
      Object.assign(summary, res.summary)
    } else {
      const res = await getGiftEntries(params)
      giftItems.value = res.items
      page.total = res.total
      Object.assign(summary, res.summary)
    }
  } catch {
    message.error(tab.value === 'cash' ? '读取现金账失败' : '读取赠送额度账失败')
  } finally {
    loading.value = false
  }
}

async function openCash(row: CashEntry) {
  detail.value = row
  relatedCash.value = row
  detailOpen.value = true
}

async function openGift(row: GiftQuotaEntry) {
  detail.value = row
  relatedCash.value = null
  detailOpen.value = true
  if (!row.ledger_adjustment_id || !row.sub2api_user_id) return

  detailLoading.value = true
  try {
    const res = await getCashEntries({ page: 1, page_size: 100, sub2api_user_id: row.sub2api_user_id })
    relatedCash.value = res.items.find(item => item.ledger_adjustment_id === row.ledger_adjustment_id) || null
  } catch {
    message.error('读取关联现金账失败')
  } finally {
    detailLoading.value = false
  }
}

function rowProps(row: CashEntry | GiftQuotaEntry, type: 'cash' | 'gift') {
  return {
    class: 'clickableRow',
    onClick: () => type === 'cash' ? openCash(row as CashEntry) : openGift(row as GiftQuotaEntry),
  }
}

function cashRowProps(row: CashEntry) { return rowProps(row, 'cash') }
function giftRowProps(row: GiftQuotaEntry) { return rowProps(row, 'gift') }

function isGift(row: CashEntry | GiftQuotaEntry): row is GiftQuotaEntry {
  return 'quota_amount' in row
}

function search() {
  page.current = 1
  loadItems()
}

function resetFilters() {
  userId.value = ''
  email.value = ''
  businessNo.value = ''
  linkStatus.value = ''
  operator.value = undefined
  dates.value = undefined
  search()
}

function change(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  loadItems()
}

function changeTab() {
  page.current = 1
  page.total = 0
  loadItems()
}

onMounted(loadItems)
</script>

<template>
  <section class="page">
    <div class="pageHead">
      <div>
        <h1>现金账与赠送额度</h1>
        <p>调额二次确认成功后生成</p>
      </div>
      <a-button @click="loadItems">刷新</a-button>
    </div>

    <div class="filterBar">
      <a-input v-model:value="userId" placeholder="用户 ID" allow-clear @press-enter="search" />
      <a-input v-model:value="email" placeholder="用户邮箱" allow-clear @press-enter="search" />
      <a-input v-model:value="businessNo" placeholder="业务单号" allow-clear @press-enter="search" />
      <a-select v-model:value="linkStatus" placeholder="关联状态" allow-clear>
        <a-select-option value="">全部关联状态</a-select-option>
        <a-select-option value="linked">已关联</a-select-option>
        <a-select-option value="unlinked">未关联</a-select-option>
      </a-select>
      <a-select v-model:value="operator" placeholder="操作人" allow-clear>
        <a-select-option v-for="row in adminOptions" :key="row.id" :value="row.id">{{ row.name }}（{{ row.email }}）</a-select-option>
      </a-select>
      <a-range-picker v-model:value="dates" />
      <a-button type="primary" @click="search">查询</a-button>
      <a-button @click="resetFilters">重置</a-button>
    </div>

    <div class="summaryGrid">
      <section><span>{{ tab === 'cash' ? '现金入账合计' : '赠送额度合计' }}</span><strong class="money">{{ summary.amount_total }}</strong></section>
      <section><span>记录数</span><strong>{{ summary.record_count }}</strong></section>
      <section><span>用户数</span><strong>{{ summary.user_count }}</strong></section>
      <section><span>{{ tab === 'cash' ? '已关联数' : '关联现金账数' }}</span><strong>{{ tab === 'cash' ? summary.linked_count : (summary.related_cash_count || 0) }}</strong></section>
      <section><span>{{ tab === 'cash' ? '未关联数' : '无现金关联数' }}</span><strong>{{ tab === 'cash' ? summary.unlinked_count : (summary.missing_cash_count || 0) }}</strong></section>
    </div>

    <a-tabs v-model:active-key="tab" @change="changeTab">
      <a-tab-pane key="cash" tab="现金账">
        <div class="tableTools"><ColumnSettings v-model:value="cashTable.visibleCols.value" v-model:width="cashTable.tableWidth.value" :options="cashTable.colOptions.value" @reset="cashTable.resetColumns" /></div>
        <a-table
          row-key="id"
          :custom-row="cashRowProps"
          :columns="cashTable.columns.value"
          :data-source="cashItems"
          :loading="loading"
          :pagination="page"
          :scroll="{ x: cashTable.tableWidth.value }"
          :locale="{ emptyText: '暂无现金账记录' }"
          @change="change"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'cash_amount'">
              <span class="money">{{ record.cash_amount }}</span>
            </template>
          </template>
        </a-table>
      </a-tab-pane>

      <a-tab-pane key="gift" tab="赠送额度">
        <div class="tableTools"><ColumnSettings v-model:value="giftTable.visibleCols.value" v-model:width="giftTable.tableWidth.value" :options="giftTable.colOptions.value" @reset="giftTable.resetColumns" /></div>
        <a-table
          row-key="id"
          :custom-row="giftRowProps"
          :columns="giftTable.columns.value"
          :data-source="giftItems"
          :loading="loading"
          :pagination="page"
          :scroll="{ x: giftTable.tableWidth.value }"
          :locale="{ emptyText: '暂无赠送额度记录' }"
          @change="change"
        >
          <template #bodyCell="{ column, record }">
            <template v-if="column.dataIndex === 'quota_amount'">
              <span class="money">{{ record.quota_amount }}</span>
            </template>
          </template>
        </a-table>
      </a-tab-pane>
    </a-tabs>

    <a-modal v-model:open="detailOpen" title="账目详情" :footer="null" width="680px">
      <a-spin :spinning="detailLoading">
        <a-descriptions v-if="detail" :column="1" bordered size="small">
          <a-descriptions-item label="类型">{{ isGift(detail) ? '赠送额度' : '现金账' }}</a-descriptions-item>
          <a-descriptions-item label="流水号">{{ detail.entry_no }}</a-descriptions-item>
          <a-descriptions-item label="用户">{{ detail.sub2api_user_email || `用户 #${detail.sub2api_user_id}` }}</a-descriptions-item>
          <a-descriptions-item label="账本调整 ID">{{ detail.ledger_adjustment_id || '-' }}</a-descriptions-item>
          <a-descriptions-item label="金额"><strong class="money">{{ isGift(detail) ? detail.quota_amount : detail.cash_amount }}</strong></a-descriptions-item>
          <a-descriptions-item label="来源">{{ detail.source }}</a-descriptions-item>
          <a-descriptions-item label="备注">{{ detail.remark || '-' }}</a-descriptions-item>
          <a-descriptions-item label="创建时间">{{ detail.created_at || '-' }}</a-descriptions-item>
        </a-descriptions>

        <div v-if="detail && isGift(detail)" class="relatedCash">
          <h3>关联现金账</h3>
          <a-descriptions v-if="relatedCash" :column="1" bordered size="small">
            <a-descriptions-item label="现金流水号">{{ relatedCash.entry_no }}</a-descriptions-item>
            <a-descriptions-item label="现金金额"><strong class="money">{{ relatedCash.cash_amount }}</strong></a-descriptions-item>
            <a-descriptions-item label="方向">{{ relatedCash.direction === 'in' ? '入账' : '出账' }}</a-descriptions-item>
            <a-descriptions-item label="来源">{{ relatedCash.source }}</a-descriptions-item>
            <a-descriptions-item label="创建时间">{{ relatedCash.created_at || '-' }}</a-descriptions-item>
          </a-descriptions>
          <a-empty v-else-if="!detailLoading" description="未找到关联现金账" />
        </div>
      </a-spin>
    </a-modal>
  </section>
</template>

<style scoped>
:deep(.clickableRow) { cursor: pointer; }
:deep(.clickableRow:hover) > td { background: rgba(22, 119, 255, .06) !important; }
.filterBar { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 10px; margin-bottom: 14px; }
.summaryGrid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.summaryGrid section { padding: 14px 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 12px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; color: var(--text-secondary, #7a8395); font-size: 13px; margin-bottom: 5px; }
.summaryGrid strong { font-size: 22px; }
.relatedCash { margin-top: 20px; }
.relatedCash h3 { margin: 0 0 10px; }
@media (max-width: 760px) { .filterBar { grid-template-columns: minmax(0, 1fr); } .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); } :deep(.ant-descriptions-view) { overflow-x: auto; } }
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
</style>
