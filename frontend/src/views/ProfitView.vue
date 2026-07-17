<script setup lang="ts">
import { CheckOutlined, EyeOutlined, SearchOutlined, UndoOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { message, Modal } from 'ant-design-vue'
import dayjs, { type Dayjs } from 'dayjs'
import { computed, onMounted, reactive, ref } from 'vue'
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
const batchOpen = ref(false)
const batchLoading = ref(false)
const selectedBatch = ref<ProfitSettlement | null>(null)
const batchItems = ref<ProfitSettlementItem[]>([])

const pendingCount = computed(() => pendingSummary.income_count + pendingSummary.expense_count)
const canSettle = computed(() => !loading.value && pendingCount.value > 0)
const incomeOwners = computed(() => owners.value.filter(owner => owner.income_count > 0))
const expenseOwners = computed(() => owners.value.filter(owner => owner.expense_count > 0))
const tableWidth = computed(() => 475 + (incomeOwners.value.length + expenseOwners.value.length) * 140)
const batchIncome = computed(() => batchItems.value.filter(row => row.item_type === 'cash_entry'))
const batchExpenses = computed(() => batchItems.value.filter(row => row.item_type === 'operation_expense'))
const profitColumns = computed(() => [
  { title: '日期', dataIndex: 'biz_date', key: 'biz_date', fixed: 'left', width: 110 },
  {
    title: '收入',
    children: [
      ...incomeOwners.value.map(owner => ({
        title: `${owner.name}入账`,
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
])

const incomeColumns = [
  { title: '流水号', dataIndex: 'entry_no', width: 190 },
  { title: '管理员', dataIndex: 'owner_name', width: 130 },
  { title: '用户', dataIndex: 'sub2api_user_email', width: 220 },
  { title: '入账金额', dataIndex: 'amount', align: 'right', width: 120 },
  { title: '时间', dataIndex: 'created_at', width: 180 },
]
const expenseColumns = [
  { title: '单号', dataIndex: 'expense_no', width: 190 },
  { title: '管理员', dataIndex: 'owner_name', width: 130 },
  { title: '分类', dataIndex: 'category', width: 120 },
  { title: '支出金额', dataIndex: 'amount', align: 'right', width: 120 },
  { title: '备注', dataIndex: 'remark', width: 220 },
]
const historyColumns = [
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
const batchColumns = [
  { title: '日期', dataIndex: 'biz_date', width: 110 },
  { title: '单号', dataIndex: 'reference_no', width: 200 },
  { title: '管理员', dataIndex: 'owner_name', width: 130 },
  { title: '说明', dataIndex: 'description', width: 260 },
  { title: '金额', dataIndex: 'amount', align: 'right', width: 120 },
]

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
  loading.value = true
  try {
    const res = await getProfitSummary(range)
    owners.value = res.owners
    days.value = res.days
    Object.assign(summary, res.summary)
    Object.assign(pendingSummary, res.pending_summary)
  } catch (err) {
    message.error(apiMessage(err, '读取利润统计失败'))
  } finally {
    loading.value = false
  }
}

async function openDay(row: ProfitDay) {
  detailDate.value = row.biz_date
  detailOpen.value = true
  detailLoading.value = true
  try {
    const res = await getProfitDetails(row.biz_date)
    incomeDetails.value = res.income
    expenseDetails.value = res.expenses
  } catch (err) {
    message.error(apiMessage(err, '读取当日明细失败'))
  } finally {
    detailLoading.value = false
  }
}

function dayRow(row: ProfitDay) {
  return { class: 'clickableRow', onClick: () => openDay(row) }
}

async function confirmSettlement() {
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

async function loadSettlements() {
  historyLoading.value = true
  try {
    const res = await getProfitSettlements({ page: historyPage.current, page_size: historyPage.pageSize, status: historyStatus.value })
    settlements.value = res.items
    historyPage.total = res.total
  } catch (err) {
    message.error(apiMessage(err, '读取分账记录失败'))
  } finally {
    historyLoading.value = false
  }
}

function historyChange(pager: TablePaginationConfig) {
  historyPage.current = pager.current || 1
  historyPage.pageSize = pager.pageSize || 20
  loadSettlements()
}

function searchHistory() {
  historyPage.current = 1
  loadSettlements()
}

async function openBatch(row: ProfitSettlement) {
  selectedBatch.value = row
  batchOpen.value = true
  batchLoading.value = true
  try {
    batchItems.value = (await getProfitSettlementItems(row.id)).items
  } catch (err) {
    message.error(apiMessage(err, '读取分账明细失败'))
  } finally {
    batchLoading.value = false
  }
}

function reverseBatch(row: ProfitSettlement) {
  Modal.confirm({
    title: '确认撤销该分账批次？',
    content: `${row.start_date} 至 ${row.end_date} 的流水将重新进入待分账状态。`,
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

onMounted(() => Promise.all([loadSummary(), loadSettlements()]))
</script>

<template>
  <section class="page profitPage">
    <a-tabs v-model:active-key="activeTab">
      <a-tab-pane key="pending" tab="利润明细">
        <div class="profitToolbar">
          <a-range-picker v-model:value="dates" @change="clearPending" />
          <a-button type="primary" :loading="loading" @click="loadSummary"><SearchOutlined />查询</a-button>
          <a-button type="primary" danger :disabled="!canSettle" @click="confirmOpen = true"><CheckOutlined />确认分账（{{ pendingCount }} 笔）</a-button>
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
          <a-table row-key="id" :columns="incomeColumns" :data-source="incomeDetails" :pagination="false" :scroll="{ x: 840 }" size="small">
            <template #bodyCell="{ column, record }"><template v-if="column.dataIndex === 'amount'"><span class="money">{{ record.amount }}</span></template></template>
          </a-table>
        </section>
        <section class="detailSection">
          <h3>支出明细</h3>
          <a-table row-key="id" :columns="expenseColumns" :data-source="expenseDetails" :pagination="false" :scroll="{ x: 780 }" size="small">
            <template #bodyCell="{ column, record }"><template v-if="column.dataIndex === 'amount'"><span class="money">{{ record.amount }}</span></template></template>
          </a-table>
        </section>
      </a-spin>
    </a-drawer>

    <a-drawer v-model:open="batchOpen" :title="`分账明细 · ${selectedBatch?.batch_no || ''}`" width="920">
      <a-spin :spinning="batchLoading">
        <section class="detailSection"><h3>收入明细</h3><a-table row-key="id" :columns="batchColumns" :data-source="batchIncome" :pagination="false" :scroll="{ x: 820 }" size="small" /></section>
        <section class="detailSection"><h3>支出明细</h3><a-table row-key="id" :columns="batchColumns" :data-source="batchExpenses" :pagination="false" :scroll="{ x: 820 }" size="small" /></section>
      </a-spin>
    </a-drawer>
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
.expenseValue, .negative { color: #cf3f32; }
.detailSection + .detailSection { margin-top: 24px; }
.detailSection h3 { margin: 0 0 10px; font-size: 15px; }
:deep(.clickableRow) { cursor: pointer; }
:deep(.clickableRow:hover) > td { background: rgba(22, 119, 255, .06) !important; }
:deep(.profitTotalRow > td) { background: var(--surface-muted, #fafafa); }
@media (max-width: 760px) { .profitSummary { grid-template-columns: repeat(2, minmax(0, 1fr)); } .profitToolbar :deep(.ant-picker) { width: 100%; } }
@media (max-width: 420px) { .profitSummary { grid-template-columns: 1fr; } }
</style>
