<script setup lang="ts">
import { BulbOutlined, LockOutlined, ReloadOutlined, ZoomInOutlined, ZoomOutOutlined } from '@ant-design/icons-vue'
import { nextTick, ref, watch } from 'vue'
import type { TeamMember } from '../../../types'
import { useGraphCanvas } from '../../../composables/useGraphCanvas'
import TeamMemberNode from './TeamMemberNode.vue'

const props = defineProps<{
  rootName: string
  rootMeta: string
  pageRecharge: string
  items: TeamMember[]
  page: { current: number; pageSize: number; total: number }
}>()
const emit = defineEmits<{ page: [current: number, pageSize: number] }>()
const graph = useGraphCanvas()
const canvasEl = ref<HTMLElement | null>(null)

async function centerCanvas() {
  await nextTick()
  if (!canvasEl.value) return
  canvasEl.value.scrollLeft = (canvasEl.value.scrollWidth - canvasEl.value.clientWidth) / 2
}

function resetView() {
  graph.resetView()
  void centerCanvas()
}

watch([() => props.items, () => props.page.current], centerCanvas, { immediate: true, flush: 'post' })

function changePage(current: number, pageSize: number) {
  emit('page', current, pageSize)
}

</script>

<template>
  <section class="teamGraph">
    <div class="teamGraphTools">
      <p><BulbOutlined /> 拖拽画布平移视图，滚轮缩放，点击按钮恢复视图</p>
      <div>
        <a-button shape="circle" size="small" aria-label="缩小" @click="graph.zoomOut"><template #icon><ZoomOutOutlined /></template></a-button>
        <strong>{{ Math.round(graph.canvas.scale * 100) }}%</strong>
        <a-button shape="circle" size="small" aria-label="放大" @click="graph.zoomIn"><template #icon><ZoomInOutlined /></template></a-button>
        <a-button shape="circle" size="small" aria-label="恢复视图" @click="resetView"><template #icon><ReloadOutlined /></template></a-button>
      </div>
    </div>

    <div
      class="teamCanvas"
      ref="canvasEl"
      :class="{ dragging: graph.canvas.dragging }"
      @wheel.prevent="graph.onWheel"
      @pointerdown="graph.onPointerDown"
      @pointermove="graph.onPointerMove"
      @pointerup="graph.onPointerUp"
      @pointercancel="graph.onPointerUp"
      @pointerleave="graph.onPointerUp"
    >
      <div class="teamStage" :style="graph.transformStyle.value">
        <div class="teamTree">
          <article class="teamRootNode">
            <span>我</span>
            <div><strong :title="rootName">{{ rootName }}（我）</strong><small :title="rootMeta">{{ rootMeta }}</small><small>直邀 {{ page.total }} 人 · 本页充值 {{ pageRecharge }}</small></div>
          </article>

          <template v-if="items.length">
            <div class="rootStem" />
            <div class="childBranch" :class="{ single: items.length === 1 }">
              <TeamMemberNode v-for="member in items" :key="member.user_id" :member="member" />
            </div>
          </template>
          <div v-else class="emptyNode">暂无直接下级</div>
        </div>
      </div>
    </div>

    <a-pagination
      v-if="page.total > page.pageSize"
      class="teamPagination"
      size="small"
      :current="page.current"
      :page-size="page.pageSize"
      :total="page.total"
      :show-size-changer="true"
      @change="changePage"
    />

    <div class="teamPrivacy"><LockOutlined /> 隐私说明：你只能查看自己及一级下级的推荐关系，无法查看上级信息。</div>
  </section>
</template>

<style scoped>
.teamGraph { min-width: 0; }
.teamGraphTools { display: flex; min-height: 40px; padding: 0 4px; align-items: center; justify-content: space-between; gap: 16px; }
.teamGraphTools p { margin: 0; color: var(--rebate-muted); font-size: 12px; line-height: 18px; }
.teamGraphTools p .anticon { color: #f5b700; }
.teamGraphTools > div { display: flex; flex: 0 0 auto; align-items: center; gap: 8px; }
.teamGraphTools strong { min-width: 40px; color: var(--rebate-muted); font-size: 12px; text-align: center; }
.teamCanvas { min-height: 400px; overflow: auto; border: 1px solid var(--rebate-border); border-radius: 12px; background: var(--rebate-bg); cursor: grab; touch-action: none; }
.teamCanvas.dragging { cursor: grabbing; user-select: none; }
.teamStage { display: inline-block; min-width: 100%; padding: 32px; }
.teamTree { display: flex; width: max-content; min-width: 100%; align-items: center; flex-direction: column; }
.teamRootNode { display: flex; width: 242px; padding: 12px 16px; align-items: center; gap: 12px; border: 2px solid #4648d4; border-radius: 12px; background: var(--rebate-card); }
.teamRootNode > span { display: inline-flex; width: 40px; height: 40px; flex: 0 0 40px; align-items: center; justify-content: center; border-radius: 50%; background: #4648d4; color: #fff; font-size: 13px; font-weight: 700; }
.teamRootNode > div { display: flex; min-width: 0; flex-direction: column; }
.teamRootNode strong,
.teamRootNode small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.teamRootNode strong { color: var(--rebate-text); font-size: 13px; }
.teamRootNode small { color: var(--rebate-muted); font-size: 10px; line-height: 15px; }
.rootStem { width: 1px; height: 30px; background: #cbd5e1; }
.childBranch { position: relative; display: flex; padding-top: 30px; justify-content: center; gap: 18px; }
.childBranch::before { position: absolute; top: 0; right: 115px; left: 115px; height: 1px; background: #cbd5e1; content: ''; }
.childBranch.single::before { display: none; }
.emptyNode { margin-top: 20px; padding: 7px 12px; border-radius: 8px; background: var(--rebate-low); color: var(--rebate-muted); font-size: 11px; }
.teamPagination { display: flex; margin: 12px 0 0; justify-content: center; }
.teamPrivacy { margin-top: 16px; padding: 11px 16px; border: 1px solid #d5e7ff; border-radius: 8px; background: #edf5ff; color: #0758cf; font-size: 12px; line-height: 19px; }
@media (max-width: 760px) {
  .teamGraphTools { min-height: 48px; align-items: center; gap: 8px; }
  .teamGraphTools p { max-width: 205px; }
  .teamGraphTools > div { gap: 6px; }
  .teamCanvas { min-height: 400px; }
  .teamStage { padding: 32px 18px; }
  .teamPrivacy { padding: 10px 12px; }
}
</style>
