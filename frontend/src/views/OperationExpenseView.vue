<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import dayjs, { type Dayjs } from 'dayjs'
import { computed, onMounted, reactive, ref } from 'vue'
import { createOperationExpense, getOperationExpenses, type OperationExpense } from '../api/finance'
import AttachmentUploader from '../components/attachments/AttachmentUploader.vue'
import SafeRichTextEditor from '../components/richtext/SafeRichTextEditor.vue'
import { useImagePreview } from '../composables/useImagePreview'

const { previewSrc, previewOpen, onSafeHtmlClick } = useImagePreview()
const loading = ref(false)
const submitting = ref(false)
const modalOpen = ref(false)
const drawerOpen = ref(false)
const items = ref<OperationExpense[]>([])
const selected = ref<OperationExpense | null>(null)
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const categoryOptions = [
  { label: '服务器', value: '服务器' },
  { label: '号池', value: '号池' },
  { label: '上游', value: '上游' },
  { label: '返点', value: '返点' },
  { label: '其他', value: '其他' },
]

const filters = reactive({
  category: '',
  dateRange: null as [Dayjs, Dayjs] | null,
})

const form = reactive({
  category: '',
  customCategory: '',
  amount: '',
  paid_at: dayjs().format('YYYY-MM-DD'),
  remark: '',
  content_html: '',
})

const isCustomCategory = computed(() => form.category === '其他')
const finalCategory = computed(() => (isCustomCategory.value ? form.customCategory : form.category))

const columns = [
  { title: '单号', dataIndex: 'expense_no', width: 220 },
  { title: '分类', dataIndex: 'category', width: 120 },
  { title: '金额', dataIndex: 'amount', align: 'right', width: 120 },
  { title: '发生日期', dataIndex: 'paid_at', width: 120 },
  { title: '备注', dataIndex: 'remark' },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
  { title: '操作', dataIndex: 'action', fixed: 'right', width: 90 },
] as const

async function loadItems() {
  loading.value = true
  try {
    const res = await getOperationExpenses({
      page: page.current,
      page_size: page.pageSize,
      category: filters.category,
      from: filters.dateRange?.[0]?.format('YYYY-MM-DD'),
      to: filters.dateRange?.[1]?.format('YYYY-MM-DD'),
    })
    items.value = res.items
    page.total = res.total
  } catch {
    message.error('读取经营账失败')
  } finally {
    loading.value = false
  }
}

function search() {
  page.current = 1
  loadItems()
}

function resetFilters() {
  filters.category = ''
  filters.dateRange = null
  search()
}

function change(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  loadItems()
}

function openCreate() {
  form.category = ''
  form.customCategory = ''
  form.amount = ''
  form.paid_at = dayjs().format('YYYY-MM-DD')
  form.remark = ''
  form.content_html = ''
  modalOpen.value = true
}

async function submit() {
  if (!finalCategory.value) {
    message.warning('请选择或填写分类')
    return
  }
  if (!form.amount) {
    message.warning('请填写金额')
    return
  }

  submitting.value = true
  try {
    const res = await createOperationExpense({
      category: finalCategory.value,
      amount: form.amount,
      paid_at: form.paid_at,
      remark: form.remark,
      content_html: form.content_html,
    })
    message.success(res.message)
    modalOpen.value = false
    selected.value = res.expense
    drawerOpen.value = true
    loadItems()
  } catch {
    message.error('保存经营账失败')
  } finally {
    submitting.value = false
  }
}

function openDetail(row: OperationExpense) {
  selected.value = row
  drawerOpen.value = true
}

onMounted(loadItems)
</script>

<template>
  <section class="page">
    <div class="pageHead">
      <div>
        <h1>经营账</h1>
        <p>平台经营支出，不包含赠送额度</p>
      </div>
      <a-button type="primary" @click="openCreate">新增经营账</a-button>
    </div>

    <!-- 筛选栏 -->
    <div class="expenseFilterBar">
      <a-select
        v-model:value="filters.category"
        placeholder="全部分类"
        allow-clear
        style="width:140px;"
        @change="search"
      >
        <a-select-option value="">全部分类</a-select-option>
        <a-select-option v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </a-select-option>
      </a-select>
      <a-range-picker
        v-model:value="filters.dateRange"
        :placeholder="['开始日期', '结束日期']"
        format="YYYY-MM-DD"
        @change="search"
      />
      <a-button @click="resetFilters">重置</a-button>
    </div>

    <a-table
      row-key="id"
      :columns="columns"
      :data-source="items"
      :loading="loading"
      :pagination="page"
      :scroll="{ x: 1120 }"
      :locale="{ emptyText: '暂无经营账记录' }"
      @change="change"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'amount'">
          <span class="money">{{ record.amount }}</span>
        </template>
        <template v-if="column.dataIndex === 'action'">
          <a-button size="small" @click="openDetail(record)">详情</a-button>
        </template>
      </template>
    </a-table>

    <a-modal v-model:open="modalOpen" title="新增经营账" :confirm-loading="submitting" ok-text="保存" cancel-text="取消" @ok="submit">
      <a-form layout="vertical">
        <a-form-item label="分类" required>
          <a-select v-model:value="form.category" placeholder="请选择分类">
            <a-select-option v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </a-select-option>
          </a-select>
        </a-form-item>
        <a-form-item v-if="isCustomCategory" label="自定义分类" required>
          <a-input v-model:value="form.customCategory" placeholder="请填写分类名称" />
        </a-form-item>
        <a-form-item label="金额" required><a-input v-model:value="form.amount" placeholder="0.00" /></a-form-item>
        <a-form-item label="发生日期" required><a-date-picker v-model:value="form.paid_at" value-format="YYYY-MM-DD" class="fullField" /></a-form-item>
        <a-form-item label="备注"><a-input v-model:value="form.remark" /></a-form-item>
        <a-form-item label="说明"><SafeRichTextEditor v-model:value="form.content_html" /></a-form-item>
      </a-form>
    </a-modal>

    <a-drawer v-model:open="drawerOpen" title="经营账详情" width="520">
      <a-descriptions v-if="selected" :column="1" bordered size="small">
        <a-descriptions-item label="单号">{{ selected.expense_no }}</a-descriptions-item>
        <a-descriptions-item label="分类">{{ selected.category }}</a-descriptions-item>
        <a-descriptions-item label="金额"><span class="money">{{ selected.amount }}</span></a-descriptions-item>
        <a-descriptions-item label="日期">{{ selected.paid_at }}</a-descriptions-item>
        <a-descriptions-item label="备注">{{ selected.remark || '-' }}</a-descriptions-item>
      </a-descriptions>
      <div v-if="selected?.content_html" class="safeHtml" v-html="selected.content_html" @click="onSafeHtmlClick"></div>
      <a-modal v-model:open="previewOpen" :footer="null" centered :body-style="{ textAlign: 'center', padding: '8px' }">
        <img :src="previewSrc" style="max-width:100%;max-height:80vh;border-radius:6px;" />
      </a-modal>
      <div class="drawerBlock">
        <h3>附件</h3>
        <AttachmentUploader attachable-type="operation_expense" :attachable-id="selected?.id || null" />
      </div>
    </a-drawer>
  </section>
</template>
