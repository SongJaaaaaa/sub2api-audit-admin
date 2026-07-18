<script setup lang="ts">
import { useImagePreview } from '../../composables/useImagePreview'

withDefaults(defineProps<{
  value?: string | null
  emptyText?: string
  compact?: boolean
}>(), {
  value: '',
  emptyText: '',
  compact: false,
})

const { previewSrc, previewOpen, onSafeHtmlClick } = useImagePreview()
</script>

<template>
  <div class="safeRichTextDisplay">
    <div
      v-if="value"
      class="safeHtml"
      :class="{ safeHtmlCompact: compact }"
      v-html="value"
      @click="onSafeHtmlClick"
    ></div>
    <span v-else-if="emptyText">{{ emptyText }}</span>

    <a-modal v-model:open="previewOpen" :footer="null" centered :body-style="{ textAlign: 'center', padding: '8px' }">
      <img :src="previewSrc" class="safeRichTextPreview" />
    </a-modal>
  </div>
</template>

<style scoped>
.safeHtmlCompact {
  margin-top: 0;
  padding: 0;
  border: 0;
  background: transparent;
}

.safeRichTextPreview {
  max-width: 100%;
  max-height: 80vh;
  border-radius: 6px;
}
</style>
