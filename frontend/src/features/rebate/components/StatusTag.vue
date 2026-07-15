<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ status: string }>()

const info = computed(() => ({
  pending: { text: '待审核', color: 'orange' },
  processing: { text: '处理中', color: 'blue' },
  succeeded: { text: '已到账', color: 'green' },
  rejected: { text: '已拒绝', color: 'default' },
  exception: { text: '异常', color: 'red' },
  processed: { text: '已发放', color: 'green' },
  active: { text: '正常', color: 'green' },
  disabled: { text: '停用', color: 'default' },
}[props.status] || { text: props.status || '--', color: 'default' }))
</script>

<template>
  <a-tag :color="info.color">{{ info.text }}</a-tag>
</template>
