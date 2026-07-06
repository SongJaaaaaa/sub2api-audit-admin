<script setup lang="ts">
import { reactive, watch } from 'vue'

export interface AdjustmentFormState {
  operation: 'increment' | 'decrement'
  amount: string
  cash_amount: string
  gift_quota_amount: string
  adjust_reason: string
  admin_notes: string
}

const props = defineProps<{
  value: AdjustmentFormState
}>()

const emit = defineEmits<{
  'update:value': [value: AdjustmentFormState]
}>()

const form = reactive<AdjustmentFormState>({ ...props.value })

watch(
  () => props.value,
  (val) => Object.assign(form, val),
  { deep: true },
)

watch(
  form,
  () => emit('update:value', { ...form }),
  { deep: true },
)
</script>

<template>
  <a-form layout="vertical">
    <a-form-item label="调整方向" required>
      <a-segmented
        v-model:value="form.operation"
        :options="[
          { label: '增加', value: 'increment' },
          { label: '扣减', value: 'decrement' },
        ]"
      />
    </a-form-item>
    <a-form-item label="额度" required>
      <a-input v-model:value="form.amount" placeholder="0.00" />
    </a-form-item>
    <a-form-item label="现金金额">
      <a-input v-model:value="form.cash_amount" placeholder="0.00" />
    </a-form-item>
    <a-form-item label="赠送额度">
      <a-input v-model:value="form.gift_quota_amount" placeholder="0.00" />
    </a-form-item>
    <a-form-item label="原因" required>
      <a-input v-model:value="form.adjust_reason" placeholder="例如 线下充值" />
    </a-form-item>
    <a-form-item label="备注">
      <a-textarea v-model:value="form.admin_notes" :rows="3" />
    </a-form-item>
  </a-form>
</template>
