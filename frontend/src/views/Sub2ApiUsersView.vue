<script setup lang="ts">
import { HistoryOutlined, MinusCircleOutlined, PlusCircleOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import { onMounted, reactive, ref } from 'vue'
import { getSub2BalanceHistory, getSub2Users, type Sub2BalanceHistoryItem, type Sub2User } from '../api/sub2api'

const loading = ref(false)
const historyLoading = ref(false)
const drawerOpen = ref(false)
const users = ref<Sub2User[]>([])
const history = ref<Sub2BalanceHistoryItem[]>([])
const selectedUser = ref<Sub2User | null>(null)
const historyPage = reactive({ current: 1, pageSize: 10, total: 0 })
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
  { title: '操作', dataIndex: 'action', fixed: 'right', width: 90 },
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

async function loadHistory(userId: number, p = 1) {
  historyLoading.value = true
  try {
    const res = await getSub2BalanceHistory(userId, { page: p, page_size: historyPage.pageSize })
    history.value = res.items
    historyPage.total = res.total
    historyPage.current = p
  } catch {
    history.value = []
    message.error('读取充值记录失败')
  } finally {
    historyLoading.value = false
  }
}

function openHistory(row: Sub2User) {
  selectedUser.value = row
  drawerOpen.value = true
  history.value = []
  historyPage.current = 1
  loadHistory(row.id)
}

function historyPageChange(p: number) {
  if (selectedUser.value) loadHistory(selectedUser.value.id, p)
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

function opSign(row: Sub2BalanceHistoryItem) {
  return row.operation === 'increment' ? '+' : '-'
}

function opColor(row: Sub2BalanceHistoryItem) {
  return row.operation === 'increment' ? '#52c41a' : '#ff4d4f'
}

function timeText(row: Sub2BalanceHistoryItem) {
  return row.used_at || row.created_at || '-'
}

function absVal(v: string | number) {
  return Math.abs(Number(v || 0)).toFixed(2)
}

function typeLabel(type: string) {
  const map: Record<string, string> = {
    admin_balance: '管理员调额',
    payment: '充值',
    redeem: '兑换码',
    gift: '赠送',
    deduct: '扣减',
  }
  return map[type] || type || '-'
}

onMounted(loadUsers)
</script>

<template>
  <section class="page">
    <div class="pageHead">
      <div>
        <h1>Sub2API 用户</h1>
        <p>只读数据源，点击"详情"可查看历史充值记录</p>
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
      :scroll="{ x: 1200 }"
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
        <template v-if="column.dataIndex === 'action'">
          <a-button size="small" @click="openHistory(record)">
            <template #icon><HistoryOutlined /></template>
            详情
          </a-button>
        </template>
      </template>
    </a-table>

    <!-- 历史充值详情 Drawer -->
    <a-drawer
      v-model:open="drawerOpen"
      :title="selectedUser ? `${selectedUser.username || selectedUser.email} — 充值历史` : '充值历史'"
      width="520"
      placement="right"
    >
      <template v-if="selectedUser">
        <!-- 用户概览 -->
        <div class="userOverviewCard">
          <div class="userOverviewRow">
            <span class="userOverviewAvatar">{{ (selectedUser.username || selectedUser.email || 'U').slice(0, 1).toUpperCase() }}</span>
            <div class="userOverviewInfo">
              <strong>{{ selectedUser.username || selectedUser.email }}</strong>
              <em>{{ selectedUser.email }} · ID: {{ selectedUser.id }}</em>
            </div>
          </div>
          <div class="userOverviewStats">
            <div class="userOverviewStat">
              <span>当前余额</span>
              <strong class="money">{{ Number(selectedUser.balance || 0).toFixed(2) }}</strong>
            </div>
            <div class="userOverviewStat">
              <span>累计充值</span>
              <strong>{{ Number(selectedUser.total_recharged || 0).toFixed(2) }}</strong>
            </div>
            <div class="userOverviewStat">
              <span>账号状态</span>
              <a-tag :color="selectedUser.status === 'active' ? 'green' : 'default'" style="margin:0">
                {{ selectedUser.status || '-' }}
              </a-tag>
            </div>
          </div>
        </div>

        <!-- 历史记录列表 -->
        <a-spin :spinning="historyLoading">
          <a-empty v-if="!historyLoading && history.length === 0" description="暂无充值记录" />
          <div v-else class="historyTimeline">
            <div v-for="item in history" :key="item.id" class="historyTimelineItem">
              <div class="historyTimelineIcon" :style="{ background: opColor(item) + '20', color: opColor(item) }">
                <PlusCircleOutlined v-if="item.operation === 'increment'" />
                <MinusCircleOutlined v-else />
              </div>
              <div class="historyTimelineBody">
                <div class="historyTimelineHead">
                  <span class="historyTimelineType">{{ typeLabel(item.type) }}</span>
                  <strong class="historyTimelineMoney" :style="{ color: opColor(item) }">
                    {{ opSign(item) }}{{ absVal(item.value) }}
                  </strong>
                </div>
                <div class="historyTimelineMeta">
                  <span v-if="item.before_balance !== null">
                    {{ item.before_balance ?? '-' }} → {{ item.after_balance ?? '-' }}
                  </span>
                  <span>{{ item.operator_name || 'Sub2API' }}</span>
                </div>
                <div v-if="item.adjust_reason || item.notes" class="historyTimelineRemark">
                  {{ item.adjust_reason || item.notes }}
                </div>
                <div class="historyTimelineTime">{{ timeText(item) }}</div>
              </div>
            </div>
          </div>
          <a-pagination
            v-if="historyPage.total > historyPage.pageSize"
            size="small"
            :current="historyPage.current"
            :page-size="historyPage.pageSize"
            :total="historyPage.total"
            :show-size-changer="false"
            style="margin-top:12px;text-align:right;"
            @change="historyPageChange"
          />
        </a-spin>
      </template>
    </a-drawer>
  </section>
</template>
