<script setup lang="ts">
import AppCard from '../../core/AppCard.vue'
import type { RebateRecord } from '../../../types'
import { money } from '../../../utils/money'

defineProps<{
  items: RebateRecord[]
  todayAmount: string
}>()

function typeText(type: RebateRecord['type']) {
  if (type === 'milestone') return '初始里程碑'
  if (type === 'stage') return '后续台阶'
  return '历史期初'
}
</script>

<template>
  <AppCard class="activityCard">
    <header class="activityHead">
      <div>
        <h2>最近返利</h2>
        <p>最新一级推广奖励发放记录</p>
      </div>
      <span>今日 {{ money(todayAmount) }}</span>
    </header>

    <div class="activityTable">
      <a-table
        row-key="id"
        size="small"
        :data-source="items"
        :pagination="false"
        :scroll="{ x: 720 }"
        :locale="{ emptyText: '暂无数据' }"
      >
        <a-table-column title="时间" data-index="created_at" :width="165" />
        <a-table-column title="充值用户" key="payer" :width="210">
          <template #default="{ record }">
            <div class="userCell">
              <strong>{{ record.payer_email || `用户 #${record.payer_user_id}` }}</strong>
              <span>ID {{ record.payer_user_id }}</span>
            </div>
          </template>
        </a-table-column>
        <a-table-column title="类型" key="type" :width="110">
          <template #default="{ record }">{{ typeText(record.type) }}</template>
        </a-table-column>
        <a-table-column title="充值" key="source" align="right" :width="110">
          <template #default="{ record }">{{ money(record.source_amount) }}</template>
        </a-table-column>
        <a-table-column title="返利" key="rebate" align="right" :width="110">
          <template #default="{ record }"><strong class="moneyText">{{ money(record.rebate_amount) }}</strong></template>
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

.userCell {
  display: flex;
  min-width: 0;
  flex-direction: column;
}

.userCell strong,
.userCell span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.userCell span {
  color: var(--rebate-muted);
  font-size: 11px;
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
