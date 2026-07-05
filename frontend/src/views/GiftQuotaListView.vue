<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { getCashEntries, getGiftEntries, type CashEntry, type GiftQuotaEntry } from '../api/finance'

const tab = ref<'cash' | 'gift'>('cash')
const loading = ref(false)
const userId = ref('')
const cashItems = ref<CashEntry[]>([])
const giftItems = ref<GiftQuotaEntry[]>([])
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

async function loadItems() {
  loading.value = true
  try {
    const params = { page: page.current, page_size: page.pageSize, sub2api_user_id: userId.value }
    if (tab.value === 'cash') {
      const res = await getCashEntries(params)
      cashItems.value = res.items
      page.total = res.total
    } else {
      const res = await getGiftEntries(params)
      giftItems.value = res.items
      page.total = res.total
    }
  } catch {
    message.error(tab.value === 'cash' ? '读取现金账失败' : '读取赠送额度账失败')
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
      <a-input-search v-model:value="userId" class="search" placeholder="Sub2API 用户 ID" allow-clear enter-button @search="search" />
    </div>

    <a-tabs v-model:active-key="tab" @change="changeTab">
      <a-tab-pane key="cash" tab="现金账">
        <a-table
          row-key="id"
          :columns="cashColumns"
          :data-source="cashItems"
          :loading="loading"
          :pagination="page"
          :scroll="{ x: 1180 }"
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
        <a-table
          row-key="id"
          :columns="giftColumns"
          :data-source="giftItems"
          :loading="loading"
          :pagination="page"
          :scroll="{ x: 1180 }"
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
  </section>
</template>
