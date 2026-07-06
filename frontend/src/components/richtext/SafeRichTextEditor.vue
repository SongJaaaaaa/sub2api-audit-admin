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

watch(
  () => props.value,
  (val) => {
    if (editor.value && editor.value.innerHTML !== val) {
      editor.value.innerHTML = val || ''
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

async function beforeUpload(file: File) {
  if (!file.type.startsWith('image/')) {
    message.warning('只支持上传图片')
    return false
  }
  if (file.size > 512 * 1024) {
    message.warning('图片不能超过 512KB')
    return false
  }

  const reader = new FileReader()
  reader.onload = () => {
    editor.value?.focus()
    document.execCommand('insertImage', false, String(reader.result || ''))
    sync()
  }
  reader.readAsDataURL(file)

  return false
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
      contenteditable="true"
      data-placeholder="可输入备注、调整字体样式，也可插入图片..."
      @input="sync"
      @blur="sync"
    ></div>
  </div>
</template>
