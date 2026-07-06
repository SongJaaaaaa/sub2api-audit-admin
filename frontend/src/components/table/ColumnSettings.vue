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
}>()

const emit = defineEmits<{
  'update:value': [value: string[]]
}>()

const checked = computed({
  get: () => props.value,
  set: (val: string[]) => {
    const required = props.options.filter((item) => item.required).map((item) => item.key)
    emit('update:value', Array.from(new Set([...required, ...val])))
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
        <a-checkbox-group v-model:value="checked">
          <div v-for="item in options" :key="item.key" class="columnCheck">
            <a-checkbox :value="item.key" :disabled="item.required">{{ item.title }}</a-checkbox>
          </div>
        </a-checkbox-group>
      </div>
    </template>
  </a-dropdown>
</template>
