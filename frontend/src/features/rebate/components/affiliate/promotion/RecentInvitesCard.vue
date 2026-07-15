<script setup lang="ts">
import type { TeamMember } from '../../../types'
import { money } from '../../../utils/money'
import AsyncState from '../../AsyncState.vue'
import AppCard from '../../core/AppCard.vue'

defineProps<{
  items: TeamMember[]
  total: number
}>()
</script>

<template>
  <AppCard>
    <div class="rebateSectionHeader">
      <h2>邀请记录</h2>
      <span class="rebateMuted">共 {{ total }} 人</span>
    </div>
    <AsyncState :empty="items.length === 0" empty-text="暂无邀请记录">
      <div class="rebateTable">
        <a-table row-key="user_id" size="middle" :data-source="items" :pagination="false" :scroll="{ x: 720 }">
          <a-table-column title="用户" key="user" :width="260">
            <template #default="{ record }">
              <div class="rebateUserCell">
                <strong>{{ record.email || record.username || `用户 #${record.user_id}` }}</strong>
                <span>{{ record.username || `ID ${record.user_id}` }}</span>
              </div>
            </template>
          </a-table-column>
          <a-table-column title="累计充值" key="recharge" align="right" :width="140">
            <template #default="{ record }">{{ money(record.total_recharge_amount) }}</template>
          </a-table-column>
          <a-table-column title="产生返利" key="rebate" align="right" :width="140">
            <template #default="{ record }"><span class="rebateAmount">{{ money(record.total_rebate_amount) }}</span></template>
          </a-table-column>
          <a-table-column title="加入时间" data-index="joined_at" :width="175" />
        </a-table>
      </div>
    </AsyncState>
  </AppCard>
</template>
