<script setup lang="ts">
import { computed } from 'vue'
import AdminLayout from './AdminLayout.vue'
import AppLayout from '../app/layouts/AppLayout.vue'
import { useAppMode } from '../app/composables/useAppMode'

const { isAppMode } = useAppMode()
const layout = computed(() => (isAppMode.value ? AppLayout : AdminLayout))
</script>

<template><component :is="layout" /></template>
