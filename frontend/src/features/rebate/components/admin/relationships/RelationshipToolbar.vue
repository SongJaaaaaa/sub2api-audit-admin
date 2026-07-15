<script setup lang="ts">
import { AimOutlined, SearchOutlined, ZoomInOutlined, ZoomOutOutlined } from '@ant-design/icons-vue'

defineProps<{
  selectedId?: number
  options: { value: number; label: string }[]
  userLoading: boolean
  loading: boolean
  scale: number
}>()

const emit = defineEmits<{
  search: [keyword: string]
  select: [id?: number]
  load: []
  zoomIn: []
  zoomOut: []
  reset: []
}>()

function select(value: unknown) {
  emit('select', typeof value === 'number' ? value : undefined)
}
</script>

<template>
  <div class="relationshipToolbar">
    <div class="relationshipSearch">
      <a-select
        aria-label="选择 Sub2API 用户"
        :value="selectedId"
        show-search
        allow-clear
        :filter-option="false"
        :options="options"
        :loading="userLoading"
        placeholder="搜索邮箱、用户名或用户 ID"
        @search="emit('search', $event)"
        @change="select"
      />
      <a-button type="primary" :disabled="!selectedId" :loading="loading" @click="emit('load')">
        <template #icon><SearchOutlined /></template>
        查看关系
      </a-button>
    </div>

    <div class="zoomControls">
      <a-tooltip title="缩小">
        <a-button shape="circle" size="small" aria-label="缩小" @click="emit('zoomOut')"><ZoomOutOutlined /></a-button>
      </a-tooltip>
      <span>{{ Math.round(scale * 100) }}%</span>
      <a-tooltip title="放大">
        <a-button shape="circle" size="small" aria-label="放大" @click="emit('zoomIn')"><ZoomInOutlined /></a-button>
      </a-tooltip>
      <a-tooltip title="重置视图">
        <a-button shape="circle" size="small" aria-label="重置视图" @click="emit('reset')"><AimOutlined /></a-button>
      </a-tooltip>
    </div>
  </div>
</template>

<style scoped>
.relationshipToolbar {
  display: flex;
  min-width: 0;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.relationshipSearch {
  display: grid;
  width: min(100%, 520px);
  min-width: 0;
  grid-template-columns: minmax(220px, 1fr) auto;
  gap: 12px;
}

.zoomControls {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 8px;
}

.zoomControls > span {
  min-width: 42px;
  color: var(--rebate-muted);
  font-size: 12px;
  font-weight: 600;
  text-align: center;
}

@media (max-width: 760px) {
  .relationshipToolbar {
    align-items: stretch;
    flex-direction: column;
  }

  .relationshipSearch {
    width: 100%;
    grid-template-columns: 1fr;
  }

  .zoomControls {
    justify-content: flex-end;
  }
}
</style>
