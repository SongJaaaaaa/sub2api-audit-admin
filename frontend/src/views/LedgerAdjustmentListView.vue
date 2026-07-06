<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { getLedgerAdjustments, type LedgerAdjustment } from '../api/ledger'
import ColumnSettings, { type ColumnOption } from '../components/table/ColumnSettings.vue'

const loading = ref(false)
const items = ref<LedgerAdjustment[]>([])
const userId = ref('')
const page = reactive({
  current: 1,
  pageSize: 20,
  total: 0,
})

const allColumns = [
  { title: '业务单号', dataIndex: 'ledger_no', width: 180 },
  { title: '用户ID', dataIndex: 'sub2api_user_id', width: 100 },
  { title: '邮箱', dataIndex: 'sub2api_user_email' },
  { title: '方向', dataIndex: 'operation', width: 90 },
  { title: '额度', dataIndex: 'amount', align: 'right', width: 120 },
  { title: '现金', dataIndex: 'cash_amount', align: 'right', width: 120 },
  { title: '赠送', dataIndex: 'gift_quota_amount', align: 'right', width: 120 },
  { title: '调前', dataIndex: 'before_balance', align: 'right', width: 120 },
  { title: '调后', dataIndex: 'after_balance', align: 'right', width: 120 },
  { title: '原因', dataIndex: 'adjust_reason' },
  { title: '确认时间', dataIndex: 'confirmed_at', width: 180 },
] as const

const requiredCols = ['ledger_no', 'sub2api_user_id', 'operation', 'amount', 'confirmed_at']
const visibleCols = ref<string[]>(['ledger_no', 'sub2api_user_id', 'sub2api_user_email', 'operation', 'amount', 'cash_amount', 'gift_quota_amount', 'confirmed_at'])
const colOptions = computed<ColumnOption[]>(() =>
  allColumns.map((item) => ({
    key: item.dataIndex,
    title: item.title,
    required: requiredCols.includes(item.dataIndex),
  })),
)
const columns = computed(() => allColumns.filter((item) => visibleCols.value.includes(item.dataIndex)))

async function loadItems() {
  loading.value = true
  try {
    const res = await getLedgerAdjustments({
      page: page.current,
      page_size: page.pageSize,
      status: 'succeeded',
      sub2api_user_id: userId.value,
    })
    items.value = res.items
    page.total = res.total
  } catch {
    message.error('读取调额记录失败')
  } finally {
    loading.value = false
  }
}

function search() {
  page.current = 1
  loadItems()
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
    <div class="pageHead">
      <div>
        <h1>额度调整记录</h1>
        <p>已二次确认成功</p>
      </div>
      <a-input-search
        v-model:value="userId"
        class="search"
        placeholder="Sub2API 用户 ID"
        allow-clear
        enter-button
        @search="search"
      />
      <ColumnSettings v-model:value="visibleCols" :options="colOptions" />
    </div>

    <a-table
      row-key="id"
      :columns="columns"
      :data-source="items"
      :loading="loading"
      :locale="{ emptyText: '暂无已二次确认成功的调额记录，未确认或失败记录请到异常中心查看' }"
      :pagination="page"
      :scroll="{ x: 1260 }"
      @change="change"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'operation'">
          <a-tag :color="record.operation === 'increment' ? 'green' : 'orange'">
            {{ record.operation === 'increment' ? '增加' : '扣减' }}
          </a-tag>
        </template>
        <template v-if="['amount', 'cash_amount', 'gift_quota_amount', 'before_balance', 'after_balance'].includes(column.dataIndex as string)">
          <span class="money">{{ record[column.dataIndex] || '-' }}</span>
        </template>
      </template>
    </a-table>
  </section>
</template>
