<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { menuItems } from '../config/menu'

const route = useRoute()
const title = computed(() => menuItems.find((item) => item.path === route.path)?.label || '模块')
</script>

<template>
  <section class="page">
    <div class="pageHead">
      <div>
        <h1>{{ title }}</h1>
        <p>等待对应后端模块完成后接入。</p>
      </div>
    </div>
  </section>
</template>
