<script setup lang="ts">
import type { RebateConfigInput } from '../../../types'
import { money } from '../../../utils/money'
import ConfigCard from './ConfigCard.vue'

const form = defineModel<RebateConfigInput>({ required: true })
</script>

<template>
  <ConfigCard
    section-id="milestone-config"
    title="里程碑配置"
    description="下级累计充值达到固定台阶时，优先给直推上级发放个人奖励。"
  >
    <template #badge><span class="enabledBadge">已启用</span></template>
    <a-form layout="vertical" :model="form">
      <div class="configFormGrid singleTail">
        <a-form-item label="下级累充门槛" required>
          <a-input v-model:value="form.milestone_amount" inputmode="decimal" addon-after="元" />
        </a-form-item>
        <a-form-item label="每次奖励金额" required>
          <a-input v-model:value="form.milestone_reward_amount" inputmode="decimal" addon-after="元" />
        </a-form-item>
        <a-form-item label="最多奖励次数" required>
          <a-input-number v-model:value="form.milestone_max_times" :min="1" :precision="0" style="width: 100%" addon-after="次" />
        </a-form-item>
      </div>
    </a-form>
    <div class="configHint">
      <strong>当前配置示例</strong>
      下级每累充满 {{ money(form.milestone_amount) }}，直推上级奖励 {{ money(form.milestone_reward_amount) }}；最多触发
      {{ form.milestone_max_times }} 次。
    </div>
  </ConfigCard>
</template>
