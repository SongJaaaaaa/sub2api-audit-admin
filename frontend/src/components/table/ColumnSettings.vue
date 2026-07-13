<script setup lang="ts">
import { SettingOutlined } from '@ant-design/icons-vue'
import { computed } from 'vue'

export interface ColumnOption {
  key: string
  title: string
  required?: boolean
}

const props = defineProps<{
  options: ColumnOption[]
  value: string[]
  width: number
}>()

const emit = defineEmits<{
  'update:value': [value: string[]]
  'update:width': [value: number]
  reset: []
}>()

function changeWidth(val: number | null) {
  emit('update:width', Number(val || 1200))
}

const checked = computed({
  get: () => props.value,
  set: (val: string[]) => {
    emit('update:value', val)
  },
})
</script>

<template>
  <a-dropdown trigger="click">
    <a-button>
      <template #icon><SettingOutlined /></template>
      列
    </a-button>
    <template #overlay>
      <div class="columnPanel">
        <div class="columnPanelHead">
          <strong>展示列</strong>
          <a-button type="link" size="small" @click="emit('reset')">恢复默认</a-button>
        </div>
        <a-checkbox-group v-model:value="checked">
          <div v-for="item in options" :key="item.key" class="columnCheck">
            <a-checkbox :value="item.key" >{{ item.title }}</a-checkbox>
          </div>
        </a-checkbox-group>
        <div class="columnWidth">
          <span>表格宽度</span>
          <a-input-number :value="width" :min="600" :max="4000" :step="100" @change="changeWidth" />
        </div>
      </div>
    </template>
  </a-dropdown>
</template>
