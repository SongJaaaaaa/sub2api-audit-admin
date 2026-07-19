<script setup lang="ts">
import { GiftOutlined, HistoryOutlined, ImportOutlined, MinusCircleOutlined, PlusCircleOutlined } from '@ant-design/icons-vue'
import type { TableProps } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import type { Dayjs } from 'dayjs'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { createBatchGift } from '../api/ledger'
import { getSub2BalanceHistory, getSub2Users, type Sub2BalanceHistoryItem, type Sub2User, type UserSummary } from '../api/sub2api'

import SafeRichTextDisplay from '../components/richtext/SafeRichTextDisplay.vue'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useTableColumns } from '../composables/useTableColumns'
const router = useRouter()
const loading = ref(false)
const historyLoading = ref(false)
const drawerOpen = ref(false)
const batchOpen = ref(false)
const batchSubmitting = ref(false)
const importOpen = ref(false)
const importLoading = ref(false)
const importText = ref('')
const importedUsers = ref<Sub2User[]>([])
const users = ref<Sub2User[]>([])
const loaded = ref(false)
const loadError = ref('')
const summary = reactive<UserSummary>({ user_count: 0, active_count: 0, disabled_count: 0, balance_total: '0.00', average_balance: '0.00', negative_balance_count: 0, zero_balance_count: 0 })
const history = ref<Sub2BalanceHistoryItem[]>([])
const selectedUser = ref<Sub2User | null>(null)
const selectedIds = ref<number[]>([])
const historyPage = reactive({ current: 1, pageSize: 10, total: 0 })
const keyword = ref('')
const userFilter = ref<'zero_balance' | 'negative_balance' | 'disabled' | ''>('')
const balanceSort = ref<'' | 'asc' | 'desc'>('')
const lastUsedRange = ref<[Dayjs, Dayjs] | null>(null)
const batchMode = ref<'selected' | 'all' | 'imported'>('selected')
const batchProgress = ref('')
const batchForm = reactive({ amount: '', admin_notes: '', include_revenue: false })
const page = reactive({
  current: 1,
  pageSize: 20,
  total: 0,
  showSizeChanger: true,
  pageSizeOptions: ['10', '20', '50', '100'],
  showTotal: (total: number) => `共 ${total} 条`,
})

const allColumns = [
  { title: 'ID', dataIndex: 'id', width: 90, defaultHidden: true },
  { title: '邮箱', dataIndex: 'email', width: 320 },
  { title: '角色', dataIndex: 'role', width: 100 },
  { title: '余额', dataIndex: 'balance', align: 'right', width: 120, sorter: true },
  { title: 'Sub2API 累计充值字段', dataIndex: 'total_recharged', align: 'right', width: 190, defaultHidden: true },
  { title: '状态', dataIndex: 'status', width: 110 },
  { title: '近期使用时间', dataIndex: 'last_used_at', width: 180 },
  { title: '创建时间', dataIndex: 'created_at', width: 180 },
  { title: '操作', dataIndex: 'action', fixed: 'right', width: 90 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resizeColumn, resetColumns } = useTableColumns('sub2api-users-columns-v2', allColumns, 1280)
const selectedUsers = computed(() => users.value.filter(row => selectedIds.value.includes(row.id)))
const batchUsers = computed(() => batchMode.value === 'imported' ? importedUsers.value : selectedUsers.value)
const batchCount = computed(() => {
  if (batchMode.value === 'imported') return importedUsers.value.length
  return batchMode.value === 'selected' ? selectedIds.value.length : page.total
})
const actionName = computed(() => batchForm.include_revenue ? '入账' : '赠送')
const rowSelection = computed(() => ({
  selectedRowKeys: selectedIds.value,
  onChange: (keys: (string | number)[]) => { selectedIds.value = keys.map(Number) },
}))

function userParams(pageNo = page.current, pageSize = page.pageSize) {
  return {
    page: pageNo,
    page_size: pageSize,
    keyword: keyword.value,
    user_filter: userFilter.value,
    last_used_start: lastUsedRange.value?.[0].format('YYYY-MM-DD'),
    last_used_end: lastUsedRange.value?.[1].format('YYYY-MM-DD'),
    sort_by: balanceSort.value ? 'balance' as const : undefined,
    sort_order: balanceSort.value || undefined,
  }
}

async function loadUsers() {
  loading.value = true
  try {
    const res = await getSub2Users(userParams())
    users.value = res.items
    page.total = res.total
    Object.assign(summary, res.summary)
    loaded.value = true
    loadError.value = ''
    selectedIds.value = []
  } catch {
    loadError.value = 'Sub2API 用户数据暂不可用，不展示零值。'
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

function changeUserFilter() {
  search()
}

const change: TableProps['onChange'] = (pager, _filters, sorter) => {
  const active = Array.isArray(sorter) ? sorter[0] : sorter
  balanceSort.value = active.field === 'balance' && active.order
    ? (active.order === 'ascend' ? 'asc' : 'desc')
    : ''
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || page.pageSize
  loadUsers()
}

async function copyEmail(email: string) {
  try {
    await navigator.clipboard.writeText(email)
    message.success('邮箱已复制')
  } catch {
    message.error('邮箱复制失败')
  }
}

function openBatchGift() {
  if (page.total === 0) {
    message.warning('当前没有可赠送的用户')
    return
  }
  batchMode.value = selectedIds.value.length > 0 ? 'selected' : 'all'
  importedUsers.value = []
  resetBatchForm()
  batchOpen.value = true
}

function resetBatchForm() {
  batchForm.amount = ''
  batchForm.admin_notes = '管理员赠送'
  batchForm.include_revenue = false
  batchProgress.value = ''
}

function openImport() {
  importText.value = ''
  importedUsers.value = []
  importOpen.value = true
}

async function resolveImportedUsers() {
  const emails = [...new Set(importText.value.split(/\s+/).map(val => val.trim().toLowerCase()).filter(Boolean))]
  if (emails.length === 0) return void message.warning('请输入用户邮箱')

  importLoading.value = true
  try {
    const res = await getSub2Users({ page: 1, page_size: 100, emails })
    if (res.items.length === 0) return void message.warning('未找到匹配的 Sub2API 用户')

    const found = new Set(res.items.map(row => row.email.toLowerCase()))
    const missing = emails.filter(email => !found.has(email))
    importedUsers.value = res.items
    batchMode.value = 'imported'
    resetBatchForm()
    importOpen.value = false
    batchOpen.value = true
    if (missing.length > 0) message.warning(`${missing.length} 个邮箱未找到，已导入 ${res.items.length} 个用户`)
  } catch {
    message.error('读取导入用户失败')
  } finally {
    importLoading.value = false
  }
}

async function loadAllUserIds() {
  const size = 100
  const first = await getSub2Users(userParams(1, size))
  const ids = first.items.map(row => row.id)
  const pages = Math.ceil(first.total / size)

  for (let p = 2; p <= pages; p += 1) {
    batchProgress.value = `正在读取全部用户：${Math.min(ids.length, first.total)} / ${first.total}`
    const res = await getSub2Users(userParams(p, size))
    ids.push(...res.items.map(row => row.id))
  }

  return ids
}

async function submitBatchGift() {
  if (batchCount.value === 0) return void message.warning(`没有可${actionName.value}的用户`)
  if (!batchForm.amount || Number(batchForm.amount) <= 0) return void message.warning(`请填写${actionName.value}额度`)

  batchSubmitting.value = true
  let done = 0
  try {
    const ids = batchMode.value === 'imported'
      ? importedUsers.value.map(row => row.id)
      : batchMode.value === 'selected' ? [...selectedIds.value] : await loadAllUserIds()
    let success = 0
    let failed = 0
    const size = 20

    for (let i = 0; i < ids.length; i += size) {
      const chunk = ids.slice(i, i + size)
      batchProgress.value = `正在${actionName.value}：${i} / ${ids.length}`
      const res = await createBatchGift({
        user_ids: chunk,
        amount: batchForm.amount,
        admin_notes: batchForm.admin_notes,
        include_revenue: batchForm.include_revenue,
      })
      success += res.success_count
      failed += res.failed_count
      done += chunk.length
    }

    const result = `批量${actionName.value}完成：成功 ${success} 个，失败 ${failed} 个`
    if (failed > 0) {
      message.warning(result)
    } else {
      message.success(result)
    }
    batchOpen.value = false
    loadUsers()
  } catch (err) {
    const data = (err as { response?: { data?: { message?: string } } }).response?.data
    const reason = data?.message || `批量${actionName.value}失败`
    message.error(done > 0 ? `${reason}，已完成 ${done} 人，剩余操作已停止` : reason)
  } finally {
    batchSubmitting.value = false
  }
}

function changeAction() {
  batchForm.admin_notes = batchForm.include_revenue ? '管理员入账' : '管理员赠送'
}

function rowProps(row: Sub2User) {
  return {
    onClick: (event: MouseEvent) => {
      const target = event.target as HTMLElement
      if (target.closest('button, a, input, .ant-checkbox-wrapper')) return
      router.push({ path: '/users-quota', query: { user_id: row.id } })
    },
  }
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

function moneyText(v: string | number) {
  return Number(v || 0).toFixed(2)
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
    <div class="pageHead pageHeadActionsOnly">
      <div class="headActions userFilters">
        <div v-if="loaded" class="compactSummary">
          <span>用户 {{ summary.user_count }}</span>
          <span>余额 <strong>{{ moneyText(summary.balance_total) }}</strong></span>
        </div>
        <a-select v-model:value="userFilter" class="userFilterSelect" @change="changeUserFilter">
          <a-select-option value="">全部用户</a-select-option>
          <a-select-option value="zero_balance">零余额用户</a-select-option>
          <a-select-option value="negative_balance">负余额用户</a-select-option>
          <a-select-option value="disabled">禁用用户</a-select-option>
        </a-select>
        <a-range-picker
          v-model:value="lastUsedRange"
          class="lastUsedFilter"
          :placeholder="['近期使用开始', '近期使用结束']"
          @change="search"
        />
        <a-input-search
          v-model:value="keyword"
          class="search"
          placeholder="邮箱或用户名"
          allow-clear
          enter-button
          @search="search"
        />
        <a-button @click="openImport">
          <template #icon><ImportOutlined /></template>
          导入赠送用户
        </a-button>
        <a-button type="primary" @click="openBatchGift">
          <template #icon><GiftOutlined /></template>
          批量赠送<span v-if="selectedIds.length">（{{ selectedIds.length }}）</span>
        </a-button>
      </div>
    </div>

    <a-alert v-if="loadError" class="loadAlert" type="error" show-icon :message="loadError" />

    <div class="tableTools">
      <ColumnSettings v-model:value="visibleCols" v-model:width="tableWidth" :options="colOptions" @reset="resetColumns" />
    </div>
    <a-table
      row-key="id"
      :columns="columns"
      :data-source="users"
      :loading="loading"
      :locale="{ emptyText: '暂无 Sub2API 用户数据' }"
      :pagination="page"
      :row-selection="rowSelection"
      :custom-row="rowProps"
      :scroll="{ x: tableWidth }"
      @resize-column="resizeColumn"
      @change="change"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'email'">
          <a-tooltip title="点击复制邮箱">
            <button type="button" class="copyEmail" @click.stop="copyEmail(record.email)">
              <span>{{ record.email }}</span>
            </button>
          </a-tooltip>
        </template>
        <template v-if="column.dataIndex === 'status'">
          <a-tag :color="record.status === 'active' ? 'green' : 'default'">
            {{ record.status || '-' }}
          </a-tag>
        </template>
        <template v-if="column.dataIndex === 'balance'">
          <span class="money">{{ moneyText(record.balance) }}</span>
        </template>
        <template v-if="column.dataIndex === 'total_recharged'">
          <span class="money">{{ moneyText(record.total_recharged) }}</span>
        </template>
        <template v-if="column.dataIndex === 'last_used_at'">
          {{ record.last_used_at || '从未使用' }}
        </template>
        <template v-if="column.dataIndex === 'action'">
          <a-button size="small" @click.stop="openHistory(record)">
            <template #icon><HistoryOutlined /></template>
            详情
          </a-button>
        </template>
      </template>
    </a-table>

    <a-modal
      v-model:open="importOpen"
      title="导入赠送用户"
      ok-text="读取用户"
      cancel-text="取消"
      :confirm-loading="importLoading"
      @ok="resolveImportedUsers"
    >
      <a-form layout="vertical">
        <a-form-item label="用户邮箱">
          <a-textarea
            v-model:value="importText"
            :rows="8"
            placeholder="1ssssxxx@qq.com&#10;123123123@163.com"
          />
        </a-form-item>
        <a-alert type="info" show-icon message="多个邮箱可使用空格或换行分隔" />
      </a-form>
    </a-modal>

    <a-modal
      v-model:open="batchOpen"
      title="批量赠送 / 入账"
      :ok-text="`确认${actionName} ${batchCount} 人`"
      cancel-text="取消"
      :confirm-loading="batchSubmitting"
      :ok-button-props="{ disabled: batchCount === 0 }"
      :cancel-button-props="{ disabled: batchSubmitting }"
      :closable="!batchSubmitting"
      :mask-closable="!batchSubmitting"
      @ok="submitBatchGift"
    >
      <a-form layout="vertical">
        <a-form-item v-if="batchMode === 'imported'" :label="`已导入 ${importedUsers.length} 人`">
          <div class="selectedUsers">
            <a-tag v-for="item in importedUsers" :key="item.id">{{ item.email }}</a-tag>
          </div>
        </a-form-item>
        <a-form-item v-else label="操作范围">
          <a-radio-group v-model:value="batchMode" class="batchScope">
            <a-radio value="selected" :disabled="selectedIds.length === 0">已勾选用户（{{ selectedIds.length }} 人）</a-radio>
            <a-radio value="all">全部筛选结果（{{ page.total }} 人）</a-radio>
          </a-radio-group>
        </a-form-item>
        <a-alert
          v-if="batchMode === 'all'"
          type="warning"
          show-icon
          :message="`将向当前筛选结果中的 ${page.total} 位用户${actionName}`"
        />
        <a-form-item v-else-if="batchMode === 'selected'" :label="`已勾选 ${selectedIds.length} 人`">
          <div class="selectedUsers">
            <a-tag v-for="item in batchUsers" :key="item.id">
              {{ item.username || item.email || `用户 #${item.id}` }}
            </a-tag>
          </div>
        </a-form-item>
        <a-form-item :label="`每人${actionName}额度`" required>
          <a-input v-model:value="batchForm.amount" placeholder="0.00" />
        </a-form-item>
        <a-form-item label="操作类型">
          <a-radio-group v-model:value="batchForm.include_revenue" class="batchScope" @change="changeAction">
            <a-radio :value="false">赠送</a-radio>
            <a-radio :value="true">入账</a-radio>
          </a-radio-group>
        </a-form-item>
        <a-form-item label="备注">
          <a-textarea v-model:value="batchForm.admin_notes" :rows="3" :placeholder="`管理员${actionName}`" />
        </a-form-item>
        <a-alert v-if="batchProgress" type="info" show-icon :message="batchProgress" />
      </a-form>
    </a-modal>

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
              <span>Sub2API 累计充值字段</span>
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
                <div v-if="item.admin_notes" class="historyTimelineRemark">
                  <SafeRichTextDisplay :value="item.admin_notes" compact />
                </div>
                <div v-else-if="item.adjust_reason || item.notes" class="historyTimelineRemark">
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

<style scoped>
.loadAlert { margin-bottom: 14px; }
.userFilters { display: flex; width: 100%; gap: 10px; align-items: center; flex-wrap: nowrap; overflow-x: auto; }
.userFilterSelect { flex: 0 0 140px; width: 140px; }
.lastUsedFilter { flex: 0 0 230px; width: 230px; }
.search { flex: 0 1 190px; width: 190px; min-width: 160px; }
.userFilters > button { flex: 0 0 auto; }
.compactSummary { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 10px; height: 32px; padding: 0 10px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 8px; background: var(--card-bg, #fff); color: var(--text-secondary, #7a8395); font-size: 12px; }
.compactSummary strong { color: var(--heading, #1f2937); font-variant-numeric: tabular-nums; }
.selectedUsers { display: flex; flex-wrap: wrap; gap: 6px; max-height: 150px; overflow: auto; padding: 8px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 8px; }
.batchScope { display: grid; gap: 10px; }
.copyEmail { display: inline-flex; max-width: 100%; align-items: center; gap: 6px; padding: 0; border: 0; background: none; color: #1677ff; cursor: pointer; }
.copyEmail span { overflow: hidden; text-overflow: ellipsis; }
:deep(.ant-table-tbody > tr) { cursor: pointer; }
@media (max-width: 760px) { .userFilters { padding-bottom: 4px; } }
</style>
