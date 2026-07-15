<script setup lang="ts">
import { SearchOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { getRelationships, searchSub2Users } from '../../api/admin'
import AsyncState from '../../components/AsyncState.vue'
import MetricGrid, { type MetricItem } from '../../components/MetricGrid.vue'
import PageHeader from '../../components/PageHeader.vue'
import type { RelationshipRes, UserSearchItem } from '../../types'

const selectedId = ref<number>()
const users = ref<UserSearchItem[]>([])
const userLoading = ref(false)
const loading = ref(false)
const error = ref('')
const result = ref<RelationshipRes | null>(null)
const page = reactive({ current: 1, pageSize: 20 })
let searchTimer: ReturnType<typeof setTimeout> | undefined

const options = computed(() => users.value.map((user) => ({
  value: user.id,
  label: `${user.email}${user.username ? ` (${user.username})` : ''} · ID ${user.id}`,
})))

const metrics = computed<MetricItem[]>(() => result.value ? [
  { label: '直接下级', value: result.value.user.direct_count, tone: 'blue' },
  { label: '下级累计充值', value: money(result.value.user.total_recharge_amount), tone: 'green' },
  { label: '产生返利', value: money(result.value.user.total_rebate_amount), tone: 'green' },
] : [])

function money(value: string) {
  return `¥${Number(value || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function apiMessage(err: unknown, fallback: string) {
  return (err as { response?: { data?: { message?: string } } }).response?.data?.message || fallback
}

function queueUserSearch(keyword: string) {
  clearTimeout(searchTimer)
  if (!keyword.trim()) {
    users.value = []
    return
  }
  searchTimer = setTimeout(() => loadUsers(keyword.trim()), 300)
}

async function loadUsers(keyword: string) {
  userLoading.value = true
  try {
    users.value = (await searchSub2Users(keyword)).items
  } catch (err) {
    error.value = apiMessage(err, '搜索 Sub2API 用户失败')
  } finally {
    userLoading.value = false
  }
}

function changeUser() {
  result.value = null
  error.value = ''
  page.current = 1
}

async function load() {
  if (!selectedId.value) return
  loading.value = true
  error.value = ''
  try {
    result.value = await getRelationships({
      user_id: selectedId.value,
      page: page.current,
      page_size: page.pageSize,
    })
  } catch (err) {
    result.value = null
    error.value = apiMessage(err, '读取推荐关系失败')
  } finally {
    loading.value = false
  }
}

function tableChange(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  load()
}

onBeforeUnmount(() => clearTimeout(searchTimer))
</script>

<template>
  <div class="rebatePage">
    <PageHeader title="推荐关系" />

    <section class="rebateSection">
      <div class="rebateFilters">
        <a-select
          v-model:value="selectedId"
          show-search
          allow-clear
          :filter-option="false"
          :options="options"
          :loading="userLoading"
          placeholder="搜索邮箱、用户名或用户 ID"
          style="width: min(100%, 520px)"
          @search="queueUserSearch"
          @change="changeUser"
        />
        <a-button type="primary" :disabled="!selectedId" :loading="loading" @click="load">
          <template #icon><SearchOutlined /></template>
          查看关系
        </a-button>
      </div>
    </section>

    <a-alert v-if="!result && !loading && !error" type="info" show-icon message="请选择账号后查看一级推荐关系" />

    <AsyncState :loading="loading" :error="error" @retry="load">
      <template v-if="result">
        <section class="rebateSection">
          <div class="rebateSectionHeader">
            <h2>{{ result.user.email }}</h2>
            <span class="rebateMuted">用户 ID {{ result.user.user_id }}</span>
          </div>
          <MetricGrid :items="metrics" />
        </section>

        <section class="rebateSection">
          <div class="rebateSectionHeader"><h2>直接下级</h2></div>
          <div class="rebateTable">
            <a-table
              row-key="user_id"
              size="middle"
              :data-source="result.items"
              :pagination="{ current: result.page, pageSize: result.page_size, total: result.total, showSizeChanger: true }"
              :scroll="{ x: 960 }"
              @change="tableChange"
            >
              <a-table-column title="用户" key="user" :width="260">
                <template #default="{ record }">
                  <div class="rebateUserCell">
                    <strong>{{ record.email }}</strong>
                    <span>{{ record.username || `用户 #${record.user_id}` }}</span>
                  </div>
                </template>
              </a-table-column>
              <a-table-column title="用户 ID" data-index="user_id" :width="100" />
              <a-table-column title="邀请码" data-index="invite_code" :width="130" />
              <a-table-column title="累计充值" key="total_recharge_amount" align="right" :width="140">
                <template #default="{ record }">{{ money(record.total_recharge_amount) }}</template>
              </a-table-column>
              <a-table-column title="产生返利" key="total_rebate_amount" align="right" :width="140">
                <template #default="{ record }"><span class="rebateAmount">{{ money(record.total_rebate_amount) }}</span></template>
              </a-table-column>
              <a-table-column title="加入时间" data-index="created_at" :width="175" />
            </a-table>
          </div>
        </section>
      </template>
    </AsyncState>
  </div>
</template>
