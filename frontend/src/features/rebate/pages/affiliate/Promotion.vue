<script setup lang="ts">
import { CopyOutlined, ReloadOutlined } from '@ant-design/icons-vue'
import { message } from 'ant-design-vue'
import { computed, onMounted, ref } from 'vue'
import { getAffiliatePromotion } from '../../api/affiliate'
import AsyncState from '../../components/AsyncState.vue'
import MetricGrid, { type MetricItem } from '../../components/MetricGrid.vue'
import PageHeader from '../../components/PageHeader.vue'
import type { PromotionRes } from '../../types'

const loading = ref(false)
const error = ref('')
const data = ref<PromotionRes | null>(null)

const metrics = computed<MetricItem[]>(() => data.value ? [
  { label: '直接邀请', value: data.value.direct_count, tone: 'blue' },
  { label: '已充值下级', value: data.value.converted_count, tone: 'green' },
  { label: '充值转化率', value: `${data.value.conversion_rate}%`, tone: 'orange' },
  { label: '下级累计充值', value: money(data.value.total_direct_recharge_amount), tone: 'green' },
] : [])

function money(value: string) {
  return `¥${Number(value || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await getAffiliatePromotion()
  } catch (err) {
    data.value = null
    error.value = (err as { response?: { data?: { message?: string } } }).response?.data?.message || '读取推广数据失败'
  } finally {
    loading.value = false
  }
}

async function copy(value: string, label: string) {
  try {
    await navigator.clipboard.writeText(value)
    message.success(`${label}已复制`)
  } catch {
    message.error('复制失败')
  }
}

onMounted(load)
</script>

<template>
  <div class="rebatePage">
    <PageHeader title="推广中心">
      <template #actions>
        <a-button :loading="loading" @click="load">
          <template #icon><ReloadOutlined /></template>
          刷新
        </a-button>
      </template>
    </PageHeader>

    <AsyncState :loading="loading" :error="error" @retry="load">
      <template v-if="data">
        <section class="rebateSection">
          <div class="rebateSectionHeader"><h2>邀请信息</h2></div>
          <a-descriptions bordered :column="1" size="middle">
            <a-descriptions-item label="邀请码">
              <div class="rebateInlineValue">
                <a-input :value="data.invite_code" readonly />
                <a-button aria-label="复制邀请码" @click="copy(data.invite_code, '邀请码')"><CopyOutlined /></a-button>
              </div>
            </a-descriptions-item>
            <a-descriptions-item label="邀请链接">
              <div class="rebateInlineValue">
                <a-input :value="data.invite_url" readonly />
                <a-button aria-label="复制邀请链接" @click="copy(data.invite_url, '邀请链接')"><CopyOutlined /></a-button>
              </div>
            </a-descriptions-item>
          </a-descriptions>
        </section>

        <MetricGrid :items="metrics" />

        <section class="rebateSection">
          <div class="rebateSectionHeader"><h2>最近邀请</h2></div>
          <AsyncState :empty="data.items.length === 0" empty-text="暂无邀请记录">
            <div class="rebateTable">
              <a-table row-key="user_id" size="middle" :data-source="data.items" :pagination="false" :scroll="{ x: 720 }">
                <a-table-column title="用户" key="user" :width="260">
                  <template #default="{ record }">{{ record.email || record.username || `用户 #${record.user_id}` }}</template>
                </a-table-column>
                <a-table-column title="累计充值" key="recharge" align="right" :width="140">
                  <template #default="{ record }">{{ money(record.total_recharge_amount) }}</template>
                </a-table-column>
                <a-table-column title="产生返利" key="rebate" align="right" :width="140">
                  <template #default="{ record }"><span class="rebateAmount">{{ money(record.total_rebate_amount) }}</span></template>
                </a-table-column>
                <a-table-column title="加入时间" data-index="joined_at" :width="175" />
              </a-table>
            </div>
          </AsyncState>
        </section>
      </template>
    </AsyncState>
  </div>
</template>
