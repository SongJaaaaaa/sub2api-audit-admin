<script setup lang="ts">
import { CheckOutlined, ReloadOutlined, StopOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import type { RebateWithdrawal } from '../../../types'
import { money } from '../../../utils/money'
import StatusTag from '../../StatusTag.vue'

defineProps<{
  items: RebateWithdrawal[]
  loading: boolean
  actionIds: Set<number>
  page: { current: number; pageSize: number; total: number }
}>()
const emit = defineEmits<{
  change: [pager: TablePaginationConfig]
  approve: [row: RebateWithdrawal]
  reject: [row: RebateWithdrawal]
  retry: [row: RebateWithdrawal]
}>()

function rowClass(row: RebateWithdrawal) {
  return row.status === 'exception' ? 'withdrawRowException' : row.status === 'pending' ? 'withdrawRowPending' : ''
}

function change(pager: TablePaginationConfig) {
  emit('change', pager)
}
</script>

<template>
  <div class="withdrawTable">
    <a-table
      row-key="id"
      size="small"
      :loading="loading"
      :data-source="items"
      :pagination="{ current: page.current, pageSize: page.pageSize, total: page.total, showSizeChanger: true }"
      :scroll="{ x: 1240 }"
      :row-class-name="rowClass"
      @change="change"
    >
      <a-table-column title="ID" data-index="id" :width="70" />
      <a-table-column title="用户" key="user" :width="210">
        <template #default="{ record }"><div class="userCell"><strong>{{ record.user_email || `用户 #${record.user_id}` }}</strong><span>ID {{ record.user_id }}</span></div></template>
      </a-table-column>
      <a-table-column title="扣除返利" key="amount" align="right" :width="120">
        <template #default="{ record }"><strong class="amount">{{ money(record.amount) }}</strong></template>
      </a-table-column>
      <a-table-column title="转入额度" key="quota" align="right" :width="120">
        <template #default="{ record }">{{ money(record.quota_amount) }}</template>
      </a-table-column>
      <a-table-column title="申请单号" data-index="request_no" :width="220" />
      <a-table-column title="状态" key="status" :width="105">
        <template #default="{ record }"><StatusTag :status="record.status" /></template>
      </a-table-column>
      <a-table-column title="结果" key="result" :width="210">
        <template #default="{ record }"><span class="result">{{ record.reject_reason || record.error_message || '-' }}</span></template>
      </a-table-column>
      <a-table-column title="申请时间" data-index="created_at" :width="170" />
      <a-table-column title="操作" key="action" fixed="right" :width="170">
        <template #default="{ record }">
          <div class="actions">
            <template v-if="record.status === 'pending'">
              <a-button type="primary" size="small" :loading="actionIds.has(record.id)" @click="$emit('approve', record)"><template #icon><CheckOutlined /></template>通过</a-button>
              <a-button danger size="small" :disabled="actionIds.has(record.id)" @click="$emit('reject', record)"><template #icon><StopOutlined /></template>拒绝</a-button>
            </template>
            <a-button v-else-if="record.status === 'exception'" size="small" :loading="actionIds.has(record.id)" @click="$emit('retry', record)"><template #icon><ReloadOutlined /></template>重试</a-button>
            <span v-else class="muted">无需操作</span>
          </div>
        </template>
      </a-table-column>
    </a-table>
  </div>
</template>

<style scoped>
.withdrawTable { min-width: 0; overflow-x: auto; }
.userCell { display: flex; min-width: 0; flex-direction: column; gap: 2px; }
.userCell strong { overflow: hidden; color: var(--rebate-text); text-overflow: ellipsis; }
.userCell span,
.muted { color: var(--rebate-muted); font-size: 12px; }
.amount { color: var(--rebate-text); font-variant-numeric: tabular-nums; }
.result { display: block; overflow-wrap: anywhere; white-space: normal; }
.actions { display: flex; align-items: center; gap: 6px; }
:deep(.ant-table-thead > tr > th) { padding: 11px 12px; background: var(--rebate-low); color: var(--rebate-muted); font-size: 12px; font-weight: 700; }
:deep(.ant-table-tbody > tr > td) { padding: 12px; color: #334155; font-size: 13px; }
:deep(.withdrawRowException > td:first-child) { box-shadow: inset 3px 0 var(--rebate-danger); }
:deep(.withdrawRowPending > td:first-child) { box-shadow: inset 3px 0 var(--rebate-warning); }
:deep(.ant-table-pagination.ant-pagination) { margin: 16px 0 0; }
</style>
