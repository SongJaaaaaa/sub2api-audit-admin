<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { getAdmins, type AdminAccount, type AdminSummary } from '../api/admin'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useTableColumns } from '../composables/useTableColumns'

const loading = ref(false)
const items = ref<AdminAccount[]>([])
const filters = reactive({ keyword: '', status: '' })
const summary = reactive<AdminSummary>({ admin_count: 0, active_count: 0, disabled_count: 0 })
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const allColumns = [
  { title: 'Sub2API ID', dataIndex: 'sub2api_user_id', width: 120 },
  { title: '管理员姓名', dataIndex: 'name', width: 180 },
  { title: '登录邮箱', dataIndex: 'email', minWidth: 240 },
  { title: '状态', dataIndex: 'status', width: 110 },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resizeColumn, resetColumns } = useTableColumns('admin-account-columns', allColumns, 800)

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

onMounted(loadItems)
</script>

<template>
  <section class="page adminAccountsPage">
    <div class="adminFilterBar">
      <a-input v-model:value="filters.keyword" class="filterGrow" placeholder="姓名或登录邮箱" allow-clear @press-enter="search" />
      <a-select v-model:value="filters.status" class="filterStatus" placeholder="全部状态">
        <a-select-option value="">全部状态</a-select-option>
        <a-select-option value="active">启用</a-select-option>
        <a-select-option value="disabled">停用</a-select-option>
      </a-select>
      <div class="filterActions">
        <a-button type="primary" @click="search">查询</a-button>
        <a-button @click="resetFilters">重置</a-button>
      </div>
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
      @resize-column="resizeColumn"
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
  </section>
</template>

<style scoped>
.adminAccountsPage { gap: 16px; }
.adminFilterBar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.filterGrow { flex: 1 1 240px; max-width: 360px; }
.filterStatus { flex: 0 0 140px; }
.filterActions { display: flex; flex: 0 0 auto; gap: 10px; }
.summaryGrid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.summaryGrid section { padding: 14px 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 8px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; margin-bottom: 6px; color: var(--text-secondary, #7a8395); font-size: 12px; }
.summaryGrid strong { font-size: 22px; }
.activeCount { color: #389e0d; }
@media (max-width: 760px) {
  .adminFilterBar > :not(.filterActions) { flex: 1 1 100%; width: 100%; max-width: none; min-width: 0; }
  .filterActions { flex: 1 1 100%; }
  .filterActions button { flex: 1; }
  .summaryGrid { grid-template-columns: minmax(0, 1fr); }
}
</style>
