<script setup lang="ts">
import { UserAddOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { createAdmin, getAdmins, type AdminAccount, type AdminSummary } from '../api/admin'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useTableColumns } from '../composables/useTableColumns'

const loading = ref(false)
const submitting = ref(false)
const modalOpen = ref(false)
const items = ref<AdminAccount[]>([])
const filters = reactive({ keyword: '', status: '' })
const summary = reactive<AdminSummary>({ admin_count: 0, active_count: 0, disabled_count: 0 })
const page = reactive({ current: 1, pageSize: 20, total: 0 })
const form = reactive({ name: '', email: '', password: '', password_confirmation: '', active: true })

const allColumns = [
  { title: 'ID', dataIndex: 'id', width: 90 },
  { title: '管理员姓名', dataIndex: 'name', width: 180 },
  { title: '登录邮箱', dataIndex: 'email', minWidth: 240 },
  { title: '状态', dataIndex: 'status', width: 110 },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resetColumns } = useTableColumns('admin-account-columns', allColumns, 800)

async function loadItems() {
  loading.value = true
  try {
    const res = await getAdmins({
      page: page.current,
      page_size: page.pageSize,
      keyword: filters.keyword,
      status: filters.status,
    })
    items.value = res.items
    page.total = res.total
    Object.assign(summary, res.summary)
  } catch {
    message.error('读取管理员账号失败')
  } finally {
    loading.value = false
  }
}

function search() {
  page.current = 1
  loadItems()
}

function resetFilters() {
  filters.keyword = ''
  filters.status = ''
  search()
}

function change(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  loadItems()
}

function openCreate() {
  Object.assign(form, { name: '', email: '', password: '', password_confirmation: '', active: true })
  modalOpen.value = true
}

async function submit() {
  if (!form.name.trim()) return void message.warning('请填写管理员姓名')
  if (!form.email.trim()) return void message.warning('请填写登录邮箱')
  if (form.password.length < 8) return void message.warning('密码至少 8 位')
  if (form.password !== form.password_confirmation) return void message.warning('两次输入的密码不一致')

  submitting.value = true
  try {
    const res = await createAdmin({
      name: form.name.trim(),
      email: form.email.trim(),
      password: form.password,
      password_confirmation: form.password_confirmation,
      status: form.active ? 'active' : 'disabled',
    })
    message.success(res.message)
    modalOpen.value = false
    page.current = 1
    loadItems()
  } catch (err) {
    const data = (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response?.data
    message.error(data?.errors?.email?.[0] || data?.errors?.password?.[0] || data?.message || '创建管理员失败')
  } finally {
    submitting.value = false
  }
}

onMounted(loadItems)
</script>

<template>
  <section class="page adminAccountsPage">
    <div class="pageHead pageHeadActionsOnly">
      <a-button type="primary" @click="openCreate">
        <template #icon><UserAddOutlined /></template>
        新增管理员
      </a-button>
    </div>

    <div class="adminFilterBar">
      <a-input v-model:value="filters.keyword" placeholder="姓名或登录邮箱" allow-clear @press-enter="search" />
      <a-select v-model:value="filters.status" placeholder="全部状态">
        <a-select-option value="">全部状态</a-select-option>
        <a-select-option value="active">启用</a-select-option>
        <a-select-option value="disabled">停用</a-select-option>
      </a-select>
      <a-button type="primary" @click="search">查询</a-button>
      <a-button @click="resetFilters">重置</a-button>
    </div>

    <div class="summaryGrid">
      <section><span>管理员总数</span><strong>{{ summary.admin_count }}</strong></section>
      <section><span>启用账号</span><strong class="activeCount">{{ summary.active_count }}</strong></section>
      <section><span>停用账号</span><strong>{{ summary.disabled_count }}</strong></section>
    </div>

    <div class="tableTools">
      <ColumnSettings v-model:value="visibleCols" v-model:width="tableWidth" :options="colOptions" @reset="resetColumns" />
    </div>
    <a-table
      row-key="id"
      :columns="columns"
      :data-source="items"
      :loading="loading"
      :pagination="page"
      :scroll="{ x: tableWidth }"
      :locale="{ emptyText: '暂无管理员账号' }"
      @change="change"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'status'">
          <a-tag :color="record.status === 'active' ? 'green' : 'default'">
            {{ record.status === 'active' ? '启用' : '停用' }}
          </a-tag>
        </template>
      </template>
    </a-table>

    <a-modal
      v-model:open="modalOpen"
      title="新增管理员"
      :confirm-loading="submitting"
      ok-text="创建账号"
      cancel-text="取消"
      @ok="submit"
    >
      <a-form layout="vertical" autocomplete="off">
        <a-form-item label="管理员姓名" required>
          <a-input v-model:value="form.name" placeholder="例如：运营管理员" :maxlength="100" />
        </a-form-item>
        <a-form-item label="登录邮箱" required>
          <a-input v-model:value="form.email" type="email" autocomplete="off" placeholder="admin@example.com" />
        </a-form-item>
        <a-form-item label="登录密码" required>
          <a-input-password v-model:value="form.password" autocomplete="new-password" placeholder="至少 8 位" :maxlength="72" />
        </a-form-item>
        <a-form-item label="确认密码" required>
          <a-input-password v-model:value="form.password_confirmation" autocomplete="new-password" placeholder="再次输入密码" :maxlength="72" />
        </a-form-item>
        <a-form-item label="账号状态">
          <div class="statusSwitch">
            <a-switch v-model:checked="form.active" />
            <span>{{ form.active ? '启用' : '停用' }}</span>
          </div>
        </a-form-item>
      </a-form>
    </a-modal>
  </section>
</template>

<style scoped>
.adminAccountsPage { gap: 16px; }
.adminFilterBar { display: grid; grid-template-columns: minmax(220px, 1fr) 180px auto auto; gap: 10px; }
.summaryGrid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.summaryGrid section { padding: 14px 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 8px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; margin-bottom: 6px; color: var(--text-secondary, #7a8395); font-size: 12px; }
.summaryGrid strong { font-size: 22px; }
.activeCount { color: #389e0d; }
.statusSwitch { display: flex; align-items: center; gap: 10px; }
@media (max-width: 760px) {
  .adminFilterBar { grid-template-columns: minmax(0, 1fr); }
  .adminFilterBar > * { width: 100%; min-width: 0; }
  .summaryGrid { grid-template-columns: minmax(0, 1fr); }
}
</style>
