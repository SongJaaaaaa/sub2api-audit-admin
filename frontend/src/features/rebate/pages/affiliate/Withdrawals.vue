<script setup lang="ts">
import { ReloadOutlined, WalletOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { createAffiliateWithdrawal, getAffiliateWithdrawals } from '../../api/affiliate'
import AsyncState from '../../components/AsyncState.vue'
import MetricGrid, { type MetricItem } from '../../components/MetricGrid.vue'
import PageHeader from '../../components/PageHeader.vue'
import StatusTag from '../../components/StatusTag.vue'
import type { AffiliateWithdrawalsRes, RebateWithdrawal } from '../../types'

const loading = ref(false)
const creating = ref(false)
const error = ref('')
const data = ref<AffiliateWithdrawalsRes | null>(null)
const amount = ref('')
const items = ref<RebateWithdrawal[]>([])
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const expectedQuota = computed(() => {
  const value = Number(amount.value || 0) * Number(data.value?.config.to_api_quota_rate || 0)
  return money(value.toFixed(2))
})

const metrics = computed<MetricItem[]>(() => data.value ? [
  { label: '可用返利', value: money(data.value.balance.available_amount), tone: 'green' },
  { label: '冻结返利', value: money(data.value.balance.frozen_amount), tone: 'orange' },
  { label: '累计转入额度', value: money(data.value.balance.withdrawn_amount), tone: 'blue' },
  { label: '今日已申请', value: `${data.value.today_count} 笔 / ${money(data.value.today_amount)}`, tone: 'blue' },
] : [])

function money(value: string) {
  return `¥${Number(value || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function apiMessage(err: unknown, fallback: string) {
  return (err as { response?: { data?: { message?: string } } }).response?.data?.message || fallback
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await getAffiliateWithdrawals({ page: page.current, page_size: page.pageSize })
    data.value = res
    items.value = res.items
    page.total = res.total
  } catch (err) {
    data.value = null
    items.value = []
    error.value = apiMessage(err, '读取提现信息失败')
  } finally {
    loading.value = false
  }
}

async function submit() {
  if (!data.value) return
  const value = Number(amount.value)
  const min = Number(data.value.config.min_amount)
  const available = Number(data.value.balance.available_amount)
  if (!Number.isFinite(value) || value <= 0) return void message.warning('请输入正确的提现金额')
  if (value < min) return void message.warning(`单次最低提现 ${money(data.value.config.min_amount)}`)
  if (value > available) return void message.warning('可用返利余额不足')

  creating.value = true
  try {
    const res = await createAffiliateWithdrawal(amount.value)
    message.success(res.message || '提现申请已提交')
    amount.value = ''
    page.current = 1
    await load()
  } catch (err) {
    message.error(apiMessage(err, '提交提现申请失败'))
  } finally {
    creating.value = false
  }
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
    <PageHeader title="提现管理">
      <template #actions>
        <a-button :loading="loading" @click="load"><template #icon><ReloadOutlined /></template>刷新</a-button>
      </template>
    </PageHeader>

    <AsyncState :loading="loading && !data" :error="error" @retry="load">
      <template v-if="data">
        <MetricGrid :items="metrics" />

        <section class="rebateSection">
          <div class="rebateSectionHeader"><h2>申请转入 Sub2API 额度</h2></div>
          <a-form layout="vertical" @finish="submit">
            <div class="rebateFormGrid">
              <a-form-item label="提现金额" required>
                <a-input v-model:value="amount" inputmode="decimal" placeholder="0.00" addon-after="元" />
              </a-form-item>
              <a-form-item label="预计到账额度">
                <a-input :value="expectedQuota" readonly />
              </a-form-item>
            </div>
            <a-descriptions bordered :column="1" size="small">
              <a-descriptions-item label="单次最低金额">{{ money(data.config.min_amount) }}</a-descriptions-item>
              <a-descriptions-item label="每日申请次数">{{ data.config.daily_limit === 0 ? '不限' : `${data.config.daily_limit} 次` }}</a-descriptions-item>
              <a-descriptions-item label="每日申请总金额">{{ Number(data.config.daily_amount_limit) === 0 ? '不限' : money(data.config.daily_amount_limit) }}</a-descriptions-item>
              <a-descriptions-item label="换算比例">1 : {{ data.config.to_api_quota_rate }}</a-descriptions-item>
            </a-descriptions>
            <div class="withdrawSubmit">
              <a-button type="primary" html-type="submit" :loading="creating" :disabled="!amount">
                <template #icon><WalletOutlined /></template>
                提交申请
              </a-button>
            </div>
          </a-form>
        </section>

        <section class="rebateSection">
          <div class="rebateSectionHeader">
            <h2>提现记录</h2>
            <span class="rebateMuted">共 {{ page.total }} 条</span>
          </div>
          <AsyncState :empty="items.length === 0" empty-text="暂无提现记录">
            <div class="rebateTable">
              <a-table
                row-key="id"
                size="middle"
                :data-source="items"
                :pagination="{ current: page.current, pageSize: page.pageSize, total: page.total, showSizeChanger: true }"
                :scroll="{ x: 1020 }"
                @change="tableChange"
              >
                <a-table-column title="申请时间" data-index="created_at" :width="175" />
                <a-table-column title="申请单号" data-index="request_no" :width="220" />
                <a-table-column title="提现金额" key="amount" align="right" :width="130">
                  <template #default="{ record }"><span class="rebateAmount">{{ money(record.amount) }}</span></template>
                </a-table-column>
                <a-table-column title="到账额度" key="quota" align="right" :width="130">
                  <template #default="{ record }">{{ money(record.quota_amount) }}</template>
                </a-table-column>
                <a-table-column title="状态" key="status" :width="105">
                  <template #default="{ record }"><StatusTag :status="record.status" /></template>
                </a-table-column>
                <a-table-column title="结果" key="result" :width="240">
                  <template #default="{ record }"><span class="rebateWrapCell">{{ record.reject_reason || record.error_message || '--' }}</span></template>
                </a-table-column>
              </a-table>
            </div>
          </AsyncState>
        </section>
      </template>
    </AsyncState>
  </div>
</template>

<style scoped>
.withdrawSubmit {
  display: flex;
  margin-top: 16px;
  justify-content: flex-end;
}

@media (max-width: 760px) {
  .withdrawSubmit .ant-btn { width: 100%; }
}
</style>
