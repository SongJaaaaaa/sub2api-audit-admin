<script setup lang="ts">
import { WalletOutlined } from '@ant-design/icons-vue'
import type { WithdrawConfig } from '../../../types'
import { compareMoney, money } from '../../../utils/money'
import AppCard from '../../core/AppCard.vue'

defineProps<{
  config: WithdrawConfig
  expectedQuota: string
  creating: boolean
}>()
defineEmits<{ submit: [] }>()
const amount = defineModel<string>({ required: true })
</script>

<template>
  <AppCard class="withdrawApplyCard">
    <h2>返利余额处理</h2>
    <ul class="withdrawTips">
      <li>单次最低提现 {{ money(config.min_amount) }}</li>
      <li>每日最多提现 {{ config.daily_limit === 0 ? '不限' : `${config.daily_limit} 次` }}</li>
      <li>返利余额只可转入 Sub2API API 额度</li>
    </ul>

    <a-tabs active-key="api-quota" :animated="false">
      <a-tab-pane key="api-quota" tab="转入 API 额度">
        <div class="quotaTarget">
          <span>转入目标</span>
          <strong>Sub2API 账户额度 · 1 : {{ config.to_api_quota_rate }}</strong>
          <small>本次预计到账 {{ expectedQuota }}</small>
        </div>

        <a-form layout="vertical" @finish="$emit('submit')">
          <a-form-item label="提现金额" required>
            <a-input v-model:value="amount" size="large" inputmode="decimal" placeholder="请输入金额，如 100.00" addon-after="元" />
          </a-form-item>
          <a-form-item label="预计到账额度">
            <a-input :value="expectedQuota" size="large" readonly />
          </a-form-item>
          <a-button type="primary" html-type="submit" size="large" block :loading="creating" :disabled="!amount.trim()">
            <template #icon><WalletOutlined /></template>
            提交申请
          </a-button>
        </a-form>
      </a-tab-pane>
    </a-tabs>

    <dl class="withdrawPolicy">
      <div><dt>每日总额</dt><dd>{{ compareMoney(config.daily_amount_limit, '0') === 0 ? '不限' : money(config.daily_amount_limit) }}</dd></div>
      <div><dt>到账方式</dt><dd>Sub2API API 额度</dd></div>
    </dl>
  </AppCard>
</template>

<style scoped>
.withdrawApplyCard { min-width: 0; }
.withdrawApplyCard h2 { margin: 0 0 16px; color: var(--rebate-text); font-size: 18px; line-height: 26px; }
.withdrawTips { margin: 0 0 16px; padding: 12px 14px 12px 28px; border-radius: 8px; background: var(--rebate-low); color: var(--rebate-muted); font-size: 12px; line-height: 21px; }
.quotaTarget { margin-bottom: 18px; padding: 14px; border: 1px solid var(--rebate-border); border-radius: 8px; }
.quotaTarget span,
.quotaTarget strong,
.quotaTarget small { display: block; }
.quotaTarget span { color: var(--rebate-muted); font-size: 11px; font-weight: 600; }
.quotaTarget strong { margin-top: 4px; color: var(--rebate-text); font-size: 14px; }
.quotaTarget small { margin-top: 6px; color: var(--rebate-muted); font-size: 12px; }
.withdrawApplyCard :deep(.ant-btn-primary:disabled) { border-color: #999ae9; background: #999ae9; color: #fff; }
.withdrawPolicy { display: grid; margin: 20px 0 0; padding-top: 12px; gap: 8px; border-top: 1px solid var(--rebate-border); }
.withdrawPolicy > div { display: flex; justify-content: space-between; gap: 12px; }
.withdrawPolicy dt { color: var(--rebate-muted); font-size: 12px; }
.withdrawPolicy dd { margin: 0; color: var(--rebate-text); font-size: 12px; font-weight: 600; text-align: right; }
</style>
