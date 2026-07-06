<script setup lang="ts">
import {
  AuditOutlined,
  CreditCardOutlined,
  MinusCircleOutlined,
  PlusCircleOutlined,
  UserOutlined,
  WalletOutlined,
} from '@ant-design/icons-vue'
import { Modal, message } from 'ant-design-vue'
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import {
  createLedgerAdjustment,
  type AdjustmentRes,
} from '../api/ledger'
import { getSub2BalanceHistory, getSub2Users, type Sub2BalanceHistoryItem, type Sub2User } from '../api/sub2api'
import AdjustmentForm, { type AdjustmentFormState } from '../components/ledger/AdjustmentForm.vue'

const loading = ref(false)
const historyLoading = ref(false)
const submitting = ref(false)
const users = ref<Sub2User[]>([])
const history = ref<Sub2BalanceHistoryItem[]>([])
const selected = ref<Sub2User | null>(null)
const selectedHistory = ref<Sub2BalanceHistoryItem | null>(null)
const keyword = ref('')
const adjustPanel = ref<HTMLElement | null>(null)
const page = reactive({
  current: 1,
  pageSize: 10,
  total: 0,
})
const form = reactive<AdjustmentFormState>({
  operation: 'increment' as 'increment' | 'decrement',
  amount: '',
  cash_amount: '',
  gift_quota_amount: '',
  adjust_reason: '充值',
  admin_notes: '',
})

const activeUsers = computed(() => users.value.filter((item) => item.status === 'active').length)
const totalBalance = computed(() => users.value.reduce((sum, item) => sum + Number(item.balance || 0), 0))
const totalRecharge = computed(() =>
  users.value.reduce((sum, item) => sum + Number(item.total_recharged || 0), 0),
)
const selectedName = computed(() => selected.value?.username || selected.value?.email || '-')
const userCards = computed(() => [
  { label: '本页用户', value: page.total.toLocaleString('zh-CN'), icon: UserOutlined },
  { label: '活跃账户', value: activeUsers.value.toLocaleString('zh-CN'), icon: AuditOutlined },
  { label: '本页余额', value: moneyText(totalBalance.value), icon: WalletOutlined },
  { label: '本页累计充值', value: moneyText(totalRecharge.value), icon: CreditCardOutlined },
])

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
    if (selected.value) {
      const hit = res.items.find((item) => item.id === selected.value?.id)
      if (hit) selected.value = hit
    }
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
}

async function selectUser(row: Sub2User) {
  selected.value = row
  resetForm()
  loadHistory(row.id)
  await nextTick()
  adjustPanel.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

async function loadHistory(id: number) {
  historyLoading.value = true
  try {
    const res = await getSub2BalanceHistory(id, {
      page: 1,
      page_size: 8,
    })
    history.value = res.items
  } catch {
    history.value = []
    message.error('读取额度变动记录失败')
  } finally {
    historyLoading.value = false
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
    resetForm()
    loadUsers()
    loadHistory(selected.value.id)
  } catch (err) {
    const data = (err as { response?: { data?: AdjustmentRes } }).response?.data
    message.error(data?.message || '调额未确认成功')
  } finally {
    submitting.value = false
  }
}

function confirmAdjust() {
  if (!selected.value) return

  const op = form.operation === 'increment' ? '增加' : '扣减'
  const isCorrection = form.adjust_reason === '异常修正'
  const cash = isCorrection ? '0.00' : form.cash_amount || '0.00'
  const gift = isCorrection ? '0.00' : form.gift_quota_amount || '0.00'
  const after = nextBalanceText()
  Modal.confirm({
    title: '确认提交额度调整',
    content: isCorrection
      ? `将为 Sub2API 用户 #${selected.value.id} ${op}额度 ${form.amount || '0.00'}，预计调整后额度 ${after}。本次仅调整 Sub2API 额度，不纳入记账。`
      : `将为 Sub2API 用户 #${selected.value.id} ${op}额度 ${form.amount || '0.00'}，预计调整后额度 ${after}，入账 ${cash}，赠送 ${gift}。新系统不会直接显示成功，只有 Sub2API 真实入账并二次确认成功后，才会生成成功记录和财务账本。`,
    okText: '确认提交',
    cancelText: '再检查',
    onOk: submitAdjust,
  })
}

function moneyText(val: number | string) {
  return Number(val || 0).toFixed(2)
}

function absMoneyText(val: number | string) {
  return Math.abs(Number(val || 0)).toFixed(2)
}

function nextBalanceText() {
  const current = Number(selected.value?.balance || 0)
  const amount = Number(form.amount || 0)
  const signed = form.operation === 'decrement' ? -amount : amount

  return (current + signed).toFixed(2)
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

onMounted(loadUsers)
</script>

<template>
  <section class="page quotaPage">
    <div class="quotaHero">
      <div>
        <h1>Sub2API 额度管理</h1>
        <p>管理用户在 Sub2API 中的可用额度，手动充值或扣减。</p>
      </div>
      <a-button :loading="loading" @click="loadUsers">刷新用户</a-button>
    </div>

    <div class="quotaNotice">
      <strong>说明：</strong>
      API 额度是用户调用 Sub2API 接口的消费凭证。充值后用户可在额度范围内使用 API 服务，超出额度将被限制。所有变动均记录在审计日志中。
    </div>

    <div class="quotaMetricGrid">
      <section v-for="item in userCards" :key="item.label" class="quotaMetric">
        <component :is="item.icon" />
        <span>{{ item.label }}</span>
        <strong>{{ item.value }}</strong>
      </section>
    </div>

    <div class="quotaWorkGrid">
      <section class="panel quotaUserPanel">
        <div class="panelHead">
          <h2>选择用户</h2>
          <span class="panelMeta">点击后进入调额区</span>
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
          <a-empty v-if="users.length === 0" description="暂无可调额用户数据" />
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
          <p>选择后可查看其 API 额度详情并执行充值或扣减操作。</p>
        </div>

        <template v-else>
          <section class="panel quotaSelected">
            <div class="quotaSelectedUser">
              <span class="quotaAvatar large">{{ selectedName.slice(0, 1).toUpperCase() }}</span>
              <div>
                <h2>{{ selectedName }}</h2>
                <p>{{ selected.email }} · ID: {{ selected.id }}</p>
                <div class="quotaInlineStats">
                  <span>API 可用余额 <strong class="money">{{ moneyText(selected.balance) }}</strong></span>
                  <span>累计充入 <strong>{{ moneyText(selected.total_recharged) }}</strong></span>
                </div>
              </div>
            </div>
          </section>

          <section class="panel quotaAdjustPanel">
            <div class="panelHead">
              <h2>额度调整</h2>
              <span class="panelMeta">立即提交至 Sub2API 二次确认</span>
            </div>
            <AdjustmentForm v-model:value="form" :current-balance="selected.balance" />
            <div class="quotaFormActions">
              <a-button @click="resetForm">重置</a-button>
              <a-button type="primary" :loading="submitting" @click="confirmAdjust">确认调整</a-button>
            </div>
            <p class="quotaWarn">
              * 额度调整只有在 Sub2API 真实入账并二次确认成功后，才会生成成功记录和财务账本。
            </p>
          </section>

          <section class="panel quotaHistoryPanel">
            <div class="panelHead">
              <h2>额度变动记录</h2>
              <span class="panelMeta">同步 Sub2API 最近 8 条</span>
            </div>
            <a-spin :spinning="historyLoading">
              <a-empty v-if="history.length === 0" description="暂无额度变动记录" />
              <div v-else class="quotaHistoryList">
                <button v-for="item in history" :key="item.id" type="button" class="quotaHistoryItem" @click="openHistory(item)">
                  <div class="historyIcon" :class="item.operation">
                    <PlusCircleOutlined v-if="item.operation === 'increment'" />
                    <MinusCircleOutlined v-else />
                  </div>
                  <div class="historyInfo">
                    <strong>调整人员：{{ item.operator_name || 'Sub2API' }}</strong>
                    <span>调整账号：{{ item.adjusted_account || selected.email }}</span>
                    <span>调整前：{{ item.before_balance ?? '-' }} · 调整后：{{ item.after_balance ?? '-' }}</span>
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
      title="额度变动详情"
      :footer="null"
      @cancel="selectedHistory = null"
    >
      <div v-if="selectedHistory" class="historyDetail">
        <p><span>调整人员</span><strong>{{ selectedHistory.operator_name || 'Sub2API' }}</strong></p>
        <p><span>调整账号</span><strong>{{ selectedHistory.adjusted_account }}</strong></p>
        <p><span>调整金额</span><strong>{{ opSign(selectedHistory) }} {{ absMoneyText(selectedHistory.value) }}</strong></p>
        <p><span>调整前</span><strong>{{ selectedHistory.before_balance ?? '-' }}</strong></p>
        <p><span>调整后</span><strong>{{ selectedHistory.after_balance ?? '-' }}</strong></p>
        <p><span>时间</span><strong>{{ timeText(selectedHistory) }}</strong></p>
        <p><span>业务单号</span><strong>{{ selectedHistory.ledger_no || '-' }}</strong></p>
        <p><span>原因</span><strong>{{ selectedHistory.adjust_reason || selectedHistory.type || '-' }}</strong></p>
        <div v-if="selectedHistory.admin_notes" class="historyDetailNotes">
          <span>备注</span>
          <div class="safeHtml" v-html="selectedHistory.admin_notes"></div>
        </div>
        <div v-else-if="selectedHistory.notes" class="historyDetailNotes">
          <span>Sub2API 备注</span>
          <pre>{{ selectedHistory.notes }}</pre>
        </div>
      </div>
    </a-modal>
  </section>
</template>
