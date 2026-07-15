<script setup lang="ts">
import { UploadOutlined } from '@ant-design/icons-vue'
import { message } from 'ant-design-vue'
import { onMounted, ref, watch } from 'vue'
import { downloadAttachment, getAttachments, uploadAttachment, type AttachmentItem } from '../../api/attachments'

const props = defineProps<{
  attachableType: string
  attachableId: number | null
}>()

const loading = ref(false)
const items = ref<AttachmentItem[]>([])
const maxImageSize = 2 * 1024 * 1024

async function loadItems() {
  if (!props.attachableId) {
    items.value = []
    return
  }

  loading.value = true
  try {
    const res = await getAttachments({
      attachable_type: props.attachableType,
      attachable_id: props.attachableId,
    })
    items.value = res.items
  } catch {
    message.error('读取附件失败')
  } finally {
    loading.value = false
  }
}

async function beforeUpload(file: File) {
  if (!props.attachableId) {
    message.warning('请先保存记录后再上传附件')
    return false
  }
  if (file.type.startsWith('image/') && file.size > maxImageSize) {
    message.warning('图片不能超过 2MB')
    return false
  }

  const data = new FormData()
  data.append('attachable_type', props.attachableType)
  data.append('attachable_id', String(props.attachableId))
  data.append('file', file)

  try {
    await uploadAttachment(data)
    message.success('附件已上传')
    loadItems()
  } catch {
    message.error('上传附件失败')
  }

  return false
}

function fileSize(val: number) {
  if (val >= 1024 * 1024) return `${(val / 1024 / 1024).toFixed(1)} MB`
  return `${Math.max(1, Math.round(val / 1024))} KB`
}

async function download(item: AttachmentItem) {
  try {
    const blob = await downloadAttachment(item.id)
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = item.original_name
    link.click()
    URL.revokeObjectURL(url)
  } catch {
    message.error('下载附件失败')
  }
}

watch(() => props.attachableId, loadItems)
onMounted(loadItems)
</script>

<template>
  <div class="attachBox">
    <a-upload :before-upload="beforeUpload" :show-upload-list="false" accept="image/*,.pdf">
      <a-button :disabled="!attachableId">
        <template #icon><UploadOutlined /></template>
        上传附件
      </a-button>
    </a-upload>

    <a-spin :spinning="loading">
      <a-empty v-if="items.length === 0" description="暂无附件" />
      <div v-else class="attachList">
        <button v-for="item in items" :key="item.id" class="attachItem" type="button" @click="download(item)">
          <span>{{ item.original_name }}</span>
          <em>{{ fileSize(item.size) }}</em>
        </button>
      </div>
    </a-spin>
  </div>
</template>
