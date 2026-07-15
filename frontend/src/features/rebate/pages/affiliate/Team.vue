<script setup lang="ts">
import { ReloadOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { getAffiliateTeam } from '../../api/affiliate'
import AsyncState from '../../components/AsyncState.vue'
import PageHeader from '../../components/PageHeader.vue'
import type { TeamMember } from '../../types'

const loading = ref(false)
const error = ref('')
const items = ref<TeamMember[]>([])
const page = reactive({ current: 1, pageSize: 20, total: 0 })

function money(value: string) {
  return `¥${Number(value || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await getAffiliateTeam({ page: page.current, page_size: page.pageSize })
    items.value = res.items
    page.total = res.total
  } catch (err) {
    items.value = []
    error.value = (err as { response?: { data?: { message?: string } } }).response?.data?.message || '读取团队失败'
  } finally {
    loading.value = false
  }
}

function tableChange(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  load()
}

onMounted(load)
</script>

<template>
  <div class="rebatePage">
    <PageHeader title="我的团队">
      <template #actions>
        <a-button :loading="loading" @click="load">
          <template #icon><ReloadOutlined /></template>
          刷新
        </a-button>
      </template>
    </PageHeader>

    <section class="rebateSection">
      <div class="rebateSectionHeader">
        <h2>直接下级</h2>
        <span class="rebateMuted">共 {{ page.total }} 人</span>
      </div>
      <AsyncState :loading="loading" :error="error" :empty="!loading && items.length === 0" empty-text="暂无直接下级" @retry="load">
        <div class="rebateTable">
          <a-table
            row-key="user_id"
            size="middle"
            :data-source="items"
            :pagination="{ current: page.current, pageSize: page.pageSize, total: page.total, showSizeChanger: true }"
            :scroll="{ x: 850 }"
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
            <a-table-column title="累计充值" key="recharge" align="right" :width="140">
              <template #default="{ record }">{{ money(record.total_recharge_amount) }}</template>
            </a-table-column>
            <a-table-column title="产生返利" key="rebate" align="right" :width="140">
              <template #default="{ record }"><span class="rebateAmount">{{ money(record.total_rebate_amount) }}</span></template>
            </a-table-column>
            <a-table-column title="里程碑次数" data-index="milestone_times" align="right" :width="120" />
            <a-table-column title="加入时间" data-index="joined_at" :width="175" />
          </a-table>
        </div>
      </AsyncState>
    </section>
  </div>
</template>
