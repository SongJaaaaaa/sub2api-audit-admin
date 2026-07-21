<script setup lang="ts">
import { DownOutlined, LoadingOutlined } from '@ant-design/icons-vue'

withDefaults(defineProps<{
  loading?: boolean
  hasMore?: boolean
  loaded?: number
  total?: number
}>(), { loading: false, hasMore: false, loaded: 0, total: 0 })

defineEmits<{ load: [] }>()
</script>

<template>
  <div class="appLoadMore">
    <button v-if="hasMore" class="appSecondaryButton appLoadMoreButton" type="button" :disabled="loading" @click="$emit('load')">
      <LoadingOutlined v-if="loading" />
      <DownOutlined v-else />
      {{ loading ? '加载中…' : '加载更多' }}
    </button>
    <span v-else-if="total" class="appListEnd">已显示全部 {{ loaded || total }} 条</span>
  </div>
</template>
