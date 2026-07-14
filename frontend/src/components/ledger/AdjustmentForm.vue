<script setup lang="ts">
import { MinusCircleOutlined, PlusCircleOutlined, QuestionCircleOutlined } from '@ant-design/icons-vue'
import { computed, reactive, ref, watch } from 'vue'
import SafeRichTextEditor from '../richtext/SafeRichTextEditor.vue'

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
  currentBalance?: string | number
}>()

const emit = defineEmits<{
  'update:value': [value: AdjustmentFormState]
}>()

const form = reactive<AdjustmentFormState>({ ...props.value })
const cashEdited = ref(false)
const isCorrection = computed(() => form.adjust_reason === '异常修正')
const isRecharge = computed(() => form.operation === 'increment' && form.adjust_reason === '充值')
const isReissue = computed(() => form.operation === 'increment' && form.adjust_reason === '补发')
const showFinance = computed(() => !isCorrection.value && form.operation === 'increment')
const nextBalance = computed(() => {
  const current = Number(props.currentBalance || 0)
  const amount = Number(form.amount || 0)
  const signed = form.operation === 'decrement' ? -amount : amount

  return (current + signed).toFixed(2)
})
const reasonOptions = [
  { label: '充值', value: '充值' },
  { label: '补发', value: '补发' },
  { label: '人工扣减', value: '人工扣减' },
  { label: '异常修正', value: '异常修正' },
]

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

watch(
  () => [form.amount, form.cash_amount, form.adjust_reason, form.operation],
  () => {
    if (isCorrection.value || form.operation === 'decrement') {
      cashEdited.value = false
      form.cash_amount = ''
      form.gift_quota_amount = ''
      return
    }

    if (isReissue.value) {
      cashEdited.value = false
      form.cash_amount = ''
      form.gift_quota_amount = Number(form.amount || 0).toFixed(2)
      return
    }

    if (!isRecharge.value) {
      cashEdited.value = false
      form.cash_amount = ''
      form.gift_quota_amount = ''
      return
    }

    if (!cashEdited.value) {
      form.cash_amount = form.amount
    }

    // 充值：赠送额度 = 调整金额 - 入账金额
    const amount = Number(form.amount || 0)
    const cash = Number(form.cash_amount || 0)
    form.gift_quota_amount = Math.max(amount - cash, 0).toFixed(2)
  },
)

function setOperation(op: 'increment' | 'decrement') {
  cashEdited.value = false
  form.operation = op
  if (op === 'decrement' && form.adjust_reason !== '异常修正') {
    form.adjust_reason = '人工扣减'
  }
  if (op === 'increment' && form.adjust_reason === '人工扣减') {
    form.adjust_reason = '充值'
  }
}

function markCashEdited() {
  cashEdited.value = true
}
</script>

<template>
  <a-form layout="vertical" class="quotaForm">
    <div class="quotaFormGrid">
      <a-form-item label="调整类型" required>
        <div class="adjustTypeGroup">
          <button
            type="button"
            class="adjustTypeBtn plus"
            :class="{ active: form.operation === 'increment' }"
            @click="setOperation('increment')"
          >
            <PlusCircleOutlined />
            充值 (+)
          </button>
          <button
            type="button"
            class="adjustTypeBtn minus"
            :class="{ active: form.operation === 'decrement' }"
            @click="setOperation('decrement')"
          >
            <MinusCircleOutlined />
            扣减 (-)
          </button>
        </div>
      </a-form-item>

      <a-form-item required>
        <template #label>
          <span class="formLabelHint">
            Sub2API 金额调整
            <a-tooltip v-if="isCorrection" title="本次仅调整 Sub2API 额度，不纳入记账">
              <QuestionCircleOutlined />
            </a-tooltip>
          </span>
        </template>
        <a-input v-model:value="form.amount" placeholder="0.00" />
        <div class="quotaAfterBalance">
          调整后 Sub2API 额度
          <strong>{{ nextBalance }}</strong>
        </div>
      </a-form-item>
    </div>

    <a-form-item label="原因类型" required>
      <a-select v-model:value="form.adjust_reason" :options="reasonOptions" />
    </a-form-item>

    <div v-if="showFinance" class="quotaFormGrid">
      <a-form-item v-if="isRecharge" label="入账金额（现金）">
        <a-input v-model:value="form.cash_amount" placeholder="0.00" @input="markCashEdited" />
        <div class="quotaAfterBalance" style="font-size:12px;color:var(--text2);">实收现金金额，剩余计入赠送</div>
      </a-form-item>
      <a-form-item label="赠送额度">
        <a-input :value="form.gift_quota_amount" placeholder="0.00" readonly disabled />
      </a-form-item>
    </div>

    <a-form-item :label="isCorrection ? '备注' : '备注'" :required="isCorrection">
      <SafeRichTextEditor v-model:value="form.admin_notes" />
    </a-form-item>
  </a-form>
</template>
