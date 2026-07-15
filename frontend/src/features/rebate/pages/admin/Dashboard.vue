<script setup lang="ts">
import {
  ApartmentOutlined,
  CalendarOutlined,
  ClockCircleOutlined,
  GiftOutlined,
  LockOutlined,
  ReloadOutlined,
  TeamOutlined,
  WalletOutlined,
} from '@ant-design/icons-vue'
import { computed, onMounted, ref } from 'vue'
import { getAdminDashboard } from '../../api/admin'
import AsyncState from '../../components/AsyncState.vue'
import StatusTag from '../../components/StatusTag.vue'
import type { AdminDashboard } from '../../types'

const loading = ref(false)
const error = ref('')
const data = ref<AdminDashboard | null>(null)

const metrics = computed(() => {
  if (!data.value) return []
  return [
    {
      label: '返利用户',
      value: data.value.total_users.toLocaleString('zh-CN'),
      hint: '已进入推广返利体系',
      icon: TeamOutlined,
      tone: 'indigo',
    },
    {
      label: '一级推荐关系',
      value: data.value.direct_referral_count.toLocaleString('zh-CN'),
      hint: '仅统计直接推荐',
      icon: ApartmentOutlined,
      tone: 'green',
    },
    {
      label: '累计发放返利',
      value: money(data.value.total_rebate_amount),
      hint: `今日 ${money(data.value.today_rebate_amount)}`,
      icon: GiftOutlined,
      tone: 'indigo',
    },
    {
      label: '待审核提现',
      value: `${data.value.pending_withdrawal_count} 笔`,
      hint: `待处理 ${money(data.value.pending_withdrawal_amount)}`,
      icon: ClockCircleOutlined,
      tone: 'red',
    },
    {
      label: '可用返利余额',
      value: money(data.value.available_rebate_amount),
      hint: '用户当前可申请提现',
      icon: WalletOutlined,
      tone: 'green',
    },
    {
      label: '冻结返利余额',
      value: money(data.value.frozen_rebate_amount),
      hint: '提现处理中的资金',
      icon: LockOutlined,
      tone: 'orange',
    },
    {
      label: '累计转入额度',
      value: money(data.value.withdrawn_amount),
      hint: '已成功转入 Sub2API',
      icon: WalletOutlined,
      tone: 'green',
    },
    {
      label: '本月返利',
      value: money(data.value.month_rebate_amount),
      hint: '本自然月累计发放',
      icon: CalendarOutlined,
      tone: 'indigo',
    },
  ]
})

function money(value: string) {
  return `¥${Number(value || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function apiMessage(err: unknown) {
  return (err as { response?: { data?: { message?: string } } }).response?.data?.message || '读取返利看板失败'
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await getAdminDashboard()
  } catch (err) {
    data.value = null
    error.value = apiMessage(err)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="rebatePage adminDashboard">
    <header class="adminPageHead">
      <div>
        <span class="pageEyebrow">推广返利</span>
        <h1>数据看板</h1>
        <p>一级推广返利的用户、发放和提现概况</p>
      </div>
      <a-button :loading="loading" @click="load">
        <template #icon><ReloadOutlined /></template>
        刷新数据
      </a-button>
    </header>

    <AsyncState :loading="loading && !data" :error="error" @retry="load">
      <template v-if="data">
        <div class="dashboardMetrics">
          <article v-for="item in metrics" :key="item.label" class="metricCard" :data-tone="item.tone">
            <div class="metricTopline">
              <span class="metricIcon"><component :is="item.icon" /></span>
          <span class="metricState">当前快照</span>
            </div>
            <span class="metricLabel">{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
            <span class="metricHint">{{ item.hint }}</span>
          </article>
        </div>

        <section class="adminPanel">
          <div class="panelHeader">
            <div>
              <h2>最近返利</h2>
              <p>最新一级推广奖励发放记录</p>
            </div>
            <span class="panelMeta"><i />今日 {{ money(data.today_rebate_amount) }}</span>
          </div>
          <div class="rebateTable">
            <a-table
              row-key="id"
              size="small"
              :data-source="data.recent_rebates"
              :pagination="false"
              :scroll="{ x: 850 }"
            >
              <a-table-column title="时间" data-index="created_at" key="created_at" :width="175" />
              <a-table-column title="充值用户" key="payer" :width="230">
                <template #default="{ record }">
                  <div class="rebateUserCell">
                    <strong>{{ record.payer_email || `用户 #${record.payer_user_id}` }}</strong>
                    <span>ID {{ record.payer_user_id }}</span>
                  </div>
                </template>
              </a-table-column>
              <a-table-column title="返利类型" key="type" :width="120">
                <template #default="{ record }">{{ record.type === 'milestone' ? '初始里程碑' : '后续台阶' }}</template>
              </a-table-column>
              <a-table-column title="充值金额" key="source_amount" align="right" :width="130">
                <template #default="{ record }">{{ money(record.source_amount) }}</template>
              </a-table-column>
              <a-table-column title="返利金额" key="rebate_amount" align="right" :width="130">
                <template #default="{ record }"><span class="rebateAmount">{{ money(record.rebate_amount) }}</span></template>
              </a-table-column>
            </a-table>
          </div>
        </section>

        <section class="adminPanel">
          <div class="panelHeader">
            <div>
              <h2>最近提现</h2>
              <p>推广用户最新提现申请与处理状态</p>
            </div>
            <span class="panelMeta pending"><i />待审核 {{ money(data.pending_withdrawal_amount) }}</span>
          </div>
          <div class="rebateTable">
            <a-table
              row-key="id"
              size="small"
              :data-source="data.recent_withdrawals"
              :pagination="false"
              :scroll="{ x: 800 }"
            >
              <a-table-column title="申请时间" data-index="created_at" :width="175" />
              <a-table-column title="申请单号" data-index="request_no" :width="210" />
              <a-table-column title="用户" key="user" :width="230">
                <template #default="{ record }">{{ record.user_email || `用户 #${record.user_id}` }}</template>
              </a-table-column>
              <a-table-column title="金额" key="amount" align="right" :width="130">
                <template #default="{ record }"><span class="rebateAmount">{{ money(record.amount) }}</span></template>
              </a-table-column>
              <a-table-column title="状态" key="status" :width="100">
                <template #default="{ record }"><StatusTag :status="record.status" /></template>
              </a-table-column>
            </a-table>
          </div>
        </section>
      </template>
    </AsyncState>
  </div>
</template>

<style scoped>
.adminDashboard {
  gap: 24px;
}

.adminPageHead {
  display: flex;
  min-width: 0;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
}

.pageEyebrow {
  display: block;
  margin-bottom: 4px;
  color: #4648d4;
  font-size: 12px;
  font-weight: 700;
  line-height: 18px;
}

.adminPageHead h1 {
  margin: 0;
  color: var(--heading);
  font-size: 28px;
  line-height: 38px;
  letter-spacing: 0;
}

.adminPageHead p,
.panelHeader p {
  margin: 3px 0 0;
  color: var(--muted);
  font-size: 13px;
  line-height: 20px;
}

.dashboardMetrics {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
}

.metricCard {
  display: flex;
  min-width: 0;
  min-height: 176px;
  padding: 22px;
  flex-direction: column;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  box-shadow: 0 1px 3px rgb(15 23 42 / 5%);
}

.metricTopline {
  display: flex;
  margin-bottom: 18px;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.metricIcon {
  display: inline-flex;
  width: 42px;
  height: 42px;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  background: #eef0ff;
  color: #4648d4;
  font-size: 21px;
}

.metricState {
  color: #10b981;
  font-size: 11px;
  font-weight: 700;
}

.metricCard[data-tone='green'] .metricIcon {
  background: #e7f8f1;
  color: #059669;
}

.metricCard[data-tone='orange'] .metricIcon {
  background: #fff5df;
  color: #d97706;
}

.metricCard[data-tone='red'] .metricIcon {
  background: #fff0ef;
  color: #dc2626;
}

.metricCard[data-tone='red'] .metricState {
  color: #dc2626;
}

.metricLabel {
  overflow: hidden;
  color: var(--muted);
  font-size: 12px;
  font-weight: 700;
  line-height: 18px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.metricCard strong {
  margin-top: 4px;
  overflow-wrap: anywhere;
  color: var(--heading);
  font-size: 30px;
  font-variant-numeric: tabular-nums;
  line-height: 40px;
  letter-spacing: 0;
}

.metricHint {
  margin-top: auto;
  color: var(--muted);
  font-size: 12px;
  line-height: 18px;
}

.adminPanel {
  min-width: 0;
  padding: 0 22px 18px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  box-shadow: 0 1px 3px rgb(15 23 42 / 4%);
}

.panelHeader {
  display: flex;
  min-height: 76px;
  padding: 16px 0;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid var(--border);
}

.panelHeader h2 {
  margin: 0;
  color: var(--heading);
  font-size: 17px;
  line-height: 25px;
  letter-spacing: 0;
}

.panelMeta {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 7px;
  color: #059669;
  font-size: 12px;
  font-weight: 600;
}

.panelMeta i {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
}

.panelMeta.pending {
  color: #dc2626;
}

.panelMeta.pending i {
  background: #ef4444;
}

:deep(.ant-table-wrapper .ant-table) {
  background: transparent;
}

:deep(.ant-table-wrapper .ant-table-thead > tr > th) {
  padding: 11px 14px;
  background: #f2f4f6;
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
}

:deep(.ant-table-wrapper .ant-table-tbody > tr > td) {
  padding: 12px 14px;
  color: var(--text);
  font-size: 13px;
}

:deep(.ant-tag) {
  margin-inline-end: 0;
  border: 0;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
}

@media (max-width: 1180px) {
  .dashboardMetrics {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 760px) {
  .adminDashboard {
    gap: 16px;
  }

  .adminPageHead,
  .panelHeader {
    align-items: stretch;
    flex-direction: column;
  }

  .adminPageHead h1 {
    font-size: 24px;
    line-height: 34px;
  }

  .adminPageHead .ant-btn {
    width: 100%;
  }

  .dashboardMetrics {
    grid-template-columns: 1fr;
    gap: 12px;
  }

  .metricCard {
    min-height: 158px;
    padding: 18px;
  }

  .adminPanel {
    padding: 0 12px 12px;
    border-radius: 8px;
  }

  .panelHeader {
    min-height: auto;
    padding: 14px 0;
  }
}
</style>
