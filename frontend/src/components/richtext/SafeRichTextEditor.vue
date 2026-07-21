<script setup lang="ts">
import {
  BoldOutlined,
  ItalicOutlined,
  OrderedListOutlined,
  PictureOutlined,
  UnderlineOutlined,
  UnorderedListOutlined,
} from '@ant-design/icons-vue'
import { App as AntApp } from 'ant-design-vue'
import { onMounted, ref, watch } from 'vue'

const { message } = AntApp.useApp()
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
// 记录编辑区内最后一次光标位置，供失焦后（如点击工具栏上传按钮）插入图片时恢复
let savedRange: Range | null = null

function saveSelection() {
  const selection = window.getSelection()
  if (!selection || selection.rangeCount === 0) return
  const range = selection.getRangeAt(0)
  if (editor.value && editor.value.contains(range.commonAncestorContainer)) {
    savedRange = range.cloneRange()
  }
}

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
  // 恢复失焦前保存的选区，保证在移动端点击工具栏后格式命令作用于原文本
  if (savedRange && editor.value?.contains(savedRange.commonAncestorContainer)) {
    const selection = window.getSelection()
    selection?.removeAllRanges()
    selection?.addRange(savedRange)
  }
  document.execCommand(cmd)
  saveSelection()
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
    const host = editor.value
    if (!host) return

    // 选定插入位置：优先拖拽落点 → 已保存光标 → 内容末尾。
    // 直接用 DOM 插入 <img>，避免 execCommand('insertImage') 在移动端失焦时静默失败。
    let target = range && host.contains(range.commonAncestorContainer) ? range : savedRange
    if (!target || !host.contains(target.commonAncestorContainer)) {
      target = document.createRange()
      target.selectNodeContents(host)
      target.collapse(false)
    }

    sources.forEach((src) => {
      const img = document.createElement('img')
      img.src = src
      target!.insertNode(img)
      // 光标移动到刚插入图片之后，保证多张图片按顺序排列
      target!.setStartAfter(img)
      target!.setEndAfter(img)
    })

    // 更新保存的光标位置并同步内容
    savedRange = target.cloneRange()
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
      <a-tooltip title="加粗"><a-button size="small" @click="run('bold')"><BoldOutlined /></a-button></a-tooltip>
      <a-tooltip title="斜体"><a-button size="small" @click="run('italic')"><ItalicOutlined /></a-button></a-tooltip>
      <a-tooltip title="下划线"><a-button size="small" @click="run('underline')"><UnderlineOutlined /></a-button></a-tooltip>
      <a-tooltip title="无序列表"><a-button size="small" @click="run('insertUnorderedList')"><UnorderedListOutlined /></a-button></a-tooltip>
      <a-tooltip title="有序列表"><a-button size="small" @click="run('insertOrderedList')"><OrderedListOutlined /></a-button></a-tooltip>
      <a-upload :before-upload="beforeUpload" :show-upload-list="false" accept="image/*">
        <a-tooltip title="插入图片"><a-button size="small"><PictureOutlined /></a-button></a-tooltip>
      </a-upload>
    </div>
    <div
      ref="editor"
      class="richBody"
      :class="{ richBodyDragging: isDragging }"
      contenteditable="true"
      data-placeholder="可输入备注、调整字体样式，也可插入图片..."
      @input="sync"
      @blur="() => { saveSelection(); sync() }"
      @keyup="saveSelection"
      @mouseup="saveSelection"
      @touchend="saveSelection"
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
