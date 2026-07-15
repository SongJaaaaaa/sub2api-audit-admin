<script setup lang="ts">
import { SearchOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import { getRelationships, searchSub2Users } from '../../api/admin'
import AsyncState from '../../components/AsyncState.vue'
import type { RelationshipRes, UserSearchItem } from '../../types'

const selectedId = ref<number>()
const users = ref<UserSearchItem[]>([])
const userLoading = ref(false)
const loading = ref(false)
const error = ref('')
const result = ref<RelationshipRes | null>(null)
const page = reactive({ current: 1, pageSize: 20 })
let searchTimer: ReturnType<typeof setTimeout> | undefined

const options = computed(() => users.value.map((user) => ({
  value: user.id,
  label: `${user.email}${user.username ? ` (${user.username})` : ''} · ID ${user.id}`,
})))

const initials = computed(() => {
  const user = result.value?.user
  return (user?.username || user?.email || '用').slice(0, 1).toUpperCase()
})

function money(value: string) {
  return `¥${Number(value || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
}

function apiMessage(err: unknown, fallback: string) {
  return (err as { response?: { data?: { message?: string } } }).response?.data?.message || fallback
}

function queueUserSearch(keyword: string) {
  clearTimeout(searchTimer)
  if (!keyword.trim()) {
    users.value = []
    return
  }
  searchTimer = setTimeout(() => loadUsers(keyword.trim()), 300)
}

async function loadUsers(keyword: string) {
  userLoading.value = true
  try {
    users.value = (await searchSub2Users(keyword)).items
  } catch (err) {
    error.value = apiMessage(err, '搜索 Sub2API 用户失败')
  } finally {
    userLoading.value = false
  }
}

function changeUser() {
  result.value = null
  error.value = ''
  page.current = 1
}

async function load() {
  if (!selectedId.value) return
  loading.value = true
  error.value = ''
  try {
    result.value = await getRelationships({
      user_id: selectedId.value,
      page: page.current,
      page_size: page.pageSize,
    })
  } catch (err) {
    result.value = null
    error.value = apiMessage(err, '读取推荐关系失败')
  } finally {
    loading.value = false
  }
}

function tableChange(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  load()
}

onBeforeUnmount(() => clearTimeout(searchTimer))
</script>

<template>
  <div class="rebatePage relationshipPage">
    <header class="adminPageHead">
      <div>
        <span class="pageEyebrow">推荐网络</span>
        <h1>推荐关系</h1>
        <p>按账号查看直接上级归因与一级推广成员</p>
      </div>
    </header>

    <section class="searchPanel">
      <div class="searchLabel">
        <SearchOutlined />
        <div>
          <strong>查找推广账号</strong>
          <span>支持邮箱、用户名或用户 ID</span>
        </div>
      </div>
      <div class="searchControls">
        <a-select
          v-model:value="selectedId"
          show-search
          allow-clear
          :filter-option="false"
          :options="options"
          :loading="userLoading"
          placeholder="搜索邮箱、用户名或用户 ID"
          @search="queueUserSearch"
          @change="changeUser"
        />
        <a-button type="primary" :disabled="!selectedId" :loading="loading" @click="load">
          <template #icon><SearchOutlined /></template>
          查看关系
        </a-button>
      </div>
    </section>

    <section v-if="!result && !loading && !error" class="relationshipEmpty">
      <span class="emptyIcon"><SearchOutlined /></span>
      <h2>选择账号后查看推荐关系</h2>
      <p>结果仅展示所选账号和他的直接下级</p>
    </section>

    <AsyncState :loading="loading" :error="error" @retry="load">
      <template v-if="result">
        <section class="relationshipFocus">
          <article class="rootAccount">
            <div class="rootTopline">
              <span class="rootBadge">一级推广账号</span>
              <span class="verifiedDot">已同步</span>
            </div>
            <div class="rootIdentity">
              <span class="rootAvatar">{{ initials }}</span>
              <div>
                <h2>{{ result.user.username || result.user.email }}</h2>
                <p>{{ result.user.email }} · ID {{ result.user.user_id }}</p>
              </div>
            </div>
            <div class="parentAttribution">
              <span>直接上级</span>
              <strong v-if="result.user.parent_user_id">
                {{ result.user.parent_email || '邮箱未同步' }} · ID {{ result.user.parent_user_id }}
              </strong>
              <strong v-else>无直接上级</strong>
            </div>
            <div class="rootStats">
              <div>
                <span>直接下级</span>
                <strong>{{ result.user.direct_count }}</strong>
              </div>
              <div>
                <span>下级累计充值</span>
                <strong>{{ money(result.user.total_recharge_amount) }}</strong>
              </div>
              <div>
                <span>产生返利</span>
                <strong>{{ money(result.user.total_rebate_amount) }}</strong>
              </div>
            </div>
          </article>
          <span class="relationshipLine" />
        </section>

        <section class="adminPanel">
          <div class="panelHeader">
            <div>
              <h2>直接下级</h2>
              <p>当前账号的一级推广成员</p>
            </div>
            <span class="memberCount">{{ result.total }} 位成员</span>
          </div>
          <div class="rebateTable">
            <a-table
              row-key="user_id"
              size="small"
              :data-source="result.items"
              :pagination="{ current: result.page, pageSize: result.page_size, total: result.total, showSizeChanger: true }"
              :scroll="{ x: 960 }"
              @change="tableChange"
            >
              <a-table-column title="用户" key="user" :width="260">
                <template #default="{ record }">
                  <div class="rebateUserCell">
                    <strong>{{ record.email }}</strong>
                    <span>{{ record.username || `用户 #${record.user_id}` }}</span>
                  </div>
                </template>
              </a-table-column>
              <a-table-column title="用户 ID" data-index="user_id" :width="100" />
              <a-table-column title="邀请码" data-index="invite_code" :width="130" />
              <a-table-column title="累计充值" key="total_recharge_amount" align="right" :width="140">
                <template #default="{ record }">{{ money(record.total_recharge_amount) }}</template>
              </a-table-column>
              <a-table-column title="产生返利" key="total_rebate_amount" align="right" :width="140">
                <template #default="{ record }"><span class="rebateAmount">{{ money(record.total_rebate_amount) }}</span></template>
              </a-table-column>
              <a-table-column title="加入时间" data-index="created_at" :width="175" />
            </a-table>
          </div>
        </section>
      </template>
    </AsyncState>
  </div>
</template>

<style scoped>
.relationshipPage {
  gap: 24px;
}

.adminPageHead h1 {
  margin: 0;
  color: var(--heading);
  font-size: 28px;
  line-height: 38px;
  letter-spacing: 0;
}

.adminPageHead p {
  margin: 3px 0 0;
  color: var(--muted);
  font-size: 13px;
  line-height: 20px;
}

.pageEyebrow {
  display: block;
  margin-bottom: 4px;
  color: #4648d4;
  font-size: 12px;
  font-weight: 700;
  line-height: 18px;
}

.searchPanel {
  display: flex;
  min-width: 0;
  padding: 18px 20px;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  box-shadow: 0 1px 3px rgb(15 23 42 / 4%);
}

.searchLabel {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 12px;
  color: #4648d4;
  font-size: 20px;
}

.searchLabel div {
  display: flex;
  flex-direction: column;
}

.searchLabel strong {
  color: var(--heading);
  font-size: 14px;
  line-height: 21px;
}

.searchLabel span {
  color: var(--muted);
  font-size: 12px;
  line-height: 18px;
}

.searchControls {
  display: grid;
  width: min(100%, 680px);
  min-width: 0;
  grid-template-columns: minmax(260px, 1fr) auto;
  gap: 10px;
}

.relationshipEmpty {
  display: grid;
  min-height: 320px;
  padding: 48px 24px;
  place-items: center;
  align-content: center;
  border: 1px dashed var(--border);
  border-radius: 8px;
  background: color-mix(in srgb, var(--surface) 65%, transparent);
  text-align: center;
}

.emptyIcon {
  display: inline-flex;
  width: 52px;
  height: 52px;
  margin-bottom: 14px;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  background: #eef0ff;
  color: #4648d4;
  font-size: 24px;
}

.relationshipEmpty h2 {
  margin: 0;
  color: var(--heading);
  font-size: 17px;
  line-height: 25px;
}

.relationshipEmpty p {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 13px;
}

.relationshipFocus {
  display: flex;
  min-height: 250px;
  padding: 30px 20px 0;
  align-items: center;
  flex-direction: column;
  background: color-mix(in srgb, var(--surface) 52%, transparent);
}

.rootAccount {
  width: min(100%, 520px);
  padding: 22px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  box-shadow: 0 8px 24px rgb(15 23 42 / 7%);
}

.rootTopline {
  display: flex;
  margin-bottom: 18px;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.rootBadge,
.verifiedDot,
.memberCount {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  line-height: 22px;
}

.rootBadge {
  padding: 0 10px;
  background: #eeedff;
  color: #4648d4;
}

.verifiedDot {
  color: #059669;
}

.verifiedDot::before {
  width: 7px;
  height: 7px;
  margin-right: 6px;
  border-radius: 50%;
  background: #10b981;
  content: '';
}

.rootIdentity {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 12px;
}

.rootAvatar {
  display: inline-flex;
  width: 48px;
  height: 48px;
  flex: 0 0 48px;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: #172033;
  color: #fff;
  font-size: 18px;
  font-weight: 700;
}

.rootIdentity div {
  min-width: 0;
}

.rootIdentity h2,
.rootIdentity p {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.parentAttribution {
  display: flex;
  min-width: 0;
  margin-top: 16px;
  padding-top: 14px;
  align-items: baseline;
  justify-content: space-between;
  gap: 16px;
  border-top: 1px solid var(--border);
}

.parentAttribution span {
  flex: 0 0 auto;
  color: var(--muted);
  font-size: 11px;
}

.parentAttribution strong {
  min-width: 0;
  overflow-wrap: anywhere;
  color: var(--heading);
  font-size: 12px;
  text-align: right;
}

.rootIdentity h2 {
  margin: 0;
  color: var(--heading);
  font-size: 18px;
  line-height: 26px;
  letter-spacing: 0;
}

.rootIdentity p {
  margin: 2px 0 0;
  color: var(--muted);
  font-size: 12px;
}

.rootStats {
  display: grid;
  margin-top: 18px;
  padding-top: 16px;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  border-top: 1px solid var(--border);
}

.rootStats > div {
  min-width: 0;
  padding: 0 14px;
  border-right: 1px solid var(--border);
}

.rootStats > div:first-child {
  padding-left: 0;
}

.rootStats > div:last-child {
  padding-right: 0;
  border-right: 0;
}

.rootStats span,
.rootStats strong {
  display: block;
  overflow-wrap: anywhere;
}

.rootStats span {
  color: var(--muted);
  font-size: 11px;
  line-height: 17px;
}

.rootStats strong {
  margin-top: 4px;
  color: var(--heading);
  font-size: 16px;
  font-variant-numeric: tabular-nums;
  line-height: 24px;
}

.relationshipLine {
  width: 1px;
  height: 50px;
  background: #cbd5e1;
}

.adminPanel {
  min-width: 0;
  padding: 0 20px 16px;
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

.panelHeader h2,
.panelHeader p {
  margin: 0;
}

.panelHeader h2 {
  color: var(--heading);
  font-size: 17px;
  line-height: 25px;
}

.panelHeader p {
  margin-top: 2px;
  color: var(--muted);
  font-size: 12px;
}

.memberCount {
  padding: 0 10px;
  background: #f2f4f6;
  color: #64748b;
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

@media (max-width: 980px) {
  .searchPanel {
    display: grid;
    align-items: stretch;
    gap: 14px;
  }

  .searchControls {
    width: 100%;
  }
}

@media (max-width: 760px) {
  .relationshipPage {
    gap: 16px;
  }

  .adminPageHead h1 {
    font-size: 24px;
    line-height: 34px;
  }

  .searchPanel,
  .searchControls {
    align-items: stretch;
    grid-template-columns: 1fr;
  }

  .searchPanel {
    display: grid;
    padding: 14px;
    gap: 14px;
  }

  .searchControls {
    width: 100%;
  }

  .relationshipFocus {
    min-height: 0;
    padding: 12px 0 0;
  }

  .rootAccount {
    padding: 16px;
    border-radius: 8px;
  }

  .rootStats {
    grid-template-columns: 1fr;
  }

  .rootStats > div,
  .rootStats > div:first-child,
  .rootStats > div:last-child {
    display: flex;
    padding: 8px 0;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-right: 0;
    border-bottom: 1px solid var(--border);
  }

  .rootStats > div:last-child {
    border-bottom: 0;
  }

  .rootStats strong {
    margin-top: 0;
    text-align: right;
  }

  .relationshipLine {
    height: 28px;
  }

  .adminPanel {
    padding: 0 12px 12px;
    border-radius: 8px;
  }
}
</style>
