<script setup lang="ts">
import {
  MinusCircleOutlined,
  PlusCircleOutlined,
  WalletOutlined,
} from '@ant-design/icons-vue'
import { App as AntApp } from 'ant-design-vue'
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import {
  createLedgerAdjustment,
  type AdjustmentRes,
} from '../api/ledger'
import { getUserFinanceSummary, type UserFinanceSummary } from '../api/finance'
import { getSub2BalanceHistory, getSub2Users, type Sub2BalanceHistoryItem, type Sub2User } from '../api/sub2api'
import AdjustmentForm, { type AdjustmentFormState } from '../components/ledger/AdjustmentForm.vue'
import SafeRichTextDisplay from '../components/richtext/SafeRichTextDisplay.vue'
import { useAppMode } from '../app/composables/useAppMode'

const { message, modal } = AntApp.useApp()
const { isAppMode } = useAppMode()
const route = useRoute()
const loading = ref(false)
const historyLoading = ref(false)
const summaryLoading = ref(false)
const submitting = ref(false)
const formKey = ref(0)
const users = ref<Sub2User[]>([])
const history = ref<Sub2BalanceHistoryItem[]>([])
const selected = ref<Sub2User | null>(null)
const selectedHistory = ref<Sub2BalanceHistoryItem | null>(null)
const userSummary = reactive<UserFinanceSummary>({ total_recharge: '0.00', total_gift: '0.00' })
const keyword = ref('')
const adjustPanel = ref<HTMLElement | null>(null)
const mobileFiltersOpen = ref(false)
const mobileDetailOpen = ref(false)
const mobileConfirmOpen = ref(false)
const mobileLoadingMore = ref(false)
const mobileHistoryLoadingMore = ref(false)
const loadError = ref('')
let historyVersion = 0
let summaryVersion = 0
let usersVersion = 0
const page = reactive({
  current: 1,
  pageSize: 10,
  total: 0,
})
const historyPage = reactive({ current: 1, pageSize: 8, total: 0 })
const form = reactive<AdjustmentFormState>({
  operation: 'increment' as 'increment' | 'decrement',
  amount: '',
  cash_amount: '',
  gift_quota_amount: '',
  adjust_reason: '充值',
  admin_notes: '',
})

const selectedName = computed(() => selected.value?.username || selected.value?.email || '-')
const totalQuota = computed(() => Number(userSummary.total_recharge) + Number(userSummary.total_gift))
const hasMoreUsers = computed(() => page.current * page.pageSize < page.total)
const hasMoreHistory = computed(() => historyPage.current * historyPage.pageSize < historyPage.total)
const mobileNextBalance = computed(() => {
  const current = Number(selected.value?.balance || 0)
  const amount = Number(form.amount || 0)
  const next = form.operation === 'decrement' ? current - amount : current + amount
  return next.toFixed(2)
})
const mobileFilterTags = computed(() => keyword.value.trim() ? [{ key: 'keyword', label: `关键词：${keyword.value.trim()}` }] : [])
async function loadUsers() {
  const version = ++usersVersion
  loading.value = true
  loadError.value = ''
  try {
    const res = await getSub2Users({
      page: page.current,
      page_size: page.pageSize,
      keyword: keyword.value,
    })
    if (version !== usersVersion) return
    users.value = res.items
    page.total = res.total
    if (selected.value) {
      const hit = res.items.find((item) => item.id === selected.value?.id)
      if (hit) selected.value = hit
    }
  } catch {
    if (version !== usersVersion) return
    loadError.value = '用户列表暂不可用，请重试'
    message.error('读取 Sub2API 用户失败')
  } finally {
    if (version === usersVersion) loading.value = false
  }
}

function search() {
  page.current = 1
  loadUsers()
}

function change(pageNo: number, size: number) {
  page.current = pageNo
  page.pageSize = size
  loadUsers()
}

function resetForm() {
  form.operation = 'increment'
  form.amount = ''
  form.cash_amount = ''
  form.gift_quota_amount = ''
  form.adjust_reason = '充值'
  form.admin_notes = ''
  formKey.value += 1
}

async function selectUser(row: Sub2User) {
  selected.value = row
  resetForm()
  historyPage.current = 1
  historyPage.total = 0
  loadHistory(row.id)
  loadUserSummary(row.id)
  await nextTick()
  adjustPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

function openMobileUser(row: Sub2User) {
  selected.value = row
  resetForm()
  historyPage.current = 1
  historyPage.total = 0
  mobileDetailOpen.value = true
  loadHistory(row.id)
  loadUserSummary(row.id)
}

function clearMobileFilter() {
  keyword.value = ''
  search()
}

async function loadUserSummary(id: number) {
  const version = ++summaryVersion
  summaryLoading.value = true
  try {
    const res = await getUserFinanceSummary(id)
    if (version !== summaryVersion || selected.value?.id !== id) return
    Object.assign(userSummary, res)
  } catch {
    if (version !== summaryVersion) return
    Object.assign(userSummary, { total_recharge: '0.00', total_gift: '0.00' })
    message.error('读取用户累计账务失败')
  } finally {
    if (version === summaryVersion) summaryLoading.value = false
  }
}

async function loadHistory(id: number, p = 1, append = false) {
  const version = ++historyVersion
  historyLoading.value = true
  try {
    const res = await getSub2BalanceHistory(id, {
      page: p,
      page_size: historyPage.pageSize,
    })
    if (version !== historyVersion || selected.value?.id !== id) return
    history.value = append ? [...history.value, ...res.items] : res.items
    historyPage.current = p
    historyPage.total = res.total
  } catch {
    if (version !== historyVersion) return
    if (!append) history.value = []
    message.error('读取充值记录失败')
  } finally {
    if (version === historyVersion) historyLoading.value = false
  }
}

async function loadMoreUsers() {
  if (loading.value || mobileLoadingMore.value || !hasMoreUsers.value) return
  const version = ++usersVersion
  mobileLoadingMore.value = true
  try {
    const next = page.current + 1
    const res = await getSub2Users({ page: next, page_size: page.pageSize, keyword: keyword.value })
    if (version !== usersVersion) return
    users.value = [...users.value, ...res.items]
    page.current = next
    page.total = res.total
  } catch {
    if (version !== usersVersion) return
    loadError.value = '用户列表暂不可用，请重试'
    message.error('加载更多用户失败')
  } finally {
    mobileLoadingMore.value = false
  }
}

async function loadMoreHistory() {
  if (!selected.value || historyLoading.value || mobileHistoryLoadingMore.value || !hasMoreHistory.value) return
  mobileHistoryLoadingMore.value = true
  try {
    const next = historyPage.current + 1
    await loadHistory(selected.value.id, next, true)
  } finally {
    mobileHistoryLoadingMore.value = false
  }
}

async function submitAdjust() {
  if (!selected.value) return
  if (form.adjust_reason === '异常修正' && !hasNotes(form.admin_notes)) {
    message.warning('异常修正必须填写备注')
    return
  }

  submitting.value = true
  try {
    const res = await createLedgerAdjustment({
      sub2api_user_id: selected.value.id,
      operation: form.operation,
      amount: form.amount,
      cash_amount: form.cash_amount,
      gift_quota_amount: form.gift_quota_amount,
      adjust_reason: form.adjust_reason,
      admin_notes: form.admin_notes,
    })
    message.success(res.message)
    mobileConfirmOpen.value = false
    resetForm()
    loadUsers()
    loadHistory(selected.value.id)
    loadUserSummary(selected.value.id)
  } catch (err) {
    const data = (err as { response?: { data?: AdjustmentRes } }).response?.data
    message.error(data?.message || '充值未确认成功')
  } finally {
    submitting.value = false
  }
}

function confirmAdjust() {
  if (!selected.value) return
  if (!form.amount || Number(form.amount) <= 0) {
    message.warning('请填写充值金额')
    return
  }
  if (form.adjust_reason === '异常修正' && !hasNotes(form.admin_notes)) {
    message.warning('异常修正必须填写备注')
    return
  }
  mobileConfirmOpen.value = true
}

function closeMobileDetail() {
  if (form.amount || hasNotes(form.admin_notes)) {
    mobileDetailOpen.value = true
    modal.confirm({
      title: '放弃未提交的充值？',
      content: '当前表单还有未提交内容。',
      okText: '放弃',
      cancelText: '继续编辑',
      onOk: () => {
        mobileDetailOpen.value = false
        resetForm()
      },
    })
    return
  }
  mobileDetailOpen.value = false
}


function moneyText(val: number | string) {
  return Number(val || 0).toFixed(2)
}

function absMoneyText(val: number | string) {
  return Math.abs(Number(val || 0)).toFixed(2)
}

function hasNotes(val: string) {
  return val.replace(/<[^>]*>/g, '').trim() !== '' || val.includes('<img')
}

function opSign(row: Sub2BalanceHistoryItem) {
  return row.operation === 'increment' ? '+' : '-'
}

function timeText(row: Sub2BalanceHistoryItem) {
  return row.used_at || row.created_at || '-'
}

function openHistory(row: Sub2BalanceHistoryItem) {
  selectedHistory.value = row
}

async function initPage() {
  await loadUsers()
  const val = Array.isArray(route.query.user_id) ? route.query.user_id[0] : route.query.user_id
  const id = Number(val)
  if (!id) return

  const current = users.value.find(row => row.id === id)
  if (current) return void selectUser(current)

  try {
    const res = await getSub2Users({ page: 1, page_size: 1, user_id: id })
    if (res.items[0]) {
      users.value = [res.items[0], ...users.value]
      selectUser(res.items[0])
    }
  } catch {
    message.error('读取指定用户失败')
  }
}

onMounted(initPage)
</script>

<template>
  <section v-if="isAppMode" class="app-page app-quota-page">
    <header class="app-header">
      <div><span class="app-eyebrow">Sub2API</span><h1>用户充值</h1></div>
      <strong class="app-count">{{ page.total }} 人</strong>
    </header>
    <div class="app-toolbar">
      <a-input-search v-model:value="keyword" class="app-search" placeholder="邮箱或用户名" allow-clear enter-button @search="search" />
      <a-button @click="mobileFiltersOpen = !mobileFiltersOpen">筛选<span v-if="mobileFilterTags.length">（{{ mobileFilterTags.length }}）</span></a-button>
    </div>
    <div v-if="mobileFiltersOpen" class="app-filter-sheet" @keydown.esc="mobileFiltersOpen = false">
      <label class="app-field"><span>用户关键词</span><a-input v-model:value="keyword" allow-clear @press-enter="search" /></label>
      <div class="app-filter-actions"><a-button @click="clearMobileFilter">重置</a-button><a-button type="primary" @click="mobileFiltersOpen = false; search()">查询</a-button></div>
    </div>
    <div v-if="mobileFilterTags.length" class="app-filter-tags" aria-label="已生效筛选">
      <button v-for="tag in mobileFilterTags" :key="tag.key" type="button" class="app-filter-tag" @click="clearMobileFilter">{{ tag.label }} ×</button>
    </div>
    <div v-if="loadError" class="app-error-bar">
      <a-alert type="error" show-icon :message="loadError" />
      <a-button size="small" @click="loadUsers">重试</a-button>
    </div>
    <div class="app-summary-grid app-quota-summary">
      <div><span>用户数</span><strong>{{ page.total }}</strong></div>
      <div><span>已选用户</span><strong>{{ selected ? 1 : 0 }}</strong></div>
    </div>
    <a-spin :spinning="loading && users.length === 0">
      <a-empty v-if="!loading && !loadError && users.length === 0" description="暂无可充值用户数据" />
      <div v-else class="app-card-list">
        <article v-for="item in users" :key="item.id" class="app-card app-quota-user-card" tabindex="0" role="button" @click="openMobileUser(item)" @keydown.enter="openMobileUser(item)">
          <div class="app-card-top">
            <div class="app-card-title"><strong>{{ item.username || item.email }}</strong><span>{{ item.email }} · ID {{ item.id }}</span></div>
            <span class="app-status">{{ item.status || '-' }}</span>
          </div>
          <div class="app-card-metrics"><div><span>当前余额</span><strong class="app-money">{{ moneyText(item.balance) }}</strong></div><div><span>累计充值</span><strong>{{ moneyText(item.total_recharged) }}</strong></div></div>
          <div class="app-card-foot"><span>{{ item.last_used_at || '从未使用' }}</span><button type="button" class="app-link-button" @click.stop="openMobileUser(item)">充值</button></div>
        </article>
      </div>
    </a-spin>
    <button v-if="hasMoreUsers" type="button" class="app-load-more" :disabled="mobileLoadingMore" @click="loadMoreUsers">{{ mobileLoadingMore ? '加载中…' : '加载更多' }}</button>
    <p v-else-if="users.length" class="app-end-state">已显示全部用户</p>

    <a-drawer
      v-model:open="mobileDetailOpen"
      placement="right"
      :width="'100%'"
      :title="selected ? `${selectedName} · 充值` : '用户充值'"
      @close="closeMobileDetail"
    >
      <template v-if="selected">
        <div class="app-detail app-quota-detail">
          <div class="app-detail-hero">
            <span class="app-avatar">{{ selectedName.slice(0, 1).toUpperCase() }}</span>
            <div><h2>{{ selectedName }}</h2><p>{{ selected.email }} · ID {{ selected.id }}</p></div>
          </div>
          <div class="app-summary-grid app-detail-summary">
            <div><span>当前余额</span><strong class="app-money">{{ moneyText(selected.balance) }}</strong></div>
            <div><span>累计充值</span><strong>{{ moneyText(userSummary.total_recharge) }}</strong></div>
            <div><span>累计赠送</span><strong>{{ moneyText(userSummary.total_gift) }}</strong></div>
            <div><span>总额度</span><strong>{{ moneyText(totalQuota) }}</strong></div>
          </div>
          <section class="app-detail-section">
            <div class="app-section-head"><h2>充值入账</h2><span>提交前确认金额与余额变化</span></div>
            <AdjustmentForm :key="formKey" v-model:value="form" :current-balance="selected.balance" />
            <div class="app-action-bar"><a-button @click="resetForm">重置</a-button><a-button type="primary" :loading="submitting" @click="confirmAdjust">确认充值</a-button></div>
            <p class="app-note">充值仅在 Sub2API 真实入账并二次确认成功后记账。</p>
          </section>
          <section class="app-detail-section">
            <div class="app-section-head"><h2>充值记录</h2><span>{{ historyPage.total }} 条</span></div>
            <a-spin :spinning="historyLoading && history.length === 0">
              <a-empty v-if="!historyLoading && history.length === 0" description="暂无充值记录" />
              <div v-else class="app-history-list">
                <button v-for="item in history" :key="item.id" type="button" class="app-history-card app-history-button" @click="openHistory(item)">
                  <div class="app-history-top"><strong :class="item.operation === 'increment' ? 'app-positive' : 'app-danger'">{{ opSign(item) }}{{ absMoneyText(item.value) }}</strong><span>{{ item.operator_name || 'Sub2API' }}</span></div>
                  <div class="app-history-meta"><span>{{ item.before_balance ?? '-' }} → {{ item.after_balance ?? '-' }}</span><span>{{ timeText(item) }}</span></div>
                </button>
              </div>
            </a-spin>
            <button v-if="hasMoreHistory" type="button" class="app-load-more" :disabled="mobileHistoryLoadingMore" @click="loadMoreHistory">{{ mobileHistoryLoadingMore ? '加载中…' : '加载更多记录' }}</button>
          </section>
        </div>
      </template>
    </a-drawer>

    <a-modal v-model:open="mobileConfirmOpen" title="确认充值" ok-text="确认提交" cancel-text="返回修改" :confirm-loading="submitting" :width="'calc(100vw - 24px)'" @ok="submitAdjust">
      <div v-if="selected" class="app-confirm-summary">
        <p><span>用户</span><strong>{{ selectedName }}（{{ selected.email }}）</strong></p>
        <p><span>操作</span><strong>{{ form.operation === 'increment' ? '增加额度' : '扣减额度' }}</strong></p>
        <p><span>调整金额</span><strong>{{ moneyText(form.amount) }}</strong></p>
        <p><span>现金入账</span><strong>{{ moneyText(form.cash_amount) }}</strong></p>
        <p><span>赠送额度</span><strong>{{ moneyText(form.gift_quota_amount) }}</strong></p>
        <p><span>余额变化</span><strong>{{ moneyText(selected.balance) }} → {{ mobileNextBalance }}</strong></p>
        <p><span>原因</span><strong>{{ form.adjust_reason }}</strong></p>
      </div>
    </a-modal>

    <a-modal :open="!!selectedHistory" title="充值记录详情" :footer="null" :width="'calc(100vw - 24px)'" @cancel="selectedHistory = null">
      <div v-if="selectedHistory" class="app-confirm-summary">
        <p><span>操作人员</span><strong>{{ selectedHistory.operator_name || 'Sub2API' }}</strong></p>
        <p><span>充值账号</span><strong>{{ selectedHistory.adjusted_account || selected?.email || '-' }}</strong></p>
        <p><span>充值金额</span><strong>{{ opSign(selectedHistory) }} {{ absMoneyText(selectedHistory.value) }}</strong></p>
        <p><span>充值前后</span><strong>{{ selectedHistory.before_balance ?? '-' }} → {{ selectedHistory.after_balance ?? '-' }}</strong></p>
        <p><span>时间</span><strong>{{ timeText(selectedHistory) }}</strong></p>
        <p><span>业务单号</span><strong>{{ selectedHistory.ledger_no || '-' }}</strong></p>
        <p><span>原因</span><strong>{{ selectedHistory.adjust_reason || selectedHistory.type || '-' }}</strong></p>
        <SafeRichTextDisplay v-if="selectedHistory.admin_notes" :value="selectedHistory.admin_notes" compact />
        <pre v-else-if="selectedHistory.notes">{{ selectedHistory.notes }}</pre>
      </div>
    </a-modal>
  </section>

  <section v-else class="page quotaPage">
    <div class="quotaWorkGrid">
      <section class="panel quotaUserPanel">
        <div class="panelHead">
          <h2>选择用户</h2>
        </div>
      <a-input-search
        v-model:value="keyword"
          class="fullField"
        placeholder="邮箱或用户名"
        allow-clear
        enter-button
        @search="search"
      />
        <a-spin :spinning="loading">
          <a-empty v-if="!loading && users.length === 0" description="暂无可充值用户数据" />
          <div v-else class="quotaUserList">
            <button
              v-for="item in users"
              :key="item.id"
              type="button"
              class="quotaUserItem"
              :class="{ active: selected?.id === item.id }"
              @click="selectUser(item)"
            >
              <span class="quotaAvatar">{{ (item.username || item.email || 'U').slice(0, 1).toUpperCase() }}</span>
              <span class="quotaUserInfo">
                <strong>{{ item.username || item.email }}</strong>
                <em>ID: {{ item.id }} · {{ item.email }}</em>
              </span>
              <span class="quotaUserBalance">{{ moneyText(item.balance) }}</span>
            </button>
          </div>
        </a-spin>
        <a-pagination
          size="small"
          :current="page.current"
          :page-size="page.pageSize"
          :total="page.total"
          :show-size-changer="false"
          @change="change"
        />
      </section>

      <section ref="adjustPanel" class="quotaAdjustStack">
        <div v-if="!selected" class="panel quotaEmpty">
          <WalletOutlined />
          <h2>请从左侧选择一个用户</h2>
        </div>

        <template v-else>
          <section class="panel quotaSelected">
            <div class="quotaSelectedUser">
              <span class="quotaAvatar large">{{ selectedName.slice(0, 1).toUpperCase() }}</span>
              <div>
                <h2>{{ selectedName }}</h2>
                <p>{{ selected.email }} · ID: {{ selected.id }}</p>
                <div class="quotaInlineStats" :class="{ loading: summaryLoading }">
                  <span>累积充值 <strong class="money">{{ moneyText(userSummary.total_recharge) }}</strong></span>
                  <span>累积赠送 <strong>{{ moneyText(userSummary.total_gift) }}</strong></span>
                  <span>总额度 <strong>{{ moneyText(totalQuota) }}</strong></span>
                </div>
              </div>
            </div>
          </section>

          <section class="panel quotaAdjustPanel">
            <div class="panelHead">
              <h2>充值入账</h2>
            </div>
            <AdjustmentForm :key="formKey" v-model:value="form" :current-balance="selected.balance" />
            <div class="quotaFormActions">
              <a-button @click="resetForm">重置</a-button>
              <a-button type="primary" :loading="submitting" @click="submitAdjust">确认充值</a-button>
            </div>
            <p class="quotaWarn">
              * 充值入账只有在 Sub2API 真实入账并二次确认成功后，才会生成成功记录和财务账本。
            </p>
          </section>

          <section class="panel quotaHistoryPanel">
            <div class="panelHead">
              <h2>充值记录</h2>
            </div>
            <a-spin :spinning="historyLoading">
              <a-empty v-if="!historyLoading && history.length === 0" description="暂无充值记录" />
              <div v-else-if="history.length > 0" class="quotaHistoryList">
                <button v-for="item in history" :key="item.id" type="button" class="quotaHistoryItem" @click="openHistory(item)">
                  <div class="historyIcon" :class="item.operation">
                    <PlusCircleOutlined v-if="item.operation === 'increment'" />
                    <MinusCircleOutlined v-else />
                  </div>
                  <div class="historyInfo">
                    <strong>操作人员：{{ item.operator_name || 'Sub2API' }}</strong>
                    <span>充值账号：{{ item.adjusted_account || selected.email }}</span>
                    <span>充值前：{{ item.before_balance ?? '-' }} · 充值后：{{ item.after_balance ?? '-' }}</span>
                    <em>时间：{{ timeText(item) }}</em>
                  </div>
                  <strong class="historyMoney" :class="item.operation">
                    {{ opSign(item) }} {{ absMoneyText(item.value) }}
                  </strong>
                </button>
              </div>
            </a-spin>
          </section>
        </template>
      </section>
    </div>

    <a-modal
      :open="!!selectedHistory"
      title="充值记录详情"
      :footer="null"
      @cancel="selectedHistory = null"
    >
      <div v-if="selectedHistory" class="historyDetail">
        <p><span>操作人员</span><strong>{{ selectedHistory.operator_name || 'Sub2API' }}</strong></p>
        <p><span>充值账号</span><strong>{{ selectedHistory.adjusted_account }}</strong></p>
        <p><span>充值金额</span><strong>{{ opSign(selectedHistory) }} {{ absMoneyText(selectedHistory.value) }}</strong></p>
        <p><span>充值前</span><strong>{{ selectedHistory.before_balance ?? '-' }}</strong></p>
        <p><span>充值后</span><strong>{{ selectedHistory.after_balance ?? '-' }}</strong></p>
        <p><span>时间</span><strong>{{ timeText(selectedHistory) }}</strong></p>
        <p><span>业务单号</span><strong>{{ selectedHistory.ledger_no || '-' }}</strong></p>
        <p><span>原因</span><strong>{{ selectedHistory.adjust_reason || selectedHistory.type || '-' }}</strong></p>
        <div v-if="selectedHistory.admin_notes" class="historyDetailNotes">
          <span>备注</span>
          <SafeRichTextDisplay :value="selectedHistory.admin_notes" compact />
        </div>
        <div v-else-if="selectedHistory.notes" class="historyDetailNotes">
          <span>Sub2API 备注</span>
          <pre>{{ selectedHistory.notes }}</pre>
        </div>
      </div>
    </a-modal>
  </section>
</template>

<style scoped>
.app-page { display: grid; gap: 14px; min-width: 0; padding-bottom: 24px; }
.app-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.app-header h1 { margin: 2px 0 0; color: var(--heading); font-size: 22px; }
.app-eyebrow { color: var(--muted); font-size: 12px; }
.app-count { color: var(--primary); font-variant-numeric: tabular-nums; }
.app-toolbar { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 8px; align-items: center; }
.app-search { min-width: 0; }
.app-filter-sheet { display: grid; gap: 12px; padding: 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); }
.app-field { display: grid; gap: 6px; }
.app-field > span { color: var(--muted); font-size: 12px; }
.app-filter-actions, .app-action-bar { display: flex; justify-content: flex-end; gap: 8px; }
.app-action-bar { position: sticky; bottom: 0; z-index: 5; padding: 10px 0 calc(10px + var(--app-safe-bottom)); background: color-mix(in srgb, var(--surface) 88%, transparent); backdrop-filter: blur(10px); }
.app-action-bar > * { flex: 1; }
.app-error-bar { display: flex; align-items: center; gap: 8px; }
.app-error-bar .ant-alert { min-width: 0; flex: 1; }
.app-filter-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.app-filter-tag { padding: 5px 8px; border: 1px solid var(--border); border-radius: 999px; background: var(--surface2); color: var(--text); font-size: 12px; }
.app-summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.app-summary-grid > div { min-width: 0; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); }
.app-summary-grid span { display: block; color: var(--muted); font-size: 12px; }
.app-summary-grid strong { display: block; margin-top: 3px; color: var(--heading); font-size: 16px; font-variant-numeric: tabular-nums; }
.app-card-list, .app-history-list { display: grid; gap: 8px; }
.app-card { min-width: 0; padding: 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); }
.app-quota-user-card { cursor: pointer; }
.app-quota-user-card:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
.app-card-top, .app-card-foot, .app-history-top, .app-history-meta { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.app-card-title { display: grid; min-width: 0; gap: 3px; }
.app-card-title strong, .app-card-title span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.app-card-title span, .app-card-foot, .app-history-meta { color: var(--muted); font-size: 12px; }
.app-status { color: var(--muted); }
.app-card-metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin: 12px 0; }
.app-card-metrics > div { min-width: 0; padding: 8px; border-radius: 6px; background: var(--surface2); }
.app-card-metrics span { display: block; color: var(--muted); font-size: 12px; }
.app-card-metrics strong { display: block; margin-top: 3px; color: var(--heading); font-variant-numeric: tabular-nums; }
.app-link-button { padding: 0; border: 0; background: transparent; color: var(--primary); cursor: pointer; }
.app-load-more { width: 100%; min-height: 40px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--primary); cursor: pointer; }
.app-load-more:disabled { cursor: wait; opacity: 0.6; }
.app-end-state, .app-note { margin: 0; color: var(--muted); font-size: 12px; text-align: center; }
.app-detail { display: grid; gap: 16px; padding-bottom: 24px; }
.app-detail-hero { display: flex; align-items: center; gap: 12px; }
.app-avatar { display: inline-flex; width: 44px; height: 44px; align-items: center; justify-content: center; flex: 0 0 auto; border-radius: 50%; background: var(--primary-soft); color: var(--primary); font-weight: 700; }
.app-detail-hero h2 { margin: 0; color: var(--heading); font-size: 18px; }
.app-detail-hero p { margin: 3px 0 0; color: var(--muted); font-size: 12px; overflow-wrap: anywhere; }
.app-detail-section { display: grid; gap: 10px; }
.app-section-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.app-section-head h2 { margin: 0; color: var(--heading); font-size: 16px; }
.app-section-head span { color: var(--muted); font-size: 12px; }
.app-history-card { min-width: 0; padding: 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); text-align: left; }
.app-history-button { width: 100%; cursor: pointer; }
.app-history-top strong { font-size: 16px; font-variant-numeric: tabular-nums; }
.app-history-top span { color: var(--muted); font-size: 12px; }
.app-history-meta { justify-content: flex-start; flex-wrap: wrap; margin-top: 6px; }
.app-confirm-summary { display: grid; gap: 9px; }
.app-confirm-summary p { display: flex; justify-content: space-between; gap: 12px; margin: 0; }
.app-confirm-summary p span { color: var(--muted); }
.app-confirm-summary p strong { color: var(--heading); text-align: right; overflow-wrap: anywhere; }
.app-money, .app-positive { color: var(--success) !important; font-variant-numeric: tabular-nums; }
.app-danger { color: var(--danger) !important; }
pre { max-width: 100%; overflow: auto; white-space: pre-wrap; }
@media (max-width: 360px) {
  .app-card-top { align-items: flex-start; flex-direction: column; }
}
</style>
