<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import type { RebateRecord } from '../../../types'
import { money } from '../../../utils/money'
import StatusTag from '../../StatusTag.vue'
import AppCard from '../../core/AppCard.vue'

defineProps<{
  items: RebateRecord[]
  current: number
  pageSize: number
  total: number
}>()

const emit = defineEmits<{ change: [pager: TablePaginationConfig] }>()

function change(pager: TablePaginationConfig) {
  emit('change', pager)
}

function typeText(type: RebateRecord['type']) {
  if (type === 'milestone') return '初始里程碑'
  if (type === 'stage') return '后续台阶'
  return '历史期初'
}
</script>

<template>
  <AppCard class="recordTableCard">
    <slot name="filters" />
    <div class="rebateTable">
      <a-table
        row-key="id"
        size="small"
        :data-source="items"
        :locale="{ emptyText: '暂无返利明细' }"
        :pagination="{ current, pageSize, total, showSizeChanger: true }"
        :scroll="{ x: 980 }"
        @change="change"
      >
          <a-table-column title="时间" data-index="created_at" :width="175" />
          <a-table-column title="来源用户" key="payer" :width="250">
            <template #default="{ record }">
              <div class="rebateUserCell">
                <strong>{{ record.payer_email || `用户 #${record.payer_user_id}` }}</strong>
                <span>ID {{ record.payer_user_id }}</span>
              </div>
            </template>
          </a-table-column>
          <a-table-column title="类型" key="type" :width="130">
            <template #default="{ record }"><a-tag color="blue">{{ typeText(record.type) }}</a-tag></template>
          </a-table-column>
          <a-table-column title="来源金额" key="source" align="right" :width="140">
            <template #default="{ record }">{{ money(record.source_amount) }}</template>
          </a-table-column>
          <a-table-column title="返利金额" key="amount" align="right" :width="140">
            <template #default="{ record }"><span class="rebateAmount">+{{ money(record.rebate_amount) }}</span></template>
          </a-table-column>
          <a-table-column title="层级" :width="90"><template #default>一级</template></a-table-column>
          <a-table-column title="状态" key="status" :width="105">
            <template #default="{ record }"><StatusTag :status="record.status" /></template>
          </a-table-column>
      </a-table>
    </div>
  </AppCard>
</template>

<style scoped>
.recordTableCard { overflow: hidden; }
.recordTableCard :deep(.ant-table-thead > tr > th) {
  padding-top: 10px;
  padding-bottom: 10px;
  background: var(--rebate-low);
  color: var(--rebate-muted);
  font-size: 12px;
  font-weight: 600;
}
.recordTableCard :deep(.ant-table-tbody > tr > td) { padding-top: 11px; padding-bottom: 11px; }
.recordTableCard :deep(.ant-tag) { margin-inline-end: 0; border-radius: 999px; }
</style>
