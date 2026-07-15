<script setup lang="ts">
import AppCard from '../../core/AppCard.vue'
import StatusTag from '../../StatusTag.vue'
import type { RebateWithdrawal } from '../../../types'
import { money } from '../../../utils/money'

defineProps<{
  items: RebateWithdrawal[]
  pendingAmount: string
}>()
</script>

<template>
  <AppCard class="activityCard">
    <header class="activityHead">
      <div>
        <h2>最近提现</h2>
        <p>推广用户最新提现申请与处理状态</p>
      </div>
      <span>待审核 {{ money(pendingAmount) }}</span>
    </header>

    <div class="activityTable">
      <a-table
        row-key="id"
        size="small"
        :data-source="items"
        :pagination="false"
        :scroll="{ x: 700 }"
        :locale="{ emptyText: '暂无数据' }"
      >
        <a-table-column title="申请时间" data-index="created_at" :width="165" />
        <a-table-column title="申请单号" data-index="request_no" :width="190" />
        <a-table-column title="用户" key="user" :width="190">
          <template #default="{ record }">{{ record.user_email || `用户 #${record.user_id}` }}</template>
        </a-table-column>
        <a-table-column title="金额" key="amount" align="right" :width="110">
          <template #default="{ record }"><strong class="moneyText">{{ money(record.amount) }}</strong></template>
        </a-table-column>
        <a-table-column title="状态" key="status" :width="95">
          <template #default="{ record }"><StatusTag :status="record.status" /></template>
        </a-table-column>
      </a-table>
    </div>
  </AppCard>
</template>

<style scoped>
.activityCard {
  min-height: 310px;
}

.activityHead {
  display: flex;
  margin-bottom: 16px;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.activityHead h2,
.activityHead p {
  margin: 0;
}

.activityHead h2 {
  color: var(--rebate-text);
  font-size: 18px;
  line-height: 26px;
}

.activityHead p,
.activityHead > span {
  color: var(--rebate-muted);
  font-size: 12px;
  line-height: 18px;
}

.activityHead p {
  margin-top: 4px;
}

.activityHead > span {
  flex: 0 0 auto;
}

.activityTable {
  min-width: 0;
  overflow-x: auto;
}

.moneyText {
  color: var(--rebate-success);
  font-variant-numeric: tabular-nums;
}

:deep(.ant-table-thead > tr > th) {
  padding: 10px 12px;
  background: var(--rebate-low);
  color: var(--rebate-muted);
  font-size: 12px;
}

:deep(.ant-table-tbody > tr > td) {
  padding: 11px 12px;
  color: var(--rebate-text);
  font-size: 12px;
}

@media (max-width: 760px) {
  .activityHead {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
