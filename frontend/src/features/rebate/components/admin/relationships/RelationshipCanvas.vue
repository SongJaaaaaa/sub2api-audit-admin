<script setup lang="ts">
import { ApartmentOutlined } from '@ant-design/icons-vue'
import type { CSSProperties } from 'vue'
import type { RelationshipRes, RelationshipUser } from '../../../types'
import RelationshipNodeCard from './RelationshipNodeCard.vue'

defineProps<{
  result: RelationshipRes | null
  loading: boolean
  error: string
  dragging: boolean
  transformStyle: CSSProperties
}>()

const emit = defineEmits<{
  retry: []
  select: [node: RelationshipUser]
  page: [current: number, pageSize: number]
  wheel: [event: WheelEvent]
  pointerDown: [event: PointerEvent]
  pointerMove: [event: PointerEvent]
  pointerUp: []
}>()

function changePage(current: number, pageSize: number) {
  emit('page', current, pageSize)
}
</script>

<template>
  <section class="relationshipCanvas" :class="{ dragging }">
    <div
      class="canvasViewport"
      @wheel.prevent="emit('wheel', $event)"
      @pointerdown="emit('pointerDown', $event)"
      @pointermove="emit('pointerMove', $event)"
      @pointerup="emit('pointerUp')"
      @pointercancel="emit('pointerUp')"
      @pointerleave="emit('pointerUp')"
    >
      <a-skeleton v-if="loading" class="canvasState" active :paragraph="{ rows: 6 }" />
      <a-alert v-else-if="error" class="canvasState" type="error" show-icon :message="error">
        <template #action><a-button size="small" @click.stop="emit('retry')">重试</a-button></template>
      </a-alert>

      <div v-else-if="!result" class="canvasEmpty">
        <ApartmentOutlined />
        <h2>选择账号后查看推荐关系</h2>
        <p>搜索并选择具体账号，结果仅展示直接上级归因和一级下级。</p>
      </div>

      <div v-else class="treeStage" :style="transformStyle">
        <div class="relationshipTree">
          <RelationshipNodeCard :node="result.user" root @select="emit('select', $event)" />

          <template v-if="result.items.length">
            <div class="rootStem" />
            <div class="childBranch" :class="{ single: result.items.length === 1 }">
              <div v-for="member in result.items" :key="member.user_id" class="childNode">
                <RelationshipNodeCard :node="member" @select="emit('select', $event)" />
              </div>
            </div>
          </template>
          <div v-else class="noChildren">暂无一级直接下级</div>
        </div>
      </div>
    </div>

    <footer v-if="result && result.total > result.page_size" class="canvasPagination" @pointerdown.stop>
      <span>共 {{ result.total }} 位一级成员</span>
      <a-pagination
        size="small"
        :current="result.page"
        :page-size="result.page_size"
        :total="result.total"
        :show-size-changer="true"
        @change="changePage"
      />
    </footer>
  </section>
</template>

<style scoped>
.relationshipCanvas {
  min-width: 0;
  overflow: hidden;
  border: 1px solid var(--rebate-border);
  border-radius: 12px;
  background: var(--rebate-card);
}

.canvasViewport {
  position: relative;
  min-height: 600px;
  overflow: auto;
  cursor: grab;
  touch-action: none;
}

.dragging .canvasViewport {
  cursor: grabbing;
  user-select: none;
}

.canvasState {
  margin: 48px;
}

.canvasEmpty {
  display: flex;
  min-height: 560px;
  padding: 24px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  color: var(--rebate-muted);
  text-align: center;
}

.canvasEmpty > .anticon {
  color: var(--rebate-border);
  font-size: 54px;
}

.canvasEmpty h2 {
  margin: 16px 0 0;
  color: var(--rebate-muted);
  font-size: 18px;
}

.canvasEmpty p {
  margin: 6px 0 0;
  font-size: 13px;
}

.treeStage {
  display: inline-block;
  min-width: 100%;
  padding: 36px;
  transition: transform 100ms ease;
}

.relationshipTree {
  display: flex;
  width: max-content;
  min-width: 100%;
  align-items: center;
  flex-direction: column;
}

.rootStem {
  width: 1px;
  height: 34px;
  background: #cbd5e1;
}

.childBranch {
  position: relative;
  display: flex;
  padding-top: 34px;
  align-items: flex-start;
  justify-content: center;
  gap: 24px;
}

.childBranch::before {
  position: absolute;
  top: 0;
  right: 110px;
  left: 110px;
  height: 1px;
  background: #cbd5e1;
  content: '';
}

.childBranch.single::before {
  display: none;
}

.childNode {
  position: relative;
}

.childNode::before {
  position: absolute;
  bottom: 100%;
  left: 50%;
  width: 1px;
  height: 34px;
  background: #cbd5e1;
  content: '';
}

.noChildren {
  margin-top: 20px;
  padding: 9px 14px;
  border-radius: 8px;
  background: var(--rebate-low);
  color: var(--rebate-muted);
  font-size: 12px;
}

.canvasPagination {
  display: flex;
  min-height: 56px;
  padding: 10px 16px;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-top: 1px solid var(--rebate-border);
  background: var(--rebate-low);
}

.canvasPagination > span {
  color: var(--rebate-muted);
  font-size: 12px;
}

@media (max-width: 760px) {
  .canvasViewport {
    min-height: 480px;
  }

  .canvasEmpty {
    min-height: 440px;
  }

  .canvasState {
    margin: 24px;
  }

  .treeStage {
    padding: 24px 16px;
  }

  .canvasPagination {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
