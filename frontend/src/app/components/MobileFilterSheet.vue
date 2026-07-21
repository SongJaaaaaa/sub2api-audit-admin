<script setup lang="ts">
import { CloseOutlined, FilterOutlined } from '@ant-design/icons-vue'
import { onBeforeUnmount, watch } from 'vue'

const props = withDefaults(defineProps<{
  open: boolean
  title?: string
  activeCount?: number
}>(), { title: '筛选条件', activeCount: 0 })

const emit = defineEmits<{
  'update:open': [value: boolean]
  reset: []
  apply: []
}>()

function close() { emit('update:open', false) }
function onKeydown(event: KeyboardEvent) { if (event.key === 'Escape' && props.open) close() }
watch(() => props.open, (open) => {
  if (open) document.addEventListener('keydown', onKeydown)
  else document.removeEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition name="appSheet">
      <div v-if="open" class="appSheetBackdrop" @click.self="close">
        <section class="appFilterSheet" role="dialog" aria-modal="true" :aria-label="title">
          <div class="appSheetHead">
            <h2><FilterOutlined /> {{ title }}</h2>
            <button class="appIconButton" type="button" aria-label="关闭筛选" @click="close"><CloseOutlined /></button>
          </div>
          <div class="appSheetBody"><slot /></div>
          <div class="appSheetActions">
            <button class="appSecondaryButton" type="button" @click="emit('reset')">重置</button>
            <button class="appPrimaryButton" type="button" @click="emit('apply'); close()">查询<span v-if="activeCount">（{{ activeCount }}）</span></button>
          </div>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>
