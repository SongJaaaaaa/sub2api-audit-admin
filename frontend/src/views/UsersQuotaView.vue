<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import { Modal, message } from 'ant-design-vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { createLedgerAdjustment, type AdjustmentRes } from '../api/ledger'
import { getSub2Users, type Sub2User } from '../api/sub2api'

const loading = ref(false)
const submitting = ref(false)
const modalOpen = ref(false)
const users = ref<Sub2User[]>([])
const selected = ref<Sub2User | null>(null)
const keyword = ref('')
const page = reactive({
  current: 1,
  pageSize: 20,
  total: 0,
})
const form = reactive({
  operation: 'increment' as 'increment' | 'decrement',
  amount: '',
  adjust_reason: '',
  admin_notes: '',
})

const columns = [
  { title: 'ID', dataIndex: 'id', width: 90 },
  { title: '邮箱', dataIndex: 'email' },
  { title: '用户名', dataIndex: 'username' },
  { title: '当前余额', dataIndex: 'balance', align: 'right', width: 130 },
  { title: '累计充值', dataIndex: 'total_recharged', align: 'right', width: 130 },
  { title: '状态', dataIndex: 'status', width: 110 },
  { title: '操作', dataIndex: 'action', fixed: 'right', width: 110 },
] as const

const modalTitle = computed(() => (selected.value ? `调整额度 #${selected.value.id}` : '调整额度'))

async function loadUsers() {
  loading.value = true
  try {
    const res = await getSub2Users({
      page: page.current,
      page_size: page.pageSize,
      keyword: keyword.value,
    })
    users.value = res.items
    page.total = res.total
  } catch {
    message.error('读取 Sub2API 用户失败')
  } finally {
    loading.value = false
  }
}

function search() {
  page.current = 1
  loadUsers()
}

function change(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  loadUsers()
}

function openAdjust(row: Sub2User) {
  selected.value = row
  form.operation = 'increment'
  form.amount = ''
  form.adjust_reason = ''
  form.admin_notes = ''
  modalOpen.value = true
}

async function submitAdjust() {
  if (!selected.value) return

  submitting.value = true
  try {
    const res = await createLedgerAdjustment({
      sub2api_user_id: selected.value.id,
      operation: form.operation,
      amount: form.amount,
      adjust_reason: form.adjust_reason,
      admin_notes: form.admin_notes,
    })
    message.success(res.message)
    modalOpen.value = false
    loadUsers()
  } catch (err) {
    const data = (err as { response?: { data?: AdjustmentRes } }).response?.data
    message.error(data?.message || '调额未确认成功')
  } finally {
    submitting.value = false
  }
}

function confirmAdjust() {
  if (!selected.value) return

  const op = form.operation === 'increment' ? '增加' : '扣减'
  Modal.confirm({
    title: '确认提交额度调整',
    content: `将为 Sub2API 用户 #${selected.value.id} ${op}额度 ${form.amount || '0.00'}。新系统不会直接显示成功，只有 Sub2API 真实入账并二次确认成功后，才会生成成功记录。`,
    okText: '确认提交',
    cancelText: '再检查',
    onOk: submitAdjust,
  })
}

onMounted(loadUsers)
</script>

<template>
  <section class="page">
    <div class="pageHead">
      <div>
        <h1>用户与额度</h1>
        <p>Sub2API 当前余额</p>
      </div>
      <a-input-search
        v-model:value="keyword"
        class="search"
        placeholder="邮箱或用户名"
        allow-clear
        enter-button
        @search="search"
      />
    </div>

    <a-table
      row-key="id"
      :columns="columns"
      :data-source="users"
      :loading="loading"
      :locale="{ emptyText: '暂无可调额用户数据' }"
      :pagination="page"
      :scroll="{ x: 1080 }"
      @change="change"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'balance' || column.dataIndex === 'total_recharged'">
          <span class="money">{{ record[column.dataIndex] }}</span>
        </template>
        <template v-if="column.dataIndex === 'status'">
          <a-tag :color="record.status === 'active' ? 'green' : 'default'">
            {{ record.status || '-' }}
          </a-tag>
        </template>
        <template v-if="column.dataIndex === 'action'">
          <a-button type="primary" size="small" @click="openAdjust(record)">调额</a-button>
        </template>
      </template>
    </a-table>

    <a-modal
      v-model:open="modalOpen"
      :title="modalTitle"
      :confirm-loading="submitting"
      ok-text="提交调额"
      cancel-text="取消"
      @ok="confirmAdjust"
    >
      <div v-if="selected" class="adjustUser">
        <span>{{ selected.email }}</span>
        <strong class="money">{{ selected.balance }}</strong>
      </div>
      <a-form layout="vertical">
        <a-form-item label="调整方向" required>
          <a-segmented
            v-model:value="form.operation"
            :options="[
              { label: '增加', value: 'increment' },
              { label: '扣减', value: 'decrement' },
            ]"
          />
        </a-form-item>
        <a-form-item label="额度" required>
          <a-input v-model:value="form.amount" placeholder="0.00" />
        </a-form-item>
        <a-form-item label="原因" required>
          <a-input v-model:value="form.adjust_reason" placeholder="例如 线下充值" />
        </a-form-item>
        <a-form-item label="备注">
          <a-textarea v-model:value="form.admin_notes" :rows="3" />
        </a-form-item>
      </a-form>
    </a-modal>
  </section>
</template>
