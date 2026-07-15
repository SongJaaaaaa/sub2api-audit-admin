<script setup lang="ts">
import { onMounted } from 'vue'
import ConfigWorkspace from '../../components/admin/config/ConfigWorkspace.vue'
import AsyncState from '../../components/AsyncState.vue'
import { useRebateConfig } from '../../composables/admin/useRebateConfig'

const { loading, saving, error, form, cutoverAt, updatedAt, load, save } = useRebateConfig()

onMounted(load)
</script>

<template>
  <div class="rebatePage configPage">
    <AsyncState :loading="loading" :error="error" @retry="load">
      <ConfigWorkspace
        v-model="form"
        :saving="saving"
        :disabled="loading"
        :cutover-at="cutoverAt"
        :updated-at="updatedAt"
        @save="save"
      />
    </AsyncState>
  </div>
</template>

<style scoped>
.configPage { gap: 0; }
</style>
