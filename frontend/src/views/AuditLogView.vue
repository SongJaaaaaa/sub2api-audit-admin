<script setup lang="ts">
import { AuditOutlined, CheckCircleOutlined, CloseCircleOutlined, ExclamationCircleOutlined, FileTextOutlined, PaperClipOutlined, SettingOutlined, UserAddOutlined } from '@ant-design/icons-vue'
import type { TablePaginationConfig } from 'ant-design-vue'
import { message } from 'ant-design-vue'
import type { Dayjs } from 'dayjs'
import { onMounted, reactive, ref } from 'vue'
import { getAuditLogs, type AuditLog, type AuditSummary } from '../api/audit'

import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useAdminOptions } from '../composables/useAdminOptions'
import { useTableColumns } from '../composables/useTableColumns'
// ── 中文映射 ──────────────────────────────────────────────────────────
const actionLabels: Record<string, string> = {
  'admin.create':                '新增管理员',
  'ledger_adjustment.succeeded': '调额成功',
  'ledger_adjustment.exception': '调额异常',
  'ledger_adjustment.voided':    '调额作废',
  'operation_expense.create':    '新增经营支出',
  'reconcile.create':            '生成对账批次',
  'reconcile.run':               '运行 / 重跑对账',
  'profit_settlement.confirm':   '确认利润分账',
  'profit_settlement.reverse':   '撤销利润分账',
  'attachment.upload':           '上传附件',
}

const targetTypeLabels: Record<string, string> = {
  admin:                '管理员账号',
  ledger_adjustment:    '调额记录',
  operation_expense:    '经营支出',
  reconciliation_batch: '对账批次',
  profit_settlement:     '利润分账批次',
  attachment:           '附件',
}

const actionTagProps: Record<string, { color: string; icon: any }> = {
  'admin.create':                { color: 'red',       icon: UserAddOutlined },
  'ledger_adjustment.succeeded': { color: 'success',  icon: CheckCircleOutlined },
  'ledger_adjustment.exception': { color: 'warning',  icon: ExclamationCircleOutlined },
  'ledger_adjustment.voided':    { color: 'error',    icon: CloseCircleOutlined },
  'operation_expense.create':    { color: 'blue',     icon: FileTextOutlined },
  'reconcile.create':            { color: 'purple',   icon: AuditOutlined },
  'reconcile.run':               { color: 'purple',   icon: AuditOutlined },
  'profit_settlement.confirm':   { color: 'green',    icon: CheckCircleOutlined },
  'profit_settlement.reverse':   { color: 'orange',   icon: CloseCircleOutlined },
  'attachment.upload':           { color: 'cyan',     icon: PaperClipOutlined },
}

// ── 字段中文名 ────────────────────────────────────────────────────────
const fieldLabels: Record<string, Record<string, string>> = {
  admin: {
    id:         '管理员ID',
    name:       '管理员姓名',
    email:      '登录邮箱',
    status:     '状态',
    created_at: '创建时间',
  },
  ledger_adjustment: {
    id:                 '调额ID',
    ledger_no:          '账本号',
    sub2api_user_id:    '用户ID',
    sub2api_user_email: '用户邮箱',
    operation:          '操作方向',
    amount:             '调整金额',
    cash_amount:        '入账金额',
    gift_quota_amount:  '赠送额度',
    before_balance:     '调整前余额',
    after_balance:      '调整后余额',
    status:             '状态',
    adjust_reason:      '调整原因',
    admin_notes:        '管理员备注',
    sub2api_notes:      'Sub2API备注',
    exception_reason:   '异常原因',
    idempotency_key:    '幂等键',
    called_at:          '调用时间',
    confirmed_at:       '确认时间',
    created_at:         '创建时间',
  },
  operation_expense: {
    id:          '支出ID',
    expense_no:  '单号',
    category:    '分类',
    amount:      '金额',
    paid_at:     '发生日期',
    remark:      '备注',
    created_at:  '创建时间',
  },
  reconciliation_batch: {
    id:                  '批次ID',
    batch_no:            '批次号',
    biz_date:            '业务日期',
    cash_total:          '现金合计',
    quota_total:         '额度合计',
    gift_total:          '赠送合计',
    sub2api_delta_total: 'Sub2API变动',
    diff_amount:         '差异金额',
    status:              '状态',
    created_at:          '创建时间',
  },
  profit_settlement: {
    id:            '批次ID',
    batch_no:      '批次号',
    start_date:    '开始日期',
    end_date:      '结束日期',
    income_total:  '收入合计',
    expense_total: '支出合计',
    profit_total:  '净利润',
    income_count:  '收入笔数',
    expense_count: '支出笔数',
    status:        '状态',
    operator_name: '操作人',
    reverser_name: '撤销人',
    reversed_at:   '撤销时间',
    created_at:    '确认时间',
  },
  attachment: {
    id:              '附件ID',
    attachable_type: '关联类型',
    attachable_id:   '关联ID',
    original_name:   '文件名',
    mime:            '文件类型',
    size:            '文件大小',
    created_at:      '上传时间',
  },
}

// 字段值翻译
const valueTranslations: Record<string, Record<string, string>> = {
  operation: { increment: '充值(+)', decrement: '扣减(-)' },
  status:    { succeeded: '成功', exception: '异常', voided: '作废', ok: '正常', warning: '告警', error: '异常', balanced: '已对平', diff: '有差异', confirmed: '已确认', reversed: '已撤销' },
  attachable_type: { ledger_adjustment: '调额记录', operation_expense: '经营支出', reconciliation_batch: '对账批次' },
}

const skipFields = new Set(['content_html', 'admin_notes'])

function renderFieldValue(key: string, val: unknown): string {
  if (val === null || val === undefined || val === '') return '-'
  const trans = valueTranslations[key]
  if (trans && typeof val === 'string' && trans[val]) return trans[val]
  if (key === 'size' && typeof val === 'number') {
    return val >= 1024 * 1024 ? `${(val / 1024 / 1024).toFixed(2)} MB` : `${(val / 1024).toFixed(1)} KB`
  }
  return String(val)
}

function buildDetailRows(targetType: string | null, data: Record<string, unknown> | null | undefined) {
  if (!data || typeof data !== 'object') return []
  const map = fieldLabels[targetType ?? ''] ?? {}
  return Object.entries(data)
    .filter(([k, v]) => !skipFields.has(k) && v !== null && v !== undefined && v !== '')
    .map(([k, v]) => ({ key: k, label: map[k] || k, value: renderFieldValue(k, v) }))
}

function actionLabel(action: string) {
  return actionLabels[action] || action
}

function targetLabel(type: string) {
  return targetTypeLabels[type] || type
}

// ── 组件逻辑 ──────────────────────────────────────────────────────────
const adminOptions = useAdminOptions()
const loading = ref(false)
const drawerOpen = ref(false)
const items = ref<AuditLog[]>([])
const selected = ref<AuditLog | null>(null)
const filters = reactive({ action: '', admin_id: undefined as number | undefined, target_type: '', target_id: '', ip: '', keyword: '', risk: '' as '' | 'high', dates: null as [Dayjs, Dayjs] | null })
const summary = reactive<AuditSummary>({ record_count: 0, operator_count: 0, action_count: 0, target_count: 0, high_risk_count: 0, actions: [] })
const page = reactive({ current: 1, pageSize: 20, total: 0 })

const allColumns = [
  { title: '操作', dataIndex: 'action', width: 200 },
  { title: '管理员', dataIndex: 'admin_name', width: 120 },
  { title: '对象', dataIndex: 'target_type', width: 130 },
  { title: '对象ID', dataIndex: 'target_id', width: 90 },
  { title: 'IP', dataIndex: 'ip', width: 140 },
  { title: '时间', dataIndex: 'created_at', width: 180 },
  { title: '详情', dataIndex: 'detail', fixed: 'right', width: 80 },
] as const
const { columns, visibleCols, colOptions, tableWidth, resizeColumn, resetColumns } = useTableColumns('audit-log-columns', allColumns, 1050)

async function loadItems() {
  loading.value = true
  try {
    const res = await getAuditLogs({
      page: page.current,
      page_size: page.pageSize,
      action: filters.action,
      admin_id: filters.admin_id,
      target_type: filters.target_type,
      target_id: filters.target_id,
      ip: filters.ip,
      keyword: filters.keyword,
      risk: filters.risk,
      from: filters.dates?.[0].format('YYYY-MM-DD'),
      to: filters.dates?.[1].format('YYYY-MM-DD'),
    })
    items.value = res.items
    page.total = res.total
    if (res.summary) Object.assign(summary, res.summary)
  } catch {
    message.error('读取操作审计失败')
  } finally {
    loading.value = false
  }
}

function search() {
  page.current = 1
  loadItems()
}

function resetFilters() { Object.assign(filters, { action: '', admin_id: undefined, target_type: '', target_id: '', ip: '', keyword: '', risk: '', dates: null }); search() }

function change(pager: TablePaginationConfig) {
  page.current = pager.current || 1
  page.pageSize = pager.pageSize || 20
  loadItems()
}

function openDetail(row: AuditLog) {
  selected.value = row
  drawerOpen.value = true
}

const selectedAfterRows = () => buildDetailRows(selected.value?.target_type ?? null, selected.value?.after_value as Record<string, unknown> | null)
const selectedBeforeRows = () => buildDetailRows(selected.value?.target_type ?? null, selected.value?.before_value as Record<string, unknown> | null)

onMounted(loadItems)
</script>

<template>
  <section class="page">
    <div class="pageHead pageHeadActionsOnly">
      <a-button @click="loadItems">刷新</a-button>
    </div>

    <div class="filterBar">
      <a-select v-model:value="filters.action" class="filterAction" placeholder="操作类型" allow-clear><a-select-option value="">全部操作</a-select-option><a-select-option v-for="(label, key) in actionLabels" :key="key" :value="key">{{ label }}</a-select-option></a-select>
      <a-select v-model:value="filters.admin_id" class="filterLg" placeholder="操作人" allow-clear><a-select-option v-for="row in adminOptions" :key="row.id" :value="row.id">{{ row.name }}（{{ row.email }}）</a-select-option></a-select>
      <a-select v-model:value="filters.target_type" class="filterSm" placeholder="对象类型" allow-clear><a-select-option value="">全部对象</a-select-option><a-select-option v-for="(label, key) in targetTypeLabels" :key="key" :value="key">{{ label }}</a-select-option></a-select>
      <a-input v-model:value="filters.target_id" class="filterId" placeholder="对象 ID" allow-clear />
      <a-input v-model:value="filters.ip" class="filterIp" placeholder="IP" allow-clear @press-enter="search" />
      <a-range-picker v-model:value="filters.dates" class="filterDate" />
      <a-input v-model:value="filters.keyword" class="filterGrow" placeholder="关键字" allow-clear @press-enter="search" />
      <a-select v-model:value="filters.risk" class="filterSm" placeholder="风险级别"><a-select-option value="">全部风险</a-select-option><a-select-option value="high">高风险操作</a-select-option></a-select>
      <a-button type="primary" @click="search">查询</a-button><a-button @click="resetFilters">重置</a-button>
    </div>
    <div class="summaryGrid">
      <section><span>操作总数</span><strong>{{ summary.record_count }}</strong></section>
      <section><span>操作人数</span><strong>{{ summary.operator_count }}</strong></section>
      <section><span>涉及对象数</span><strong>{{ summary.target_count }}</strong></section>
      <section><span>高风险操作数</span><strong class="risk">{{ summary.high_risk_count }}</strong></section>
    </div>

    <div class="tableTools">
      <ColumnSettings v-model:value="visibleCols" v-model:width="tableWidth" :options="colOptions" @reset="resetColumns" />
    </div>
    <a-table
      row-key="id"
      :columns="columns"
      :data-source="items"
      :loading="loading"
      :pagination="page"
      :scroll="{ x: tableWidth }"
      :locale="{ emptyText: '暂无审计日志' }"
      @resize-column="resizeColumn"
      @change="change"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.dataIndex === 'action'">
          <a-tag
            :color="actionTagProps[record.action]?.color || 'default'"
          >
            <template #icon>
              <component :is="actionTagProps[record.action]?.icon || SettingOutlined" />
            </template>
            {{ actionLabel(record.action) }}
          </a-tag>
        </template>
        <template v-if="column.dataIndex === 'target_type'">
          <span>{{ targetLabel(record.target_type) }}</span>
        </template>
        <template v-if="column.dataIndex === 'detail'">
          <a-button size="small" @click="openDetail(record)">详情</a-button>
        </template>
      </template>
    </a-table>

    <!-- 审计详情 Drawer -->
    <a-drawer
      v-model:open="drawerOpen"
      title="审计详情"
      width="580"
    >
      <template v-if="selected">
        <!-- 头部信息卡 -->
        <div class="auditHeadCard">
          <div class="auditHeadRow">
            <a-tag
              :color="actionTagProps[selected.action]?.color || 'default'"
              style="font-size:14px;padding:4px 12px;"
            >
              <template #icon>
                <component :is="actionTagProps[selected.action]?.icon || SettingOutlined" />
              </template>
              {{ actionLabel(selected.action) }}
            </a-tag>
            <span class="auditHeadTime">{{ selected.created_at }}</span>
          </div>
          <div class="auditHeadMeta">
            <span>管理员：<strong>{{ selected.admin_name || '-' }}</strong>（ID {{ selected.admin_id || '-' }}）</span>
            <span>对象：<strong>{{ targetLabel(selected.target_type) }}</strong> #{{ selected.target_id || '-' }}</span>
            <span>IP：{{ selected.ip || '-' }}</span>
          </div>
        </div>

        <!-- 变动详情 -->
        <div v-if="selectedAfterRows().length > 0" class="auditSection">
          <div class="auditSectionTitle">变动记录</div>
          <div class="auditFieldGrid">
            <div
              v-for="row in selectedAfterRows()"
              :key="row.key"
              class="auditFieldItem"
            >
              <span class="auditFieldLabel">{{ row.label }}</span>
              <span class="auditFieldValue">{{ row.value }}</span>
            </div>
          </div>
        </div>

        <!-- 变动前（若有） -->
        <div v-if="selectedBeforeRows().length > 0" class="auditSection">
          <div class="auditSectionTitle">变动前</div>
          <div class="auditFieldGrid">
            <div v-for="row in selectedBeforeRows()" :key="row.key" class="auditFieldItem">
              <span class="auditFieldLabel">{{ row.label }}</span>
              <span class="auditFieldValue">{{ row.value }}</span>
            </div>
          </div>
        </div>

        <!-- User-Agent -->
        <div v-if="selected.user_agent" class="auditSection">
          <div class="auditSectionTitle">浏览器信息</div>
          <div class="auditUa">{{ selected.user_agent }}</div>
        </div>
      </template>
    </a-drawer>
  </section>
</template>

<style scoped>
.filterBar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
.filterId { flex: 0 0 120px; }.filterIp, .filterSm { flex: 0 0 150px; }.filterAction { flex: 0 0 190px; }.filterLg { flex: 0 0 220px; }.filterDate { flex: 0 0 250px; }.filterGrow { flex: 1 1 190px; max-width: 300px; }
.summaryGrid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
.summaryGrid section { padding: 14px 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 12px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; color: var(--text-secondary, #7a8395); font-size: 12px; margin-bottom: 6px; }
.summaryGrid strong { font-size: 21px; } .risk { color: #cf1322; }
@media (max-width: 760px) { .filterBar > * { flex: 1 1 100%; width: 100% !important; max-width: none; } .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
</style>
