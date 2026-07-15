<script setup lang="ts">
import { ReloadOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { getAffiliateRebateRecords } from '../../api/affiliate'
import AsyncState from '../../components/AsyncState.vue'
import PageHeader from '../../components/PageHeader.vue'
import type { RebateRecord } from '../../types'

const loading = ref(false)
const error = ref('')
const type = ref<'' | 'milestone' | 'stage'>('')
const items = ref<RebateRecord[]>([])
const page = reactive({ current: 1, pageSize: 20, total: 0 })

function money(value: string) {
  return `¥${Number(value || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await getAffiliateRebateRecords({ page: page.current, page_size: page.pageSize, type: type.value })
    items.value = res.items
    page.total = res.total
  } catch (err) {
    items.value = []
    error.value = (err as { response?: { data?: { message?: string } } }).response?.data?.message || '读取返利明细失败'
  } finally {
    loading.value = false
  }
}

function search() {
  page.current = 1
  load()
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
    <PageHeader title="返利明细">
      <template #actions>
        <a-button :loading="loading" @click="load"><template #icon><ReloadOutlined /></template>刷新</a-button>
      </template>
    </PageHeader>

    <section class="rebateSection">
      <div class="rebateFilters">
        <a-segmented
          v-model:value="type"
          :options="[
            { label: '全部', value: '' },
            { label: '初始里程碑', value: 'milestone' },
            { label: '后续台阶', value: 'stage' },
          ]"
          @change="search"
        />
        <span class="rebateMuted">共 {{ page.total }} 条</span>
      </div>
    </section>

    <section class="rebateSection">
      <AsyncState :loading="loading" :error="error" :empty="!loading && items.length === 0" empty-text="暂无返利明细" @retry="load">
        <div class="rebateTable">
          <a-table
            row-key="id"
            size="middle"
            :data-source="items"
            :pagination="{ current: page.current, pageSize: page.pageSize, total: page.total, showSizeChanger: true }"
            :scroll="{ x: 860 }"
            @change="tableChange"
          >
            <a-table-column title="时间" data-index="created_at" :width="175" />
            <a-table-column title="下级" key="payer" :width="250">
              <template #default="{ record }">
                <div class="rebateUserCell">
                  <strong>{{ record.payer_email || `用户 #${record.payer_user_id}` }}</strong>
                  <span>ID {{ record.payer_user_id }}</span>
                </div>
              </template>
            </a-table-column>
            <a-table-column title="类型" key="type" :width="130">
              <template #default="{ record }">{{ record.type === 'milestone' ? '初始里程碑' : '后续台阶' }}</template>
            </a-table-column>
            <a-table-column title="下级充值" key="source" align="right" :width="140">
              <template #default="{ record }">{{ money(record.source_amount) }}</template>
            </a-table-column>
            <a-table-column title="返利金额" key="amount" align="right" :width="140">
              <template #default="{ record }"><span class="rebateAmount">+{{ money(record.rebate_amount) }}</span></template>
            </a-table-column>
            <a-table-column title="层级" :width="90">
              <template #default>一级</template>
            </a-table-column>
          </a-table>
        </div>
      </AsyncState>
    </section>
  </div>
</template>
