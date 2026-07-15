<script setup lang="ts">
import { useRouter } from 'vue-router'
import type { RebateRecord } from '../../../types'
import { money } from '../../../utils/money'
import AsyncState from '../../AsyncState.vue'
import AppCard from '../../core/AppCard.vue'

defineProps<{
  items: RebateRecord[]
}>()

const router = useRouter()

function typeText(type: RebateRecord['type']) {
  if (type === 'milestone') return '初始里程碑'
  if (type === 'stage') return '后续台阶'
  return '历史期初'
}
</script>

<template>
  <AppCard>
    <div class="rebateSectionHeader">
      <h2>最近返利</h2>
      <a-button type="link" @click="router.push('/affiliate/rebates')">查看全部</a-button>
    </div>
    <AsyncState :empty="items.length === 0" empty-text="暂无返利明细">
      <div class="rebateTable">
        <a-table row-key="id" size="middle" :data-source="items" :pagination="false" :scroll="{ x: 680 }">
          <a-table-column title="时间" data-index="created_at" :width="175" />
          <a-table-column title="来源用户" key="payer" :width="240">
            <template #default="{ record }">{{ record.payer_email || `用户 #${record.payer_user_id}` }}</template>
          </a-table-column>
          <a-table-column title="类型" key="type" :width="130">
            <template #default="{ record }">{{ typeText(record.type) }}</template>
          </a-table-column>
          <a-table-column title="返利金额" key="amount" align="right" :width="140">
            <template #default="{ record }"><span class="rebateAmount">+{{ money(record.rebate_amount) }}</span></template>
          </a-table-column>
        </a-table>
      </div>
    </AsyncState>
  </AppCard>
</template>
