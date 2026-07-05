<script setup lang="ts">
import dayjs from 'dayjs'
import { message } from 'ant-design-vue'
import { computed, onMounted, ref } from 'vue'
import { getLedgerAdjustments, type LedgerAdjustment } from '../api/ledger'
import { getModelStats, type ModelRank, type ModelStatsRes, getSub2Users } from '../api/sub2api'

const loading = ref(false)
const userTotal = ref(0)
const stats = ref<ModelStatsRes | null>(null)
const success = ref<LedgerAdjustment[]>([])
const abnormal = ref<LedgerAdjustment[]>([])

const topModels = computed(() => stats.value?.models.slice(0, 5) || [])
const quicks = [
  { to: '/sub2api/users', title: 'Sub2API 用户', desc: '账号、余额、状态' },
  { to: '/sub2api/models', title: '模型消耗统计', desc: 'usage_logs 汇总' },
  { to: '/users-quota', title: '用户与额度', desc: '搜索用户并发起调额' },
  { to: '/exceptions', title: '异常中心', desc: '作废单与异常单' },
]

async function loadDashboard() {
  loading.value = true
  try {
    const from = dayjs().subtract(7, 'day').startOf('day').format('YYYY-MM-DD HH:mm:ss')
    const to = dayjs().endOf('day').format('YYYY-MM-DD HH:mm:ss')
    const [users, modelStats, successList, abnormalList] = await Promise.all([
      getSub2Users({ page: 1, page_size: 1 }),
      getModelStats({ from, to, limit: 8 }),
      getLedgerAdjustments({ page: 1, page_size: 5, status: 'succeeded' }),
      getLedgerAdjustments({ page: 1, page_size: 5, status: 'abnormal' }),
    ])

    userTotal.value = users.total
    stats.value = modelStats
    success.value = successList.items
    abnormal.value = abnormalList.items
  } catch {
    message.error('读取首页数据失败')
  } finally {
    loading.value = false
  }
}

function costText(row: ModelRank, key: 'total_cost' | 'actual_cost') {
  return Number(row[key]).toFixed(6)
}

onMounted(loadDashboard)
</script>

<template>
  <section class="page">
    <div class="pageHead">
      <div>
        <h1>首页</h1>
        <p>当前已接入模块的实时工作台</p>
      </div>
      <a-button :loading="loading" @click="loadDashboard">刷新</a-button>
    </div>

    <a-spin :spinning="loading">
      <div class="metricGrid">
        <div class="metric">
          <span>Sub2API 用户</span>
          <strong>{{ userTotal }}</strong>
        </div>
        <div class="metric">
          <span>近 7 天请求</span>
          <strong>{{ stats?.summary.request_count || 0 }}</strong>
        </div>
        <div class="metric">
          <span>近 7 天 Total Cost</span>
          <strong>{{ Number(stats?.summary.total_cost || 0).toFixed(6) }}</strong>
        </div>
        <div class="metric">
          <span>异常/作废单</span>
          <strong>{{ abnormal.length }}</strong>
        </div>
      </div>

      <div class="dashGrid">
        <section class="panel">
          <div class="panelHead">
            <h2>模型消耗 Top 5</h2>
            <RouterLink to="/sub2api/models">查看全部</RouterLink>
          </div>
          <a-table
            row-key="model"
            size="small"
            :columns="[
              { title: '模型', dataIndex: 'model' },
              { title: '请求', dataIndex: 'request_count', align: 'right', width: 90 },
              { title: 'Total Cost', dataIndex: 'total_cost', align: 'right', width: 130 },
            ]"
            :data-source="topModels"
            :pagination="false"
            :scroll="{ x: 520 }"
          >
            <template #bodyCell="{ column, record }">
              <template v-if="column.dataIndex === 'total_cost'">
                <span class="money">{{ costText(record, 'total_cost') }}</span>
              </template>
            </template>
          </a-table>
        </section>

        <section class="panel">
          <div class="panelHead">
            <h2>最近成功调额</h2>
            <RouterLink to="/ledger">查看记录</RouterLink>
          </div>
          <a-empty v-if="success.length === 0" description="暂无成功调额" />
          <div v-else class="miniList">
            <div v-for="item in success" :key="item.id" class="miniItem">
              <div>
                <strong>{{ item.ledger_no }}</strong>
                <span>#{{ item.sub2api_user_id }} {{ item.operation === 'increment' ? '增加' : '扣减' }}</span>
              </div>
              <span class="money">{{ item.amount }}</span>
            </div>
          </div>
        </section>

        <section class="panel">
          <div class="panelHead">
            <h2>异常中心</h2>
            <RouterLink to="/exceptions">处理异常</RouterLink>
          </div>
          <a-empty v-if="abnormal.length === 0" description="暂无异常单" />
          <div v-else class="miniList">
            <div v-for="item in abnormal" :key="item.id" class="miniItem dangerItem">
              <div>
                <strong>{{ item.ledger_no }}</strong>
                <span>{{ item.exception_reason || '-' }}</span>
              </div>
              <a-tag :color="item.status === 'exception' ? 'red' : 'orange'">
                {{ item.status === 'exception' ? '异常' : '作废' }}
              </a-tag>
            </div>
          </div>
        </section>

        <section class="panel">
          <div class="panelHead">
            <h2>快捷入口</h2>
          </div>
          <div class="entryGrid compact">
            <RouterLink v-for="item in quicks" :key="item.to" class="entry" :to="item.to">
              <strong>{{ item.title }}</strong>
              <span>{{ item.desc }}</span>
            </RouterLink>
          </div>
        </section>
      </div>
    </a-spin>
  </section>
</template>
