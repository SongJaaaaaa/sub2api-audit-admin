<script setup lang="ts">
import type { RebateConfigInput } from '../../../types'
import { money } from '../../../utils/money'
import ConfigCard from './ConfigCard.vue'

const form = defineModel<RebateConfigInput>({ required: true })
</script>

<template>
  <ConfigCard
    section-id="stage-config"
    title="后续台阶配置"
    description="里程碑次数完成后，继续按累充台阶奖励直接上级。"
  >
    <template #badge><span class="enabledBadge">仅一级</span></template>
    <a-form layout="vertical" :model="form">
      <div class="configFormGrid">
        <a-form-item label="后续累充门槛" required>
          <a-input v-model:value="form.stage_amount" inputmode="decimal" addon-after="元" />
        </a-form-item>
        <a-form-item label="每次奖励金额" required>
          <a-input v-model:value="form.stage_reward_amount" inputmode="decimal" addon-after="元" />
        </a-form-item>
      </div>
    </a-form>
    <div class="configHint">
      <strong>一级返利说明</strong>
      里程碑完成后，每再累充满 {{ money(form.stage_amount) }}，只给直接上级奖励 {{ money(form.stage_reward_amount) }}，不会向更高层级分配。
    </div>
  </ConfigCard>
</template>
