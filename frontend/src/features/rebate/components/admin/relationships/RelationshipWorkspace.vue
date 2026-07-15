<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRelationshipCanvas } from '../../../composables/admin/useRelationshipCanvas'
import type { RelationshipRes, RelationshipUser } from '../../../types'
import RelationshipCanvas from './RelationshipCanvas.vue'
import RelationshipLegend from './RelationshipLegend.vue'
import RelationshipNodeDetail from './RelationshipNodeDetail.vue'
import RelationshipToolbar from './RelationshipToolbar.vue'

const props = defineProps<{
  selectedId?: number
  options: { value: number; label: string }[]
  userLoading: boolean
  loading: boolean
  error: string
  result: RelationshipRes | null
}>()

const emit = defineEmits<{
  search: [keyword: string]
  selectUser: [id?: number]
  load: []
  retry: []
  page: [current: number, pageSize: number]
}>()

const selectedNode = ref<RelationshipUser | null>(null)
const detailOpen = ref(false)
const { canvas, transformStyle, onPointerDown, onPointerMove, onPointerUp, onWheel, resetView, zoomIn, zoomOut } = useRelationshipCanvas()

function openDetail(node: RelationshipUser) {
  selectedNode.value = node
  detailOpen.value = true
}

function changePage(current: number, pageSize: number) {
  emit('page', current, pageSize)
}

watch(() => [props.result?.user.user_id, props.result?.page], () => {
  resetView()
  detailOpen.value = false
})
</script>

<template>
  <RelationshipToolbar
    :selected-id="selectedId"
    :options="options"
    :user-loading="userLoading"
    :loading="loading"
    :scale="canvas.scale"
    @search="emit('search', $event)"
    @select="emit('selectUser', $event)"
    @load="emit('load')"
    @zoom-in="zoomIn"
    @zoom-out="zoomOut"
    @reset="resetView"
  />
  <RelationshipLegend />
  <RelationshipCanvas
    :result="result"
    :loading="loading"
    :error="error"
    :dragging="canvas.dragging"
    :transform-style="transformStyle"
    @retry="emit('retry')"
    @select="openDetail"
    @page="changePage"
    @wheel="onWheel"
    @pointer-down="onPointerDown"
    @pointer-move="onPointerMove"
    @pointer-up="onPointerUp"
  />
  <RelationshipNodeDetail
    :open="detailOpen"
    :node="selectedNode"
    :root-id="result?.user.user_id"
    @close="detailOpen = false"
  />
</template>
