<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { getAuditLogs, type AuditLog } from '../api/audit'

const loading = ref(false)
const drawerOpen = ref(false)
const items = ref<AuditLog[]>([])
const selected = ref<AuditLog | null>(null)
const filters = reactive({ action: '', admin_id: '' })
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const columns = [
  { title: '操作', dataIndex: 'action', width: 190 },
  { title: '管理员', dataIndex: 'admin_name', width: 120 },
  { title: '管理员ID', dataIndex: 'admin_id', width: 100 },
  { title: '对象', dataIndex: 'target_type', width: 150 },
  { title: '对象ID', dataIndex: 'target_id', width: 100 },
  { title: 'IP', dataIndex: 'ip', width: 150 },
  { title: '时间', dataIndex: 'created_at', width: 180 },
  { title: '操作', dataIndex: 'detail', fixed: 'right', width: 90 },
] as const

async function loadItems() {
  loading.value = true
  try {
    const res = await getAuditLogs({
      page: page.current,
      page_size: page.pageSize,
      action: filters.action,
      admin_id: filters.admin_id,
    })
    items.value = res.items
    page.total = res.total
  } catch {
    message.error('读取操作审计失败')
  } finally {
    loading.value = false
  }
}

function search() {
  page.current = 1
  loadItems()
}

function change(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  loadItems()
}

function openDetail(row: AuditLog) {
  selected.value = row
  drawerOpen.value = true
}

function jsonText(val: unknown) {
  return JSON.stringify(val || {}, null, 2)
}

onMounted(loadItems)
</script>

<template>
  <section class="page">
    <div class="pageHead">
      <div>
        <h1>操作审计</h1>
        <p>危险操作以后端登录管理员为准留痕</p>
      </div>
      <div class="headActions">
        <a-input v-model:value="filters.action" class="filterInput" placeholder="操作类型" allow-clear @press-enter="search" />
        <a-input v-model:value="filters.admin_id" class="filterInput" placeholder="管理员ID" allow-clear @press-enter="search" />
        <a-button type="primary" @click="search">查询</a-button>
      </div>
    </div>

    <a-table
      row-key="id"
      :columns="columns"
      :data-source="items"
      :loading="loading"
      :pagination="page"
      :scroll="{ x: 1180 }"
      :locale="{ emptyText: '暂无审计日志' }"
      @change="change"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'detail'">
          <a-button size="small" @click="openDetail(record)">详情</a-button>
        </template>
      </template>
    </a-table>

    <a-drawer v-model:open="drawerOpen" title="审计详情" width="620">
      <a-descriptions v-if="selected" :column="1" bordered size="small">
        <a-descriptions-item label="操作">{{ selected.action }}</a-descriptions-item>
        <a-descriptions-item label="管理员">{{ selected.admin_name || '-' }} #{{ selected.admin_id || '-' }}</a-descriptions-item>
        <a-descriptions-item label="对象">{{ selected.target_type }} #{{ selected.target_id || '-' }}</a-descriptions-item>
        <a-descriptions-item label="IP">{{ selected.ip || '-' }}</a-descriptions-item>
        <a-descriptions-item label="User-Agent">{{ selected.user_agent || '-' }}</a-descriptions-item>
      </a-descriptions>
      <div class="drawerBlock">
        <h3>前值</h3>
        <pre class="jsonBox">{{ jsonText(selected?.before_value) }}</pre>
        <h3>后值</h3>
        <pre class="jsonBox">{{ jsonText(selected?.after_value) }}</pre>
      </div>
    </a-drawer>
  </section>
</template>
