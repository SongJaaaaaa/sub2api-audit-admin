<script setup lang="ts">
import type { Dayjs } from 'dayjs'
import type { TablePaginationConfig } from 'ant-design-vue'
import { App as AntApp } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { getGiftEntries, type FinanceSummary, type GiftQuotaEntry } from '../api/finance'

import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useAdminOptions } from '../composables/useAdminOptions'
import { useTableColumns } from '../composables/useTableColumns'
const { message } = AntApp.useApp()
const adminOptions = useAdminOptions()
const loading = ref(false)
const userId = ref('')
const email = ref('')
const businessNo = ref('')
const linkStatus = ref<'linked' | 'unlinked' | ''>('')
const operator = ref<number | undefined>()
const dates = ref<[Dayjs, Dayjs] | undefined>()
const summary = reactive<FinanceSummary>({ record_count: 0, user_count: 0, amount_total: '0.00', linked_count: 0, unlinked_count: 0 })
const giftItems = ref<GiftQuotaEntry[]>([])
const detailOpen = ref(false)
const detail = ref<GiftQuotaEntry | null>(null)
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const giftColumns = [
  { title: '流水号', dataIndex: 'entry_no', width: 180 },
  { title: '用户ID', dataIndex: 'sub2api_user_id', width: 100 },
  { title: '邮箱', dataIndex: 'sub2api_user_email' },
  { title: '赠送额度', dataIndex: 'quota_amount', align: 'right', width: 130 },
  { title: '来源', dataIndex: 'source', width: 150 },
  { title: '备注', dataIndex: 'remark' },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
] as const
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
    const res = await getGiftEntries(params)
    giftItems.value = res.items
    page.total = res.total
    Object.assign(summary, res.summary)
  } catch {
    message.error('读取赠送额度账失败')
  } finally {
    loading.value = false
  }
}

function openGift(row: GiftQuotaEntry) {
  detail.value = row
  detailOpen.value = true
}

function giftRowProps(row: GiftQuotaEntry) {
  return {
    class: 'clickableRow',
    onClick: () => openGift(row),
  }
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

onMounted(loadItems)
</script>

<template>
  <section class="page">
    <div class="pageHead pageHeadActionsOnly">
      <a-button @click="loadItems">刷新</a-button>
    </div>

    <div class="filterBar">
      <a-input v-model:value="userId" class="filterId" placeholder="用户 ID" allow-clear @press-enter="search" />
      <a-input v-model:value="email" class="filterLg" placeholder="用户邮箱" allow-clear @press-enter="search" />
      <a-input v-model:value="businessNo" class="filterBusiness" placeholder="业务单号" allow-clear @press-enter="search" />
      <a-select v-model:value="linkStatus" class="filterSm" placeholder="关联状态" allow-clear>
        <a-select-option value="">全部关联状态</a-select-option>
        <a-select-option value="linked">已关联</a-select-option>
        <a-select-option value="unlinked">未关联</a-select-option>
      </a-select>
      <a-select v-model:value="operator" class="filterLg" placeholder="操作人" allow-clear>
        <a-select-option v-for="row in adminOptions" :key="row.id" :value="row.id">{{ row.name }}（{{ row.email }}）</a-select-option>
      </a-select>
      <a-range-picker v-model:value="dates" class="filterDate" />
      <a-button type="primary" @click="search">查询</a-button>
      <a-button @click="resetFilters">重置</a-button>
    </div>

    <div class="summaryGrid">
      <section><span>赠送额度合计</span><strong class="money">{{ summary.amount_total }}</strong></section>
      <section><span>记录数</span><strong>{{ summary.record_count }}</strong></section>
      <section><span>用户数</span><strong>{{ summary.user_count }}</strong></section>
    </div>

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
      @resize-column="giftTable.resizeColumn"
      @change="change"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'quota_amount'">
          <span class="money">{{ record.quota_amount }}</span>
        </template>
      </template>
    </a-table>

    <a-modal v-model:open="detailOpen" title="账目详情" :footer="null" width="680px">
      <a-descriptions v-if="detail" :column="1" bordered size="small">
        <a-descriptions-item label="类型">赠送额度</a-descriptions-item>
        <a-descriptions-item label="流水号">{{ detail.entry_no }}</a-descriptions-item>
        <a-descriptions-item label="用户">{{ detail.sub2api_user_email || `用户 #${detail.sub2api_user_id}` }}</a-descriptions-item>
        <a-descriptions-item label="账本调整 ID">{{ detail.ledger_adjustment_id || '-' }}</a-descriptions-item>
        <a-descriptions-item label="金额"><strong class="money">{{ detail.quota_amount }}</strong></a-descriptions-item>
        <a-descriptions-item label="来源">{{ detail.source }}</a-descriptions-item>
        <a-descriptions-item label="备注">{{ detail.remark || '-' }}</a-descriptions-item>
        <a-descriptions-item label="创建时间">{{ detail.created_at || '-' }}</a-descriptions-item>
      </a-descriptions>
    </a-modal>
  </section>
</template>

<style scoped>
:deep(.clickableRow) { cursor: pointer; }
:deep(.clickableRow:hover) > td { background: var(--row-hover) !important; }
.filterBar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
.filterId { flex: 0 0 120px; }.filterSm { flex: 0 0 150px; }.filterBusiness { flex: 0 0 210px; }.filterLg { flex: 0 0 220px; }.filterDate { flex: 0 0 250px; }
.summaryGrid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.summaryGrid section { padding: 14px 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 12px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; color: var(--text-secondary, #7a8395); font-size: 13px; margin-bottom: 5px; }
.summaryGrid strong { font-size: 22px; }
@media (max-width: 760px) { .filterBar > * { flex: 1 1 100%; width: 100% !important; } .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); } :deep(.ant-descriptions-view) { overflow-x: auto; } }
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
</style>
