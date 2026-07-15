<script setup lang="ts">
import { ReloadOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { getAffiliateRebateRecords } from '../../api/affiliate'
import AsyncState from '../../components/AsyncState.vue'
import MetricGrid, { type MetricItem } from '../../components/MetricGrid.vue'
import PageHeader from '../../components/PageHeader.vue'
import type { RebateRecord } from '../../types'

const loading = ref(false)
const error = ref('')
const type = ref<'' | 'milestone' | 'stage'>('')
const items = ref<RebateRecord[]>([])
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const metrics = computed<MetricItem[]>(() => {
  const now = new Date()
  const month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
  const monthItems = items.value.filter((item) => item.created_at?.startsWith(month))

  return [
    {
      label: '本页本月返利',
      value: formatMoney(sumAmount(monthItems, 'rebate_amount')),
      hint: `本页本月 ${monthItems.length} 条`,
      tone: 'green',
    },
    {
      label: '本页累计返利',
      value: formatMoney(sumAmount(items.value, 'rebate_amount')),
      hint: `当前页 ${items.value.length} 条合计`,
      tone: 'blue',
    },
    {
      label: '本页下级充值',
      value: formatMoney(sumAmount(items.value, 'source_amount')),
      hint: `当前页 ${items.value.length} 条来源合计`,
      tone: 'orange',
    },
  ]
})

function money(value: string) {
  return formatMoney(toCents(value))
}

function toCents(value: string) {
  const raw = String(value || '0').trim()
  const negative = raw.startsWith('-')
  const [whole = '0', decimal = ''] = raw.replace(/^[+-]/, '').split('.')
  const cents = BigInt(whole || '0') * 100n + BigInt(`${decimal}00`.slice(0, 2))
  return negative ? -cents : cents
}

function formatMoney(value: bigint) {
  const negative = value < 0n
  const absolute = negative ? -value : value
  const whole = (absolute / 100n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  const decimal = (absolute % 100n).toString().padStart(2, '0')
  return `${negative ? '-' : ''}¥${whole}.${decimal}`
}

function sumAmount(rows: RebateRecord[], key: 'source_amount' | 'rebate_amount') {
  return rows.reduce((sum, item) => sum + toCents(item[key]), 0n)
}

function typeText(value: RebateRecord['type']) {
  if (value === 'milestone') return '初始里程碑'
  if (value === 'stage') return '后续台阶'
  return '历史期初'
}

function statusInfo(value: string) {
  if (value === 'confirmed') return { text: '已确认', color: 'green' }
  return { text: value || '--', color: 'default' }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await getAffiliateRebateRecords({ page: page.current, page_size: page.pageSize, type: type.value })
    items.value = res.items
    page.total = res.total
    page.current = res.page
    page.pageSize = res.page_size
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
    <PageHeader title="返利明细" description="一级返利来源、充值金额与确认状态">
      <template #actions>
        <a-button :loading="loading" @click="load"><template #icon><ReloadOutlined /></template>刷新</a-button>
      </template>
    </PageHeader>

    <AsyncState :loading="loading" :error="error" @retry="load">
      <MetricGrid :items="metrics" />

      <section class="rebateSection recordFilterSection">
        <div class="rebateFilters">
          <div class="recordTypeFilter">
            <a-segmented
              v-model:value="type"
              :options="[
                { label: '全部', value: '' },
                { label: '初始里程碑', value: 'milestone' },
                { label: '后续台阶', value: 'stage' },
              ]"
              @change="search"
            />
          </div>
          <span class="rebateMuted">共 {{ page.total }} 条</span>
        </div>
      </section>

      <section class="rebateSection recordTableSection">
        <div class="rebateSectionHeader">
          <h2>返利记录</h2>
          <span class="rebateMuted">当前页 {{ items.length }} 条</span>
        </div>
        <AsyncState :empty="items.length === 0" empty-text="暂无返利明细">
          <div class="rebateTable">
            <a-table
              row-key="id"
              size="small"
              :data-source="items"
              :pagination="{ current: page.current, pageSize: page.pageSize, total: page.total, showSizeChanger: true }"
              :scroll="{ x: 980 }"
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
                <template #default="{ record }"><a-tag color="blue">{{ typeText(record.type) }}</a-tag></template>
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
              <a-table-column title="状态" key="status" :width="105">
                <template #default="{ record }">
                  <a-tag :color="statusInfo(record.status).color">{{ statusInfo(record.status).text }}</a-tag>
                </template>
              </a-table-column>
            </a-table>
          </div>
        </AsyncState>
      </section>
    </AsyncState>
  </div>
</template>

<style scoped>
.recordFilterSection {
  padding: 14px 16px;
}

.recordTypeFilter {
  max-width: 100%;
  overflow-x: auto;
}

.recordTableSection {
  overflow: hidden;
}

.recordTableSection :deep(.ant-table-thead > tr > th) {
  padding-top: 10px;
  padding-bottom: 10px;
  background: var(--surface2);
  color: var(--muted);
  font-size: 12px;
  font-weight: 600;
}

.recordTableSection :deep(.ant-table-tbody > tr > td) {
  padding-top: 11px;
  padding-bottom: 11px;
}

.recordTableSection :deep(.ant-tag) {
  margin-inline-end: 0;
  border-radius: 999px;
}

@media (max-width: 760px) {
  .recordFilterSection {
    padding: 12px;
  }

  .recordTypeFilter {
    width: 100%;
  }
}
</style>
