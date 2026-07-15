<script setup lang="ts">
defineProps<{ loading: boolean }>()
const open = defineModel<boolean>('open', { required: true })
const reason = defineModel<string>('reason', { required: true })
defineEmits<{ submit: [] }>()
</script>

<template>
  <a-modal v-model:open="open" title="拒绝提现" ok-text="确认拒绝" cancel-text="取消" :confirm-loading="loading" @ok="$emit('submit')">
    <a-form layout="vertical">
      <a-form-item label="拒绝原因" required>
        <a-textarea v-model:value="reason" :rows="4" :maxlength="500" show-count placeholder="请输入拒绝原因" />
      </a-form-item>
    </a-form>
  </a-modal>
</template>
