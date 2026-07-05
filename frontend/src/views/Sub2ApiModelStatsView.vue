<script setup lang="ts">
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import { message } from 'ant-design-vue'
import { computed, onMounted, ref } from 'vue'
import { getModelStats, type ModelRank, type ModelStatsRes } from '../api/sub2api'

const loading = ref(false)
const stats = ref<ModelStatsRes | null>(null)
const range = ref<[Dayjs, Dayjs]>([dayjs().subtract(7, 'day').startOf('day'), dayjs().endOf('day')])

const columns = [
  { title: '模型', dataIndex: 'model' },
  { title: '请求数', dataIndex: 'request_count', align: 'right', width: 130 },
  { title: '用户数', dataIndex: 'user_count', align: 'right', width: 120 },
  { title: 'Total Cost', dataIndex: 'total_cost', align: 'right', width: 150 },
  { title: 'Actual Cost', dataIndex: 'actual_cost', align: 'right', width: 150 },
] as const

const sourceText = computed(() => {
  const source = stats.value?.sources
  if (!source) return []

  return [
    { label: 'payment_orders', value: source.payment_orders_completed },
    ...source.redeem_codes_used.map((item) => ({
      label: `redeem_codes.${item.type}`,
      value: item.count,
    })),
  ]
})

async function loadStats() {
  loading.value = true
  try {
    const [from, to] = range.value
    stats.value = await getModelStats({
      from: from.format('YYYY-MM-DD HH:mm:ss'),
      to: to.format('YYYY-MM-DD HH:mm:ss'),
      limit: 30,
    })
  } catch {
    message.error('读取模型消耗统计失败')
  } finally {
    loading.value = false
  }
}

function changeRange(val: [Dayjs, Dayjs] | null) {
  if (!val) return
  range.value = val
  loadStats()
}

function costText(row: ModelRank, key: 'total_cost' | 'actual_cost') {
  return Number(row[key]).toFixed(6)
}

onMounted(loadStats)
</script>

<template>
  <section class="page">
    <div class="pageHead">
      <div>
        <h1>模型消耗统计</h1>
        <p>usage_logs 聚合</p>
      </div>
      <a-range-picker
        :value="range"
        show-time
        class="range"
        format="YYYY-MM-DD HH:mm:ss"
        @change="changeRange"
      />
    </div>

    <a-spin :spinning="loading">
      <div class="metricGrid">
        <div class="metric">
          <span>请求数</span>
          <strong>{{ stats?.summary.request_count || 0 }}</strong>
        </div>
        <div class="metric">
          <span>用户数</span>
          <strong>{{ stats?.summary.user_count || 0 }}</strong>
        </div>
        <div class="metric">
          <span>模型数</span>
          <strong>{{ stats?.summary.model_count || 0 }}</strong>
        </div>
        <div class="metric">
          <span>Total Cost</span>
          <strong>{{ Number(stats?.summary.total_cost || 0).toFixed(6) }}</strong>
        </div>
      </div>

      <a-table
        row-key="model"
        :columns="columns"
        :data-source="stats?.models || []"
        :locale="{ emptyText: '暂无模型消耗数据' }"
        :pagination="{ pageSize: 15 }"
        :scroll="{ x: 860 }"
      >
        <template #bodyCell="{ column, record }">
          <template v-if="column.dataIndex === 'total_cost'">
            <span class="money">{{ costText(record, 'total_cost') }}</span>
          </template>
          <template v-if="column.dataIndex === 'actual_cost'">
            <span class="money">{{ costText(record, 'actual_cost') }}</span>
          </template>
        </template>
      </a-table>

      <div class="sourceRow">
        <a-tag v-for="item in sourceText" :key="item.label" color="blue">
          {{ item.label }}: {{ item.value }}
        </a-tag>
      </div>
    </a-spin>
  </section>
</template>
