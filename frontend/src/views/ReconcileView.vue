<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import dayjs from 'dayjs'
import { onMounted, reactive, ref } from 'vue'
import {
  createReconciliation,
  getReconciliationDiffs,
  getReconciliations,
  type ReconcileBatch,
  type ReconcileDiff,
} from '../api/reconcile'

const loading = ref(false)
const creating = ref(false)
const drawerOpen = ref(false)
const bizDate = ref(dayjs().subtract(1, 'day').format('YYYY-MM-DD'))
const items = ref<ReconcileBatch[]>([])
const diffs = ref<ReconcileDiff[]>([])
const selected = ref<ReconcileBatch | null>(null)
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const columns = [
  { title: '批次号', dataIndex: 'batch_no', width: 180 },
  { title: '业务日期', dataIndex: 'biz_date', width: 120 },
  { title: '现金合计', dataIndex: 'cash_total', align: 'right', width: 120 },
  { title: '额度合计', dataIndex: 'quota_total', align: 'right', width: 120 },
  { title: '赠送合计', dataIndex: 'gift_total', align: 'right', width: 120 },
  { title: 'Sub2API 变动', dataIndex: 'sub2api_delta_total', align: 'right', width: 140 },
  { title: '差异', dataIndex: 'diff_amount', align: 'right', width: 120 },
  { title: '状态', dataIndex: 'status', width: 110 },
  { title: '操作', dataIndex: 'action', fixed: 'right', width: 90 },
] as const

const diffColumns = [
  { title: '类型', dataIndex: 'type', width: 130 },
  { title: '说明', dataIndex: 'title' },
  { title: '金额', dataIndex: 'amount', align: 'right', width: 120 },
  { title: '原因', dataIndex: 'reason' },
] as const

async function loadItems() {
  loading.value = true
  try {
    const res = await getReconciliations({ page: page.current, page_size: page.pageSize })
    items.value = res.items
    page.total = res.total
  } catch {
    message.error('读取对账批次失败')
  } finally {
    loading.value = false
  }
}

async function createBatch() {
  creating.value = true
  try {
    const res = await createReconciliation({ biz_date: bizDate.value })
    message.success(res.message)
    loadItems()
  } catch (err) {
    const msg = (err as { response?: { data?: { message?: string } } }).response?.data?.message
    message.error(msg || '生成对账批次失败')
  } finally {
    creating.value = false
  }
}

async function openDiff(row: ReconcileBatch) {
  selected.value = row
  drawerOpen.value = true
  try {
    const res = await getReconciliationDiffs(row.id)
    diffs.value = res.items
  } catch {
    diffs.value = []
    message.error('读取差异明细失败')
  }
}

function change(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  loadItems()
}

function statusText(status: string) {
  return status === 'balanced' ? '已对平' : '有差异'
}

onMounted(loadItems)
</script>

<template>
  <section class="page">
    <div class="pageHead">
      <div>
        <h1>对账中心</h1>
        <p>中国时区日报对账，差异为 0 才能已对平</p>
      </div>
      <div class="headActions">
        <a-date-picker v-model:value="bizDate" value-format="YYYY-MM-DD" />
        <a-button type="primary" :loading="creating" @click="createBatch">生成对账</a-button>
      </div>
    </div>

    <a-table
      row-key="id"
      :columns="columns"
      :data-source="items"
      :loading="loading"
      :pagination="page"
      :scroll="{ x: 1220 }"
      :locale="{ emptyText: '暂无对账批次' }"
      @change="change"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="['cash_total', 'quota_total', 'gift_total', 'sub2api_delta_total', 'diff_amount'].includes(column.dataIndex as string)">
          <span class="money">{{ record[column.dataIndex] }}</span>
        </template>
        <template v-if="column.dataIndex === 'status'">
          <a-tag :color="record.status === 'balanced' ? 'green' : 'red'">{{ statusText(record.status) }}</a-tag>
        </template>
        <template v-if="column.dataIndex === 'action'">
          <a-button size="small" @click="openDiff(record)">差异</a-button>
        </template>
      </template>
    </a-table>

    <a-drawer v-model:open="drawerOpen" title="差异明细" width="560">
      <a-descriptions v-if="selected" :column="1" bordered size="small">
        <a-descriptions-item label="批次">{{ selected.batch_no }}</a-descriptions-item>
        <a-descriptions-item label="日期">{{ selected.biz_date }}</a-descriptions-item>
        <a-descriptions-item label="状态">{{ statusText(selected.status) }}</a-descriptions-item>
        <a-descriptions-item label="差异"><span class="money">{{ selected.diff_amount }}</span></a-descriptions-item>
      </a-descriptions>
      <div class="drawerBlock">
        <a-table row-key="id" size="small" :columns="diffColumns" :data-source="diffs" :pagination="false" :scroll="{ x: 640 }" />
      </div>
    </a-drawer>
  </section>
</template>
