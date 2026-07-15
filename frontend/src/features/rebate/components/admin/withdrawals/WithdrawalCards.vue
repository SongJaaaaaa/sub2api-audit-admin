<script setup lang="ts">
import { CheckOutlined, ReloadOutlined, StopOutlined } from '@ant-design/icons-vue'
import type { RebateWithdrawal } from '../../../types'
import { money } from '../../../utils/money'
import StatusTag from '../../StatusTag.vue'

defineProps<{
  items: RebateWithdrawal[]
  actionIds: Set<number>
  page: { current: number; pageSize: number; total: number }
}>()
defineEmits<{
  pageChange: [page: number]
  approve: [row: RebateWithdrawal]
  reject: [row: RebateWithdrawal]
  retry: [row: RebateWithdrawal]
}>()
</script>

<template>
  <div class="withdrawCards">
    <article v-for="row in items" :key="row.id" class="withdrawCard">
      <header>
        <div><strong>{{ row.user_email || `用户 #${row.user_id}` }}</strong><span>申请 ID {{ row.id }}</span></div>
        <StatusTag :status="row.status" />
      </header>
      <dl>
        <div><dt>扣除返利</dt><dd>{{ money(row.amount) }}</dd></div>
        <div><dt>转入 API 额度</dt><dd>{{ money(row.quota_amount) }}</dd></div>
        <div class="wide"><dt>申请单号</dt><dd>{{ row.request_no }}</dd></div>
        <div><dt>申请时间</dt><dd>{{ row.created_at || '-' }}</dd></div>
        <div><dt>处理结果</dt><dd>{{ row.reject_reason || row.error_message || '-' }}</dd></div>
      </dl>
      <footer>
        <template v-if="row.status === 'pending'">
          <a-button type="primary" size="small" :loading="actionIds.has(row.id)" @click="$emit('approve', row)"><template #icon><CheckOutlined /></template>通过</a-button>
          <a-button danger size="small" :disabled="actionIds.has(row.id)" @click="$emit('reject', row)"><template #icon><StopOutlined /></template>拒绝</a-button>
        </template>
        <a-button v-else-if="row.status === 'exception'" size="small" :loading="actionIds.has(row.id)" @click="$emit('retry', row)"><template #icon><ReloadOutlined /></template>重新处理</a-button>
        <span v-else>无需操作</span>
      </footer>
    </article>
    <a-pagination
      v-if="page.total > page.pageSize"
      size="small"
      :current="page.current"
      :page-size="page.pageSize"
      :total="page.total"
      :show-size-changer="false"
      @change="$emit('pageChange', $event)"
    />
  </div>
</template>

<style scoped>
.withdrawCards { display: flex; flex-direction: column; gap: 12px; }
.withdrawCard { padding: 14px; border: 1px solid var(--rebate-border); border-radius: 8px; background: var(--rebate-low); }
.withdrawCard header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.withdrawCard header div,
.withdrawCard header strong,
.withdrawCard header span { display: block; min-width: 0; }
.withdrawCard header strong { overflow-wrap: anywhere; color: var(--rebate-text); font-size: 15px; }
.withdrawCard header div > span { margin-top: 2px; color: var(--rebate-muted); font-size: 12px; }
.withdrawCard dl { display: grid; margin: 14px 0 0; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; }
.withdrawCard dl div { min-width: 0; }
.withdrawCard dl .wide { grid-column: 1 / -1; }
.withdrawCard dt { color: var(--rebate-muted); font-size: 11px; line-height: 17px; }
.withdrawCard dd { margin: 1px 0 0; overflow-wrap: anywhere; color: var(--rebate-text); font-size: 13px; font-weight: 600; line-height: 19px; }
.withdrawCard footer { display: flex; min-height: 36px; margin-top: 14px; padding-top: 12px; align-items: center; gap: 8px; border-top: 1px solid var(--rebate-border); color: var(--rebate-muted); font-size: 12px; }
.withdrawCards :deep(.ant-pagination) { align-self: center; margin-top: 4px; }
</style>
