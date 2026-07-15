<script setup lang="ts">
import {
  CheckCircleOutlined,
  CopyOutlined,
  LinkOutlined,
  ReloadOutlined,
  TeamOutlined,
} from '@ant-design/icons-vue'
import { message } from 'ant-design-vue'
import { onMounted, ref } from 'vue'
import { getAffiliatePromotion } from '../../api/affiliate'
import AsyncState from '../../components/AsyncState.vue'
import PageHeader from '../../components/PageHeader.vue'
import type { PromotionRes } from '../../types'

const loading = ref(false)
const error = ref('')
const data = ref<PromotionRes | null>(null)

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
    <PageHeader title="推广中心" description="直接邀请与充值转化">
      <template #actions>
        <a-button :loading="loading" @click="load">
          <template #icon><ReloadOutlined /></template>
          刷新
        </a-button>
      </template>
    </PageHeader>

    <AsyncState :loading="loading" :error="error" @retry="load">
      <template v-if="data">
        <div class="promotionOverview">
          <section class="rebateSection promotionEngine">
            <div class="promotionEngineHead">
              <span class="promotionEngineIcon"><LinkOutlined /></span>
              <div>
                <h2>专属邀请链接</h2>
                <p>邀请码 {{ data.invite_code || '--' }}</p>
              </div>
            </div>
            <div class="promotionLinkBox">
              <span>{{ data.invite_url }}</span>
              <a-button type="primary" aria-label="复制邀请链接" @click="copy(data.invite_url, '邀请链接')">
                <template #icon><CopyOutlined /></template>
                复制链接
              </a-button>
            </div>
            <button class="promotionCode" type="button" @click="copy(data.invite_code, '邀请码')">
              <span>邀请码</span>
              <strong>{{ data.invite_code || '--' }}</strong>
              <CopyOutlined />
            </button>
          </section>

          <div class="promotionStats">
            <article>
              <TeamOutlined />
              <span>直接邀请</span>
              <strong>{{ data.direct_count }}</strong>
            </article>
            <article>
              <CheckCircleOutlined />
              <span>已充值下级</span>
              <strong>{{ data.converted_count }}</strong>
            </article>
            <article>
              <span class="promotionRateIcon">%</span>
              <span>充值转化率</span>
              <strong>{{ data.conversion_rate }}%</strong>
            </article>
            <article>
              <span class="promotionRateIcon">¥</span>
              <span>下级累计充值</span>
              <strong>{{ money(data.total_direct_recharge_amount) }}</strong>
            </article>
          </div>
        </div>

        <section class="rebateSection promotionLevelCard">
          <div>
            <span class="promotionLevelBadge">L1</span>
            <div>
              <h2>一级直接推广</h2>
              <p>关系层级 L1</p>
            </div>
          </div>
          <div class="promotionLevelStats">
            <span>直接下级 <strong>{{ data.direct_count }}</strong></span>
            <span>已转化 <strong>{{ data.converted_count }}</strong></span>
          </div>
        </section>

        <section class="rebateSection">
          <div class="rebateSectionHeader"><h2>最近邀请</h2><span class="rebateMuted">共 {{ data.direct_count }} 人</span></div>
          <AsyncState :empty="data.items.length === 0" empty-text="暂无邀请记录">
            <div class="rebateTable">
              <a-table row-key="user_id" size="middle" :data-source="data.items" :pagination="false" :scroll="{ x: 720 }">
                <a-table-column title="用户" key="user" :width="260">
                  <template #default="{ record }">
                    <div class="rebateUserCell">
                      <strong>{{ record.email || record.username || `用户 #${record.user_id}` }}</strong>
                      <span>{{ record.username || `ID ${record.user_id}` }}</span>
                    </div>
                  </template>
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
