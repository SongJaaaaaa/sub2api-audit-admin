<script setup lang="ts">
import { ReloadOutlined } from '@ant-design/icons-vue'

withDefaults(defineProps<{
  loading?: boolean
  error?: string
  empty?: boolean
  emptyText?: string
}>(), { loading: false, error: '', empty: false, emptyText: '暂无记录' })

defineEmits<{ retry: [] }>()
</script>

<template>
  <div v-if="loading" class="appListState appListLoading" role="status">正在加载…</div>
  <div v-else-if="error" class="appListState appListError" role="alert">
    <p>{{ error }}</p>
    <button class="appSecondaryButton" type="button" @click="$emit('retry')"><ReloadOutlined /> 重试</button>
  </div>
  <div v-else-if="empty" class="appListState appListEmpty">{{ emptyText }}</div>
</template>
