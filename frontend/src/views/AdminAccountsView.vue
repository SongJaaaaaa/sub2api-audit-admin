<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import { App as AntApp } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { useAppMode } from '../app/composables/useAppMode'
import { getAdmins, type AdminAccount, type AdminSummary } from '../api/admin'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useTableColumns } from '../composables/useTableColumns'

const { message } = AntApp.useApp()
const { isAppMode } = useAppMode()
const loading = ref(false)
const loadError = ref('')
const selected = ref<AdminAccount | null>(null)
const detailOpen = ref(false)
let loadSeq = 0
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

async function loadItems(append = false) {
  const seq = ++loadSeq
  const requestedPage = page.current
  loading.value = true
  loadError.value = ''
  try {
    const res = await getAdmins({
      page: page.current,
      page_size: page.pageSize,
      keyword: filters.keyword,
      status: filters.status,
    })
    if (seq !== loadSeq) return
    items.value = append ? [...items.value, ...res.items] : res.items
    page.total = res.total
    Object.assign(summary, res.summary)
  } catch {
    if (seq !== loadSeq) return
    if (append) page.current = Math.max(1, requestedPage - 1)
    loadError.value = '读取管理员账号失败，请重试。'
    message.error(loadError.value)
  } finally {
    if (seq === loadSeq) loading.value = false
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

function hasMore() {
  return page.current * page.pageSize < page.total
}

function loadMore() {
  if (loading.value || !hasMore()) return
  page.current += 1
  loadItems(true)
}

function openDetail(row: AdminAccount) {
  selected.value = row
  detailOpen.value = true
}

onMounted(loadItems)
</script>

<template>
  <section class="page adminAccountsPage">
    <a-alert v-if="loadError" class="appLoadError" type="error" show-icon :message="loadError">
      <template #description><a-button size="small" @click="loadItems">重试</a-button></template>
    </a-alert>

    <template v-if="isAppMode">
    <div class="appAdminFilters">
      <a-input v-model:value="filters.keyword" placeholder="姓名或登录邮箱" allow-clear @press-enter="search" />
      <a-select v-model:value="filters.status" placeholder="全部状态" allow-clear>
        <a-select-option value="">全部状态</a-select-option>
        <a-select-option value="active">启用</a-select-option>
        <a-select-option value="disabled">停用</a-select-option>
      </a-select>
      <div class="appFilterActions"><a-button @click="resetFilters">重置</a-button><a-button type="primary" @click="search">查询</a-button></div>
    </div>
    <div class="appSummaryGrid">
      <article><span>管理员总数</span><strong>{{ summary.admin_count }}</strong></article>
      <article><span>启用账号</span><strong class="activeCount">{{ summary.active_count }}</strong></article>
      <article><span>停用账号</span><strong>{{ summary.disabled_count }}</strong></article>
    </div>
    <div v-if="loading && !items.length" class="appLoadingState"><a-spin /><span>加载中</span></div>
    <div v-else-if="items.length" class="appAdminList">
      <article v-for="row in items" :key="row.id" class="appAdminCard">
        <div class="appAdminHead"><strong>{{ row.name || '-' }}</strong><a-tag :color="row.status === 'active' ? 'green' : 'default'">{{ row.status === 'active' ? '启用' : '停用' }}</a-tag></div>
        <div class="appAdminEmail">{{ row.email || '-' }}</div>
        <div class="appAdminMeta"><span>Sub2API ID {{ row.sub2api_user_id || '-' }}</span><time>{{ row.created_at || '-' }}</time></div>
        <a-button type="link" size="small" @click="openDetail(row)">查看详情</a-button>
      </article>
    </div>
    <a-empty v-else-if="!loading" description="暂无管理员账号" />
    <div v-if="hasMore()" class="appLoadMore"><a-button :loading="loading" block @click="loadMore">加载更多</a-button></div>
    </template>

    <template v-else>
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
    </template>

    <a-modal v-model:open="detailOpen" title="管理员详情" :footer="null" destroy-on-close>
      <template v-if="selected">
        <dl class="adminDetailList">
          <div><dt>姓名</dt><dd>{{ selected.name || '-' }}</dd></div>
          <div><dt>登录邮箱</dt><dd>{{ selected.email || '-' }}</dd></div>
          <div><dt>Sub2API ID</dt><dd>{{ selected.sub2api_user_id || '-' }}</dd></div>
          <div><dt>状态</dt><dd>{{ selected.status === 'active' ? '启用' : '停用' }}</dd></div>
          <div><dt>创建时间</dt><dd>{{ selected.created_at || '-' }}</dd></div>
        </dl>
      </template>
    </a-modal>
  </section>
</template>

<style scoped>
.appLoadError { margin-bottom: 10px; }.appAdminFilters { display: grid; gap: 8px; }.appFilterActions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.appSummaryGrid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }.appSummaryGrid article { padding: 11px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 9px; background: var(--card-bg, #fff); }.appSummaryGrid span { display: block; color: var(--text-secondary, #7a8395); font-size: 12px; }.appSummaryGrid strong { display: block; margin-top: 4px; font-size: 20px; }.appSummaryGrid .activeCount { color: var(--success); }
.appLoadingState { display: grid; place-items: center; gap: 8px; min-height: 120px; color: var(--text-secondary, #7a8395); }.appAdminList { display: grid; gap: 10px; }.appAdminCard { padding: 12px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 10px; background: var(--card-bg, #fff); }.appAdminHead, .appAdminMeta { display: flex; align-items: center; justify-content: space-between; gap: 8px; }.appAdminHead strong, .appAdminEmail { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.appAdminEmail { margin: 8px 0; color: var(--text-secondary, #7a8395); }.appAdminMeta { color: var(--text-secondary, #7a8395); font-size: 12px; }.adminDetailList { display: grid; gap: 10px; margin: 0; }.adminDetailList > div { display: flex; justify-content: space-between; gap: 16px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color, #e8eaf0); }.adminDetailList dt { color: var(--text-secondary, #7a8395); }.adminDetailList dd { margin: 0; text-align: right; overflow-wrap: anywhere; }
.adminAccountsPage { gap: 16px; }
.adminFilterBar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.filterGrow { flex: 1 1 240px; max-width: 360px; }
.filterStatus { flex: 0 0 140px; }
.filterActions { display: flex; flex: 0 0 auto; gap: 10px; }
.summaryGrid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.summaryGrid section { padding: 14px 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 8px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; margin-bottom: 6px; color: var(--text-secondary, #7a8395); font-size: 12px; }
.summaryGrid strong { font-size: 22px; }
.activeCount { color: var(--success); }
@media (max-width: 760px) {
  .adminFilterBar > :not(.filterActions) { flex: 1 1 100%; width: 100%; max-width: none; min-width: 0; }
  .filterActions { flex: 1 1 100%; }
  .filterActions button { flex: 1; }
  .summaryGrid { grid-template-columns: minmax(0, 1fr); }
}
</style>
