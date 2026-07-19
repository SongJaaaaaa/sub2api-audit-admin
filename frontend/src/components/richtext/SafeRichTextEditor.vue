<script setup lang="ts">
import {
  BoldOutlined,
  ItalicOutlined,
  OrderedListOutlined,
  PictureOutlined,
  UnderlineOutlined,
  UnorderedListOutlined,
} from '@ant-design/icons-vue'
import { message } from 'ant-design-vue'
import { onMounted, ref, watch } from 'vue'

const props = defineProps<{
  value: string
}>()

const emit = defineEmits<{
  'update:value': [value: string]
}>()

const editor = ref<HTMLElement | null>(null)
const previewSrc = ref('')
const previewOpen = ref(false)
const isDragging = ref(false)
const maxImageSize = 2 * 1024 * 1024
let dragDepth = 0

watch(
  () => props.value,
  (val) => {
    if (!editor.value) return
    // 值为空时强制清除（innerHTML 可能残留 <br>）
    if (!val) {
      editor.value.innerHTML = ''
      return
    }
    if (editor.value.innerHTML !== val) {
      editor.value.innerHTML = val
    }
  },
  { immediate: true },
)

onMounted(() => {
  if (editor.value) editor.value.innerHTML = props.value || ''
})

function sync() {
  emit('update:value', editor.value?.innerHTML || '')
}

function run(cmd: string) {
  editor.value?.focus()
  document.execCommand(cmd)
  sync()
}

// 点击编辑区内图片时预览放大，不干扰文字编辑
function onEditorClick(e: MouseEvent) {
  const target = e.target as HTMLElement
  if (target.tagName === 'IMG') {
    e.preventDefault()
    previewSrc.value = (target as HTMLImageElement).src
    previewOpen.value = true
  }
}

function validImages(files: File[]) {
  const images = files.filter((file) => file.type.startsWith('image/'))
  if (images.length !== files.length) {
    message.warning('只支持上传图片')
  }
  if (images.some((file) => file.size > maxImageSize)) {
    message.warning('图片不能超过 2MB')
  }

  return images.filter((file) => file.size <= maxImageSize)
}

function readImage(file: File) {
  return new Promise<string>((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result || ''))
    reader.onerror = () => reject(reader.error)
    reader.readAsDataURL(file)
  })
}

async function insertImages(files: File[], range?: Range | null) {
  const images = validImages(files)
  if (!images.length) return

  try {
    const sources = await Promise.all(images.map(readImage))
    if (!editor.value) return

    editor.value.focus()
    if (range && editor.value.contains(range.commonAncestorContainer)) {
      const selection = window.getSelection()
      selection?.removeAllRanges()
      selection?.addRange(range)
    }
    sources.forEach((src) => document.execCommand('insertImage', false, src))
    sync()
  } catch (err) {
    console.error('读取富文本图片失败', err)
    message.error('图片读取失败')
  }
}

function beforeUpload(file: File) {
  void insertImages([file])
  return false
}

function hasFiles(e: DragEvent) {
  return Array.from(e.dataTransfer?.types || []).includes('Files')
}

function onDragEnter(e: DragEvent) {
  if (!hasFiles(e)) return
  e.preventDefault()
  dragDepth += 1
  isDragging.value = true
}

function onDragOver(e: DragEvent) {
  if (!hasFiles(e)) return
  e.preventDefault()
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy'
}

function onDragLeave() {
  if (!isDragging.value) return
  dragDepth = Math.max(0, dragDepth - 1)
  if (!dragDepth) isDragging.value = false
}

function onDrop(e: DragEvent) {
  dragDepth = 0
  isDragging.value = false

  const files = Array.from(e.dataTransfer?.files || [])
  if (!files.length) return

  e.preventDefault()
  const range = document.caretRangeFromPoint(e.clientX, e.clientY)
  void insertImages(files, range)
}
</script>

<template>
  <div class="richEditor">
    <div class="richToolbar">
      <a-button size="small" title="加粗" @click="run('bold')"><BoldOutlined /></a-button>
      <a-button size="small" title="斜体" @click="run('italic')"><ItalicOutlined /></a-button>
      <a-button size="small" title="下划线" @click="run('underline')"><UnderlineOutlined /></a-button>
      <a-button size="small" title="无序列表" @click="run('insertUnorderedList')"><UnorderedListOutlined /></a-button>
      <a-button size="small" title="有序列表" @click="run('insertOrderedList')"><OrderedListOutlined /></a-button>
      <a-upload :before-upload="beforeUpload" :show-upload-list="false" accept="image/*">
        <a-button size="small" title="插入图片"><PictureOutlined /></a-button>
      </a-upload>
    </div>
    <div
      ref="editor"
      class="richBody"
      :class="{ richBodyDragging: isDragging }"
      contenteditable="true"
      data-placeholder="可输入备注、调整字体样式，也可插入图片..."
      @input="sync"
      @blur="sync"
      @click="onEditorClick"
      @dragenter="onDragEnter"
      @dragover="onDragOver"
      @dragleave="onDragLeave"
      @drop="onDrop"
    ></div>

    <!-- 编辑器内图片放大预览 -->
    <a-modal v-model:open="previewOpen" :footer="null" centered :body-style="{ textAlign: 'center', padding: '8px' }">
      <img :src="previewSrc" style="max-width:100%;max-height:80vh;border-radius:6px;" />
    </a-modal>
  </div>
</template>
