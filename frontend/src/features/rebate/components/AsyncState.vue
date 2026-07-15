<script setup lang="ts">
defineProps<{
  loading?: boolean
  error?: string
  empty?: boolean
  emptyText?: string
}>()

defineEmits<{ retry: [] }>()
</script>

<template>
  <a-skeleton v-if="loading" active :paragraph="{ rows: 4 }" />
  <a-alert v-else-if="error" type="error" show-icon :message="error">
    <template #action>
      <a-button size="small" @click="$emit('retry')">重试</a-button>
    </template>
  </a-alert>
  <a-empty v-else-if="empty" :description="emptyText || '暂无数据'" />
  <slot v-else />
</template>
