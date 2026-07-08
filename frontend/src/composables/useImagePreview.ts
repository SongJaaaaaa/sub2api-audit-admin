import { ref } from 'vue'

/**
 * 为 safeHtml 区域中的 <img> 提供点击放大预览能力
 * 使用方式：
 *   const { previewSrc, previewOpen, onSafeHtmlClick } = useImagePreview()
 *   <div class="safeHtml" v-html="..." @click="onSafeHtmlClick" />
 *   <a-modal v-model:open="previewOpen" :footer="null" centered>
 *     <img :src="previewSrc" style="max-width:100%;max-height:80vh;" />
 *   </a-modal>
 */
export function useImagePreview() {
  const previewSrc = ref('')
  const previewOpen = ref(false)

  function onSafeHtmlClick(event: MouseEvent) {
    const target = event.target as HTMLElement
    if (target.tagName === 'IMG') {
      previewSrc.value = (target as HTMLImageElement).src
      previewOpen.value = true
    }
  }

  return { previewSrc, previewOpen, onSafeHtmlClick }
}
