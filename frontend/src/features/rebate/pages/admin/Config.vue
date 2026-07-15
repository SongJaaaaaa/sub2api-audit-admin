<script setup lang="ts">
import { SaveOutlined } from '@ant-design/icons-vue'
import { message } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { getRebateConfig, updateRebateConfig } from '../../api/admin'
import AsyncState from '../../components/AsyncState.vue'
import PageHeader from '../../components/PageHeader.vue'
import type { RebateConfig, RebateConfigInput } from '../../types'

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const cutoverAt = ref<string | null>(null)
const updatedAt = ref<string | null>(null)
const form = reactive<RebateConfigInput>({
  milestone_amount: '100.00',
  milestone_reward_amount: '15.00',
  milestone_max_times: 2,
  stage_amount: '100.00',
  stage_reward_amount: '15.00',
  withdraw_min_amount: '2.00',
  withdraw_daily_limit: 10,
  withdraw_daily_amount_limit: '0.00',
  withdraw_to_api_quota_rate: '1.00',
  native_recharge_enabled: true,
  redeem_enabled: true,
  admin_adjust_enabled: false,
})

function apiMessage(err: unknown, fallback: string) {
  return (err as { response?: { data?: { message?: string } } }).response?.data?.message || fallback
}

function assign(data: RebateConfig) {
  Object.assign(form, {
    milestone_amount: data.milestone_amount,
    milestone_reward_amount: data.milestone_reward_amount,
    milestone_max_times: data.milestone_max_times,
    stage_amount: data.stage_amount,
    stage_reward_amount: data.stage_reward_amount,
    withdraw_min_amount: data.withdraw_min_amount,
    withdraw_daily_limit: data.withdraw_daily_limit,
    withdraw_daily_amount_limit: data.withdraw_daily_amount_limit,
    withdraw_to_api_quota_rate: data.withdraw_to_api_quota_rate,
    native_recharge_enabled: data.native_recharge_enabled,
    redeem_enabled: data.redeem_enabled,
    admin_adjust_enabled: data.admin_adjust_enabled,
  })
  cutoverAt.value = data.rebate_cutover_at
  updatedAt.value = data.updated_at
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    assign(await getRebateConfig())
  } catch (err) {
    error.value = apiMessage(err, '读取返利配置失败')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const res = await updateRebateConfig({ ...form })
    assign(res)
    message.success(res.message || '返利配置已保存')
  } catch (err) {
    message.error(apiMessage(err, '保存返利配置失败'))
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="rebatePage">
    <PageHeader title="返利配置">
      <template #actions>
        <a-button type="primary" :loading="saving" :disabled="loading" @click="save">
          <template #icon><SaveOutlined /></template>
          保存
        </a-button>
      </template>
    </PageHeader>

    <AsyncState :loading="loading" :error="error" @retry="load">
      <section class="rebateSection">
        <div class="rebateSectionHeader"><h2>一级返利规则</h2></div>
        <a-form layout="vertical" :model="form">
          <div class="rebateFormGrid">
            <a-form-item label="初始累充门槛" name="milestone_amount" required>
              <a-input v-model:value="form.milestone_amount" inputmode="decimal" addon-after="元" />
            </a-form-item>
            <a-form-item label="初始每次奖励" name="milestone_reward_amount" required>
              <a-input v-model:value="form.milestone_reward_amount" inputmode="decimal" addon-after="元" />
            </a-form-item>
            <a-form-item label="初始最多奖励次数" name="milestone_max_times" required>
              <a-input-number v-model:value="form.milestone_max_times" :min="0" :precision="0" style="width: 100%" addon-after="次" />
            </a-form-item>
            <a-form-item label="后续累充门槛" name="stage_amount" required>
              <a-input v-model:value="form.stage_amount" inputmode="decimal" addon-after="元" />
            </a-form-item>
            <a-form-item label="后续每次奖励" name="stage_reward_amount" required>
              <a-input v-model:value="form.stage_reward_amount" inputmode="decimal" addon-after="元" />
            </a-form-item>
          </div>
        </a-form>
      </section>

      <section class="rebateSection">
        <div class="rebateSectionHeader"><h2>提现规则</h2></div>
        <a-form layout="vertical" :model="form">
          <div class="rebateFormGrid">
            <a-form-item label="单次最低提现" name="withdraw_min_amount" required>
              <a-input v-model:value="form.withdraw_min_amount" inputmode="decimal" addon-after="元" />
            </a-form-item>
            <a-form-item label="每日申请次数" name="withdraw_daily_limit" required>
              <a-input-number v-model:value="form.withdraw_daily_limit" :min="0" :precision="0" style="width: 100%" addon-after="次" />
            </a-form-item>
            <a-form-item label="每日申请总金额" name="withdraw_daily_amount_limit" required>
              <a-input v-model:value="form.withdraw_daily_amount_limit" inputmode="decimal" addon-after="元" />
            </a-form-item>
            <a-form-item label="返利到 API 额度换算比例" name="withdraw_to_api_quota_rate" required>
              <a-input v-model:value="form.withdraw_to_api_quota_rate" inputmode="decimal" addon-after="倍" />
            </a-form-item>
          </div>
        </a-form>
      </section>

      <section class="rebateSection">
        <div class="rebateSectionHeader"><h2>返利来源</h2></div>
        <a-descriptions bordered :column="1" size="middle">
          <a-descriptions-item label="Sub2API 原生充值">
            <a-switch v-model:checked="form.native_recharge_enabled" />
          </a-descriptions-item>
          <a-descriptions-item label="Sub2API 兑换">
            <a-switch v-model:checked="form.redeem_enabled" />
          </a-descriptions-item>
          <a-descriptions-item label="Sub2API 后台调额">
            <a-switch v-model:checked="form.admin_adjust_enabled" />
          </a-descriptions-item>
          <a-descriptions-item label="数据切换时间">{{ cutoverAt || '--' }}</a-descriptions-item>
          <a-descriptions-item label="最后更新">{{ updatedAt || '--' }}</a-descriptions-item>
        </a-descriptions>
      </section>
    </AsyncState>
  </div>
</template>
