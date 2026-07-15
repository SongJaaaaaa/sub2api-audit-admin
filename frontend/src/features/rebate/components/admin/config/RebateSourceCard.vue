<script setup lang="ts">
import type { RebateConfigInput } from '../../../types'
import ConfigCard from './ConfigCard.vue'

defineProps<{ cutoverAt: string | null }>()
const form = defineModel<RebateConfigInput>({ required: true })

const sources = [
  { key: 'native_recharge_enabled' as const, label: 'Sub2API 原生充值', note: '已完成的原生余额充值订单' },
  { key: 'redeem_enabled' as const, label: 'Sub2API 兑换', note: '用户完成兑换后产生的余额事件' },
  { key: 'admin_adjust_enabled' as const, label: 'Sub2API 后台调额', note: '返利提现到账与纯赠送调额始终排除' },
]
</script>

<template>
  <ConfigCard section-id="source-config" title="返利来源" description="控制哪些 Sub2API 余额事件进入一级返利计算。">
    <div class="sourceBox">
      <div class="sourceIntro">事件入库后按唯一业务键去重，关闭来源不会影响已经生成的返利记录。</div>
      <label v-for="item in sources" :key="item.key" class="sourceRow">
        <span><strong>{{ item.label }}</strong><small>{{ item.note }}</small></span>
        <a-switch v-model:checked="form[item.key]" />
      </label>
    </div>
    <div class="cutover"><span>数据切换时间</span><strong>{{ cutoverAt || '--' }}</strong></div>
  </ConfigCard>
</template>

<style scoped>
.sourceBox { padding: 12px 16px 0; border: 1px solid var(--rebate-border); border-radius: 8px; background: var(--rebate-low); }
.sourceIntro { padding: 0 0 12px; color: var(--rebate-muted); font-size: 12px; line-height: 19px; }
.sourceRow { display: flex; min-height: 58px; padding: 10px 12px; align-items: center; justify-content: space-between; gap: 20px; border-top: 1px solid var(--rebate-border); border-radius: 8px; background: #fff; }
.sourceRow + .sourceRow { margin-top: 10px; }
.sourceRow > span,
.sourceRow strong,
.sourceRow small { display: block; min-width: 0; }
.sourceRow strong { color: var(--rebate-text); font-size: 13px; line-height: 20px; }
.sourceRow small { margin-top: 2px; color: var(--rebate-muted); font-size: 12px; line-height: 18px; }
.cutover { display: flex; margin-top: 16px; align-items: center; justify-content: space-between; gap: 16px; color: var(--rebate-muted); font-size: 12px; }
.cutover strong { overflow-wrap: anywhere; color: #334155; text-align: right; }

@media (max-width: 760px) {
  .sourceBox { padding: 10px 10px 0; }
  .sourceRow { padding: 10px; align-items: flex-start; }
  .cutover { align-items: flex-start; flex-direction: column; gap: 4px; }
  .cutover strong { text-align: left; }
}
</style>
