<script setup lang="ts">
import {
  MinusCircleOutlined,
  PlusCircleOutlined,
  WalletOutlined,
} from '@ant-design/icons-vue'
import { message } from 'ant-design-vue'
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useImagePreview } from '../composables/useImagePreview'
import {
  createLedgerAdjustment,
  type AdjustmentRes,
} from '../api/ledger'
import { getSub2BalanceHistory, getSub2Users, type Sub2BalanceHistoryItem, type Sub2User } from '../api/sub2api'
import AdjustmentForm, { type AdjustmentFormState } from '../components/ledger/AdjustmentForm.vue'

const loading = ref(false)
const historyLoading = ref(false)
const submitting = ref(false)
const formKey = ref(0)
const users = ref<Sub2User[]>([])
const history = ref<Sub2BalanceHistoryItem[]>([])
const selected = ref<Sub2User | null>(null)
const selectedHistory = ref<Sub2BalanceHistoryItem | null>(null)
const keyword = ref('')
const adjustPanel = ref<HTMLElement | null>(null)
const { previewSrc, previewOpen, onSafeHtmlClick } = useImagePreview()
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

const selectedName = computed(() => selected.value?.username || selected.value?.email || '-')
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
  formKey.value += 1
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
    message.error('读取充值记录失败')
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
    message.error(data?.message || '充值未确认成功')
  } finally {
    submitting.value = false
  }
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

onMounted(loadUsers)
</script>

<template>
  <section class="page quotaPage">
    <div class="quotaHero quotaHeroActionsOnly">
      <a-button :loading="loading" @click="loadUsers">刷新用户</a-button>
    </div>

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
                <div class="quotaInlineStats">
                  <span>API 可用余额 <strong class="money">{{ moneyText(selected.balance) }}</strong></span>
                  <span>Sub2API 累计充值字段 <strong>{{ moneyText(selected.total_recharged) }}</strong></span>
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

    <!-- 图片放大预览 -->
    <a-modal v-model:open="previewOpen" :footer="null" centered :body-style="{ textAlign: 'center', padding: '8px' }">
      <img :src="previewSrc" style="max-width:100%;max-height:80vh;border-radius:6px;" />
    </a-modal>

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
          <div class="safeHtml" v-html="selectedHistory.admin_notes" @click="onSafeHtmlClick"></div>
        </div>
        <div v-else-if="selectedHistory.notes" class="historyDetailNotes">
          <span>Sub2API 备注</span>
          <pre>{{ selectedHistory.notes }}</pre>
        </div>
      </div>
    </a-modal>
  </section>
</template>
