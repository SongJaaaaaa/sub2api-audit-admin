import { message } from 'ant-design-vue'
import { ref } from 'vue'
import { getRebateConfig, updateRebateConfig } from '../../api/admin'
import type { RebateConfig, RebateConfigInput } from '../../types'
import { apiMessage } from '../../utils/apiError'

function defaults(): RebateConfigInput {
  return {
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
  }
}

function inputOf(data: RebateConfig): RebateConfigInput {
  return {
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
  }
}

export function useRebateConfig() {
  const loading = ref(false)
  const saving = ref(false)
  const error = ref('')
  const form = ref(defaults())
  const cutoverAt = ref<string | null>(null)
  const updatedAt = ref<string | null>(null)

  function assign(data: RebateConfig) {
    form.value = inputOf(data)
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
      const data = await updateRebateConfig({ ...form.value })
      assign(data)
      message.success(data.message || '返利配置已保存')
    } catch (err) {
      message.error(apiMessage(err, '保存返利配置失败'))
    } finally {
      saving.value = false
    }
  }

  return { loading, saving, error, form, cutoverAt, updatedAt, load, save }
}
