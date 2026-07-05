<script setup lang="ts">
defineProps<{
  value: string
}>()

const emit = defineEmits<{
  'update:value': [value: string]
}>()
</script>

<template>
  <a-textarea
    :value="value"
    :rows="5"
    placeholder="可填写说明，支持常用 HTML 标签；脚本会在保存时过滤"
    @update:value="emit('update:value', $event)"
  />
</template>
