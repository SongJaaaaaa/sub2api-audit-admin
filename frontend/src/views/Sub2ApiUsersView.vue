<script setup lang="ts">
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { getSub2Users, type Sub2User } from '../api/sub2api'

const loading = ref(false)
const users = ref<Sub2User[]>([])
const keyword = ref('')
const page = reactive({
  current: 1,
  pageSize: 20,
  total: 0,
})

const columns = [
  { title: 'ID', dataIndex: 'id', width: 90 },
  { title: '邮箱', dataIndex: 'email' },
  { title: '用户名', dataIndex: 'username' },
  { title: '角色', dataIndex: 'role', width: 100 },
  { title: '余额', dataIndex: 'balance', align: 'right', width: 120 },
  { title: '累计充值', dataIndex: 'total_recharged', align: 'right', width: 130 },
  { title: '状态', dataIndex: 'status', width: 110 },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
] as const

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

onMounted(loadUsers)
</script>

<template>
  <section class="page">
    <div class="pageHead">
      <div>
        <h1>Sub2API 用户</h1>
        <p>只读数据源</p>
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
      :locale="{ emptyText: '暂无 Sub2API 用户数据' }"
      :pagination="page"
      :scroll="{ x: 1120 }"
      @change="change"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'status'">
          <a-tag :color="record.status === 'active' ? 'green' : 'default'">
            {{ record.status || '-' }}
          </a-tag>
        </template>
        <template v-if="column.dataIndex === 'balance'">
          <span class="money">{{ record.balance }}</span>
        </template>
        <template v-if="column.dataIndex === 'total_recharged'">
          <span class="money">{{ record.total_recharged }}</span>
        </template>
      </template>
    </a-table>
  </section>
</template>
