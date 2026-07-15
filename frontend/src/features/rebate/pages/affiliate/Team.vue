<script setup lang="ts">
import { ReloadOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { getAffiliateTeam } from '../../api/affiliate'
import AsyncState from '../../components/AsyncState.vue'
import PageHeader from '../../components/PageHeader.vue'
import { useAffiliateAuthStore } from '../../stores/affiliateAuth'
import type { TeamMember } from '../../types'

const auth = useAffiliateAuthStore()
const loading = ref(false)
const error = ref('')
const items = ref<TeamMember[]>([])
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const rootName = computed(() => auth.user?.username || auth.user?.email || '当前用户')
const rootMeta = computed(() => {
  if (auth.user?.username && auth.user.email) return auth.user.email
  return auth.user?.id ? `用户 ID ${auth.user.id}` : '推广账号'
})

function money(value: string) {
  return `¥${Number(value || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function memberName(member: TeamMember) {
  return member.username || member.email || `用户 #${member.user_id}`
}

function memberMark(member: TeamMember) {
  return memberName(member).slice(0, 1).toUpperCase()
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await getAffiliateTeam({ page: page.current, page_size: page.pageSize })
    items.value = res.items
    page.total = res.total
    page.current = res.page
    page.pageSize = res.page_size
  } catch (err) {
    items.value = []
    error.value = (err as { response?: { data?: { message?: string } } }).response?.data?.message || '读取团队失败'
  } finally {
    loading.value = false
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
    <PageHeader title="我的推荐关系" description="当前账号与一级直接下级">
      <template #actions>
        <a-button :loading="loading" @click="load">
          <template #icon><ReloadOutlined /></template>
          刷新
        </a-button>
      </template>
    </PageHeader>

    <AsyncState :loading="loading" :error="error" @retry="load">
      <section class="rebateSection teamRelationSection">
        <div class="rebateSectionHeader">
          <h2>一级推荐关系</h2>
          <span class="rebateMuted">当前页 {{ items.length }} 人 / 共 {{ page.total }} 人</span>
        </div>

        <div class="teamCanvas">
          <div class="teamTree">
            <article class="teamRoot">
              <span class="teamRootMark">我</span>
              <div class="teamNodeCopy">
                <strong>{{ rootName }}</strong>
                <span>{{ rootMeta }}</span>
              </div>
              <a-tag color="blue">直属 {{ page.total }} 人</a-tag>
            </article>

            <template v-if="items.length">
              <div class="teamRootStem" />
              <div class="teamBranch" :class="{ single: items.length === 1 }">
                <article v-for="member in items" :key="member.user_id" class="teamMember">
                  <span class="teamMemberMark">{{ memberMark(member) }}</span>
                  <div class="teamNodeCopy">
                    <strong :title="memberName(member)">{{ memberName(member) }}</strong>
                    <span :title="member.email">{{ member.email }}</span>
                  </div>
                  <dl>
                    <div>
                      <dt>累计充值</dt>
                      <dd>{{ money(member.total_recharge_amount) }}</dd>
                    </div>
                    <div>
                      <dt>产生返利</dt>
                      <dd class="rebateAmount">{{ money(member.total_rebate_amount) }}</dd>
                    </div>
                  </dl>
                  <a-tag class="teamLevelTag" color="blue">一级下级</a-tag>
                </article>
              </div>
            </template>

            <div v-else class="teamEmpty">暂无一级直接下级</div>
          </div>
        </div>
      </section>

      <section class="rebateSection teamTableSection">
        <div class="rebateSectionHeader">
          <h2>一级成员明细</h2>
          <span class="rebateMuted">共 {{ page.total }} 人</span>
        </div>
        <AsyncState :empty="items.length === 0" empty-text="暂无直接下级">
          <div class="rebateTable">
            <a-table
              row-key="user_id"
              size="small"
              :data-source="items"
              :pagination="{ current: page.current, pageSize: page.pageSize, total: page.total, showSizeChanger: true }"
              :scroll="{ x: 920 }"
              @change="tableChange"
            >
              <a-table-column title="用户" key="user" :width="280">
                <template #default="{ record }">
                  <div class="rebateUserCell">
                    <strong>{{ record.email }}</strong>
                    <span>{{ record.username || `用户 #${record.user_id}` }}</span>
                  </div>
                </template>
              </a-table-column>
              <a-table-column title="关系" :width="100">
                <template #default><a-tag color="blue">一级</a-tag></template>
              </a-table-column>
              <a-table-column title="累计充值" key="recharge" align="right" :width="140">
                <template #default="{ record }">{{ money(record.total_recharge_amount) }}</template>
              </a-table-column>
              <a-table-column title="产生返利" key="rebate" align="right" :width="140">
                <template #default="{ record }"><span class="rebateAmount">{{ money(record.total_rebate_amount) }}</span></template>
              </a-table-column>
              <a-table-column title="里程碑次数" data-index="milestone_times" align="right" :width="120" />
              <a-table-column title="加入时间" key="joined_at" :width="175">
                <template #default="{ record }">{{ record.joined_at || '--' }}</template>
              </a-table-column>
            </a-table>
          </div>
        </AsyncState>
      </section>
    </AsyncState>
  </div>
</template>

<style scoped>
.teamRelationSection,
.teamTableSection {
  overflow: hidden;
}

.teamCanvas {
  min-height: 320px;
  overflow-x: auto;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface2);
}

.teamTree {
  display: flex;
  width: max-content;
  min-width: 100%;
  min-height: 318px;
  padding: 30px;
  align-items: center;
  flex-direction: column;
  justify-content: center;
}

.teamRoot,
.teamMember {
  position: relative;
  display: flex;
  width: 240px;
  min-width: 0;
  align-items: center;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.07);
}

.teamRoot {
  width: min(360px, calc(100vw - 96px));
  padding: 14px 16px;
  border: 2px solid var(--affiliate-accent, #4648d4);
  gap: 12px;
}

.teamRootMark,
.teamMemberMark {
  display: inline-flex;
  width: 40px;
  height: 40px;
  flex: 0 0 40px;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--affiliate-accent, #4648d4);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
}

.teamMemberMark {
  width: 34px;
  height: 34px;
  flex-basis: 34px;
  background: #eef2ff;
  color: var(--affiliate-accent, #4648d4);
}

.teamNodeCopy {
  display: flex;
  min-width: 0;
  flex: 1;
  flex-direction: column;
  gap: 2px;
}

.teamNodeCopy strong,
.teamNodeCopy span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.teamNodeCopy strong {
  color: var(--heading);
  font-size: 14px;
}

.teamNodeCopy span {
  color: var(--muted);
  font-size: 12px;
}

.teamRootStem {
  width: 1px;
  height: 30px;
  background: var(--border);
}

.teamBranch {
  position: relative;
  display: flex;
  padding-top: 30px;
  gap: 16px;
}

.teamBranch::before {
  position: absolute;
  top: 0;
  right: 120px;
  left: 120px;
  height: 1px;
  background: var(--border);
  content: '';
}

.teamBranch.single::before {
  right: 50%;
  left: 50%;
}

.teamMember {
  padding: 14px;
  align-items: flex-start;
  flex-wrap: wrap;
  gap: 10px;
}

.teamMember::before {
  position: absolute;
  top: -30px;
  left: 50%;
  width: 1px;
  height: 30px;
  background: var(--border);
  content: '';
}

.teamMember dl {
  display: grid;
  width: 100%;
  margin: 4px 0 0;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.teamMember dl div {
  min-width: 0;
  padding-top: 9px;
  border-top: 1px solid var(--border);
}

.teamMember dt {
  color: var(--muted);
  font-size: 11px;
}

.teamMember dd {
  margin: 3px 0 0;
  overflow-wrap: anywhere;
  color: var(--heading);
  font-size: 13px;
  font-weight: 600;
}

.teamMember dd.rebateAmount {
  color: var(--money);
}

.teamLevelTag {
  margin-top: 2px;
}

.teamEmpty {
  display: grid;
  min-height: 110px;
  place-items: center;
  color: var(--muted);
  font-size: 13px;
}

.teamTableSection :deep(.ant-table-thead > tr > th) {
  padding-top: 10px;
  padding-bottom: 10px;
  background: var(--surface2);
  color: var(--muted);
  font-size: 12px;
  font-weight: 600;
}

.teamTableSection :deep(.ant-table-tbody > tr > td) {
  padding-top: 11px;
  padding-bottom: 11px;
}

@media (max-width: 760px) {
  .teamTree {
    padding: 22px 18px;
    align-items: flex-start;
  }

  .teamRoot {
    width: min(320px, calc(100vw - 76px));
  }

  .teamRootStem {
    margin-left: min(160px, calc((100vw - 76px) / 2));
  }
}
</style>
