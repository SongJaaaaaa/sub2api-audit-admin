<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { getLedgerAdjustments, type LedgerAdjustment } from '../api/ledger'

const loading = ref(false)
const items = ref<LedgerAdjustment[]>([])
const page = reactive({
  current: 1,
  pageSize: 20,
  total: 0,
})

const columns = [
  { title: '业务单号', dataIndex: 'ledger_no', width: 180 },
  { title: '状态', dataIndex: 'status', width: 100 },
  { title: '用户ID', dataIndex: 'sub2api_user_id', width: 100 },
  { title: '方向', dataIndex: 'operation', width: 90 },
  { title: '额度', dataIndex: 'amount', align: 'right', width: 120 },
  { title: '调前', dataIndex: 'before_balance', align: 'right', width: 120 },
  { title: '调后', dataIndex: 'after_balance', align: 'right', width: 120 },
  { title: '异常原因', dataIndex: 'exception_reason' },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
] as const

async function loadItems() {
  loading.value = true
  try {
    const res = await getLedgerAdjustments({
      page: page.current,
      page_size: page.pageSize,
      status: 'abnormal',
    })
    items.value = res.items
    page.total = res.total
  } catch {
    message.error('读取异常记录失败')
  } finally {
    loading.value = false
  }
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
        <h1>异常中心</h1>
        <p>作废单与异常单</p>
      </div>
      <a-button @click="loadItems">刷新</a-button>
    </div>

    <a-table
      row-key="id"
      :columns="columns"
      :data-source="items"
      :loading="loading"
      :pagination="page"
      :scroll="{ x: 1220 }"
      @change="change"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'status'">
          <a-tag :color="record.status === 'exception' ? 'red' : 'orange'">
            {{ record.status === 'exception' ? '异常' : '作废' }}
          </a-tag>
        </template>
        <template v-if="column.dataIndex === 'operation'">
          {{ record.operation === 'increment' ? '增加' : '扣减' }}
        </template>
        <template v-if="['amount', 'before_balance', 'after_balance'].includes(column.dataIndex as string)">
          <span class="money">{{ record[column.dataIndex] || '-' }}</span>
        </template>
      </template>
    </a-table>
  </section>
</template>
