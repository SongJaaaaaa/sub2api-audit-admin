<script setup lang="ts">
import type { RebateConfigInput } from '../../../types'
import ConfigCard from './ConfigCard.vue'

const form = defineModel<RebateConfigInput>({ required: true })
</script>

<template>
  <ConfigCard
    section-id="withdraw-config"
    title="提现配置"
    description="控制返利转入 Sub2API API 额度的金额与频率。"
  >
    <a-form layout="vertical" :model="form">
      <div class="configFormGrid">
        <a-form-item label="单次最低提现额" required>
          <a-input v-model:value="form.withdraw_min_amount" inputmode="decimal" addon-after="CNY" />
        </a-form-item>
        <a-form-item label="每日提现次数" required>
          <a-input-number v-model:value="form.withdraw_daily_limit" :min="1" :precision="0" style="width: 100%" addon-after="次" />
        </a-form-item>
        <a-form-item label="每日提现总金额（0 为不限）" required>
          <a-input v-model:value="form.withdraw_daily_amount_limit" inputmode="decimal" addon-after="CNY" />
        </a-form-item>
        <a-form-item label="API 额度换算比例" required>
          <a-input v-model:value="form.withdraw_to_api_quota_rate" inputmode="decimal" addon-after="倍" />
        </a-form-item>
      </div>
    </a-form>
  </ConfigCard>
</template>
