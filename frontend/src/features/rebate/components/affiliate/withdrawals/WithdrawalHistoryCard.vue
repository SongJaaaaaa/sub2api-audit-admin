<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import type { RebateWithdrawal } from '../../../types'
import { money } from '../../../utils/money'
import StatusTag from '../../StatusTag.vue'
import AppCard from '../../core/AppCard.vue'

defineProps<{
  items: RebateWithdrawal[]
  page: { current: number; pageSize: number; total: number }
  loading: boolean
  todayCount: number
  todayAmount: string
}>()
const emit = defineEmits<{ change: [pager: TablePaginationConfig] }>()

function change(pager: TablePaginationConfig) {
  emit('change', pager)
}
</script>

<template>
  <AppCard class="withdrawHistoryCard">
    <div class="historyHead">
      <h2>提现记录</h2>
      <span>今日 {{ todayCount }} 笔 · {{ money(todayAmount) }}</span>
    </div>
    <div class="rebateTable">
      <a-table
        row-key="id"
        size="middle"
        :loading="loading"
        :data-source="items"
        :locale="{ emptyText: '暂无提现记录' }"
        :pagination="{ current: page.current, pageSize: page.pageSize, total: page.total, showSizeChanger: true }"
        :scroll="{ x: 850 }"
        @change="change"
      >
        <a-table-column title="方式" :width="105"><template #default>API 额度</template></a-table-column>
        <a-table-column title="提现金额" key="amount" align="right" :width="120">
          <template #default="{ record }"><span class="rebateAmount">{{ money(record.amount) }}</span></template>
        </a-table-column>
        <a-table-column title="到账额度" key="quota" align="right" :width="120">
          <template #default="{ record }">{{ money(record.quota_amount) }}</template>
        </a-table-column>
        <a-table-column title="状态" key="status" :width="105">
          <template #default="{ record }"><StatusTag :status="record.status" /></template>
        </a-table-column>
        <a-table-column title="处理结果" key="result" :width="170">
          <template #default="{ record }"><span class="resultText">{{ record.reject_reason || record.error_message || '-' }}</span></template>
        </a-table-column>
        <a-table-column title="申请时间 / 单号" key="request" :width="230">
          <template #default="{ record }"><div class="rebateUserCell"><strong>{{ record.created_at || '-' }}</strong><span>{{ record.request_no }}</span></div></template>
        </a-table-column>
      </a-table>
    </div>
  </AppCard>
</template>

<style scoped>
.withdrawHistoryCard { min-width: 0; min-height: 100%; overflow: hidden; }
.historyHead { display: flex; min-height: 32px; margin-bottom: 14px; align-items: center; justify-content: space-between; gap: 12px; }
.historyHead h2 { margin: 0; color: var(--rebate-text); font-size: 18px; line-height: 26px; }
.historyHead span { color: var(--rebate-muted); font-size: 12px; text-align: right; }
.resultText { display: block; overflow-wrap: anywhere; white-space: normal; }
:deep(.ant-table-thead > tr > th) { background: var(--rebate-low); color: var(--rebate-muted); font-size: 12px; }
@media (max-width: 760px) {
  .historyHead { align-items: flex-start; flex-direction: column; }
  .historyHead span { text-align: left; }
}
</style>
