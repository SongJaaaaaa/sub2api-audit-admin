<script setup lang="ts">
import { ReloadOutlined, SearchOutlined } from '@ant-design/icons-vue'
import { App as AntApp } from 'ant-design-vue'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import { BarChart } from 'echarts/charts'
import { DataZoomComponent, GridComponent, TooltipComponent } from 'echarts/components'
import { init, use, type ECharts } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppMode } from '../app/composables/useAppMode'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useTableColumns } from '../composables/useTableColumns'
import { useThemeStore } from '../stores/theme'
import {
  getConsumptionRanking,
  getModelStats,
  type ConsumptionRank,
  type ModelStat,
  type ModelStatsRes,
  type ModelUserRank,
} from '../api/sub2api'

use([BarChart, DataZoomComponent, GridComponent, TooltipComponent, CanvasRenderer])

const { message } = AntApp.useApp()
const { isAppMode } = useAppMode()
const route = useRoute()
const router = useRouter()
const themeStore = useThemeStore()
const loading = ref(false)
const rankingLoading = ref(false)
const stats = ref<ModelStatsRes | null>(null)
const ranking = ref<ConsumptionRank[]>([])
const statsError = ref('')
const rankingError = ref('')
const activeTab = ref<'models' | 'users'>('models')
const range = ref<[Dayjs, Dayjs]>([dayjs().subtract(6, 'day'), dayjs()])
const model = ref('')
const limit = ref(20)
const modelOptions = ref<{ label: string; value: string }[]>([])
const userChartEl = ref<HTMLDivElement | null>(null)
let userChart: ECharts | null = null
let statsVersion = 0
let rankingVersion = 0

const selectedModel = computed(() => stats.value?.selected_model || '')
const isAppDetail = computed(() => isAppMode.value && route.name === 'sub2-model-detail')
const modelRows = computed<ModelStat[]>(() => [...(stats.value?.models || [])].sort((a, b) => b.total_tokens - a.total_tokens))
const userRows = computed<ModelUserRank[]>(() => [...(stats.value?.users || [])].sort((a, b) => b.total_tokens - a.total_tokens))
const pageLoading = computed(() => activeTab.value === 'models' ? loading.value : rankingLoading.value)
const activeError = computed(() => activeTab.value === 'models' ? statsError.value : rankingError.value)

const modelColumns = [
  { title: '排名', dataIndex: 'rank', width: 72, align: 'center' },
  { title: '请求模型（requested）', dataIndex: 'model', minWidth: 240 },
  { title: '请求数', dataIndex: 'request_count', width: 120, align: 'right' },
  { title: '输入 Token', dataIndex: 'input_tokens', width: 145, align: 'right' },
  { title: '输出 Token', dataIndex: 'output_tokens', width: 145, align: 'right' },
  { title: '缓存创建', dataIndex: 'cache_creation_tokens', width: 145, align: 'right' },
  { title: '缓存读取', dataIndex: 'cache_read_tokens', width: 145, align: 'right' },
  { title: '总 Token', dataIndex: 'total_tokens', width: 160, align: 'right' },
] as const

const { columns: visibleModelColumns, visibleCols, colOptions, tableWidth, resizeColumn, resetColumns } = useTableColumns('sub2api-model-columns', modelColumns, 1190)

const rankingColumns = [
  { title: '排名', dataIndex: 'rank', width: 72, align: 'center' },
  { title: '用户', dataIndex: 'email', minWidth: 280 },
  { title: '请求数', dataIndex: 'request_count', width: 150, align: 'right' },
  { title: 'Token', dataIndex: 'total_tokens', width: 180, align: 'right' },
  { title: '实际消费', dataIndex: 'actual_cost', width: 180, align: 'right' },
] as const
const {
  columns: visibleRankingColumns,
  visibleCols: visibleRankingCols,
  colOptions: rankingColOptions,
  tableWidth: rankingTableWidth,
  resizeColumn: resizeRankingColumn,
  resetColumns: resetRankingColumns,
} = useTableColumns('sub2api-consumption-ranking-columns', rankingColumns, 920)

async function loadStats() {
  const [start, end] = range.value
  const version = ++statsVersion
  loading.value = true
  statsError.value = ''

  try {
    const res = await getModelStats({
      start_date: start.format('YYYY-MM-DD'),
      end_date: end.format('YYYY-MM-DD'),
      model: model.value.trim() || undefined,
      limit: limit.value,
    })
    if (version !== statsVersion) return
    stats.value = res

    if (res.models.length) {
      modelOptions.value = res.models.map((row) => ({ label: row.model, value: row.model }))
    }
  } catch (err) {
    if (version !== statsVersion) return
    stats.value = null
    const data = (err as { response?: { data?: { code?: string; message?: string } } }).response?.data
    statsError.value = data?.code === 'SUB2API_STATS_UNAVAILABLE'
      ? 'Sub2API 官方统计暂不可用，页面不会使用自定义 SQL 或假 0 代替。'
      : (data?.message || '读取模型统计失败。')
    message.error(statsError.value)
  } finally {
    if (version === statsVersion) {
      loading.value = false
      renderUserChart()
    }
  }
}

async function loadRanking() {
  const [start, end] = range.value
  const version = ++rankingVersion
  rankingLoading.value = true
  rankingError.value = ''

  try {
    const res = await getConsumptionRanking({
      start_date: start.format('YYYY-MM-DD'),
      end_date: end.format('YYYY-MM-DD'),
      limit: limit.value,
    })
    if (version !== rankingVersion) return
    ranking.value = res.items
  } catch (err) {
    if (version !== rankingVersion) return
    ranking.value = []
    const data = (err as { response?: { data?: { message?: string } } }).response?.data
    rankingError.value = data?.message || '读取消费排行失败。'
    message.error(rankingError.value)
  } finally {
    if (version === rankingVersion) rankingLoading.value = false
  }
}

function loadData() {
  return activeTab.value === 'models' ? loadStats() : loadRanking()
}

function changeTab(key: string | number) {
  const tab = key as 'models' | 'users'
  if (tab !== 'models') {
    userChart?.dispose()
    userChart = null
  }
  activeTab.value = tab
  loadData()
}

function renderUserChart() {
  nextTick(() => {
    if (!userChartEl.value || !selectedModel.value || userRows.value.length === 0) {
      userChart?.dispose()
      userChart = null
      return
    }
    userChart ||= init(userChartEl.value)
    const rows = userRows.value
    userChart.setOption({
      grid: { left: 18, right: 22, top: 30, bottom: 105, containLabel: true },
      tooltip: {
        trigger: 'item',
        ...tooltipTheme(),
        borderWidth: 1,
        extraCssText: 'border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.2);padding:12px 14px;',
        formatter: ({ dataIndex }: { dataIndex: number }) => {
          const row = rows[dataIndex]
          if (!row) return ''
          return [
            `<strong>${row.email || `用户 #${row.user_id}`}</strong>`,
            `用户 ID：${row.user_id}`,
            `请求次数：${formatCount(row.request_count)}`,
            `输入 Token：${formatToken(row.input_tokens)}`,
            `输出 Token：${formatToken(row.output_tokens)}`,
            `缓存 Token：${formatToken(row.cache_tokens)}`,
            `总 Token：${formatToken(row.total_tokens)}`,
          ].join('<br>')
        },
      },
      xAxis: { type: 'category', data: rows.map(row => row.email || `用户 #${row.user_id}`), axisLabel: { rotate: -28, interval: 0, width: 120, overflow: 'truncate', color: chartText() }, axisTick: { show: false }, axisLine: { lineStyle: { color: chartAxis() } } },
      yAxis: { type: 'value', name: '总 Token', nameTextStyle: { color: chartText() }, axisLabel: { formatter: formatToken, color: chartText() }, splitLine: { lineStyle: { color: chartSplit() } } },
      dataZoom: rows.length > 10 ? [{ type: 'slider', xAxisIndex: 0, bottom: 5, height: 18, startValue: 0, endValue: 9, ...zoomTheme() }, { type: 'inside', xAxisIndex: 0 }] : [],
      series: [{ type: 'bar', data: rows.map(row => row.total_tokens), barMaxWidth: 42, showBackground: true, backgroundStyle: { color: 'rgba(22,119,255,.06)', borderRadius: [7, 7, 0, 0] }, itemStyle: { color: { type: 'linear', x: 0, y: 1, x2: 0, y2: 0, colorStops: [{ offset: 0, color: '#1677ff' }, { offset: 1, color: '#69b1ff' }] }, borderRadius: [7, 7, 0, 0] }, emphasis: { itemStyle: { shadowBlur: 14, shadowColor: 'rgba(22,119,255,.35)' } } }],
    }, true)
  })
}

function resizeChart() { userChart?.resize() }

function chartText() { return themeStore.themeName === 'dark' ? '#c5c8d0' : '#7a8395' }
function chartAxis() { return themeStore.themeName === 'dark' ? '#4a4f5d' : '#d9dce3' }
function chartSplit() { return themeStore.themeName === 'dark' ? '#2d313b' : '#eef0f4' }

function tooltipTheme() {
  const dark = themeStore.themeName === 'dark'
  return {
    backgroundColor: dark ? 'rgba(17, 24, 39, 0.96)' : 'rgba(255, 255, 255, 0.96)',
    borderColor: dark ? '#374151' : '#e5e7eb',
    textStyle: { color: dark ? '#f8fafc' : '#172033' },
  }
}

function zoomTheme() {
  const dark = themeStore.themeName === 'dark'
  const primary = dark ? '#60a5fa' : '#2563eb'
  const fill = dark ? 'rgba(96, 165, 250, 0.2)' : 'rgba(37, 99, 235, 0.14)'
  return {
    backgroundColor: 'transparent',
    borderColor: chartAxis(),
    fillerColor: fill,
    textStyle: { color: chartText() },
    handleStyle: { color: primary, borderColor: primary },
    moveHandleStyle: { color: primary },
    dataBackground: { lineStyle: { color: chartAxis() }, areaStyle: { color: chartSplit() } },
    selectedDataBackground: { lineStyle: { color: primary }, areaStyle: { color: fill } },
  }
}

function clearModel() {
  userChart?.dispose()
  userChart = null
  model.value = ''
  loadStats()
}

async function selectModel(name: string) {
  if (isAppMode.value) {
    const [start, end] = range.value
    await router.push({
      name: 'sub2-model-detail',
      query: {
        model: name,
        start_date: start.format('YYYY-MM-DD'),
        end_date: end.format('YYYY-MM-DD'),
        limit: limit.value,
      },
    })
    return
  }
  model.value = name
  await loadStats()
}

function queryText(value: unknown) {
  return Array.isArray(value) ? String(value[0] || '') : String(value || '')
}

function loadRouteData() {
  if (!isAppDetail.value) {
    if (isAppMode.value) {
      model.value = ''
      stats.value = null
    }
    return loadData()
  }

  activeTab.value = 'models'
  stats.value = null
  model.value = queryText(route.query.model)
  const start = dayjs(queryText(route.query.start_date))
  const end = dayjs(queryText(route.query.end_date))
  if (start.isValid() && end.isValid()) range.value = [start, end]
  const size = Number(queryText(route.query.limit))
  if (size) limit.value = size
  return loadStats()
}

function searchStats() {
  const name = model.value.trim()
  if (isAppMode.value && activeTab.value === 'models' && name && !isAppDetail.value) return selectModel(name)
  return loadData()
}

function formatCount(val: number | string | null | undefined) {
  return Number(val || 0).toLocaleString('zh-CN', { maximumFractionDigits: 0 })
}

function formatToken(val: number | string | null | undefined) {
  const num = Number(val || 0)
  const unit = Math.abs(num) >= 1_000_000_000 ? 'B' : 'M'
  const base = unit === 'B' ? 1_000_000_000 : 1_000_000
  return `${(num / base).toLocaleString('zh-CN', { maximumFractionDigits: 2 })}${unit}`
}

function formatMoney(val: number | string | null | undefined) {
  return Number(val || 0).toFixed(2)
}

onMounted(() => { window.addEventListener('resize', resizeChart); loadRouteData() })
onBeforeUnmount(() => { window.removeEventListener('resize', resizeChart); userChart?.dispose() })
watch(() => themeStore.themeName, renderUserChart)
watch(() => route.fullPath, () => { if (isAppMode.value) void loadRouteData() })
</script>

<template>
  <section class="page modelStatsPage">
    <div v-if="!isAppDetail" class="pageHead pageHeadActionsOnly">
      <div class="headActions modelFilters">
        <a-range-picker
          v-model:value="range"
          :allow-clear="false"
          :disabled="pageLoading"
          @change="searchStats"
        />
        <a-auto-complete
          v-if="activeTab === 'models'"
          v-model:value="model"
          :options="modelOptions"
          allow-clear
          placeholder="留空查看模型榜，输入模型查看用户榜"
          class="modelInput"
          @select="selectModel"
          @clear="clearModel"
        />
        <a-select v-model:value="limit" class="limitSelect">
          <a-select-option :value="10">Top 10</a-select-option>
          <a-select-option :value="20">Top 20</a-select-option>
          <a-select-option :value="50">Top 50</a-select-option>
          <a-select-option :value="100">Top 100</a-select-option>
        </a-select>
        <a-button type="primary" :loading="pageLoading" @click="searchStats">
          <template #icon><SearchOutlined /></template>
          查询
        </a-button>
        <a-button :disabled="pageLoading" @click="searchStats">
          <template #icon><ReloadOutlined /></template>
        </a-button>
      </div>
    </div>

    <a-tabs v-if="!isAppDetail" :active-key="activeTab" class="rankingTabs" @change="changeTab">
      <a-tab-pane key="models" tab="模型消耗统计" />
      <a-tab-pane key="users" tab="消费排行榜" />
    </a-tabs>

    <a-alert
      v-if="activeError"
      type="error"
      show-icon
      :message="activeError"
      :description="activeTab === 'models' && stats ? '当前保留的是上一次成功查询结果。' : undefined"
      class="statsAlert"
    />

    <div v-if="!isAppDetail && activeTab === 'models' && stats" class="summaryGrid">
      <section><span>请求总数</span><strong>{{ formatCount(stats.summary.request_count) }}</strong></section>
      <section><span>Token 总数</span><strong>{{ formatToken(stats.summary.total_tokens) }}</strong></section>
      <section><span>缓存 Token</span><strong>{{ formatToken(stats.summary.cache_tokens) }}</strong></section>
      <section><span>缓存占比</span><strong>{{ stats.summary.cache_rate.toFixed(2) }}%</strong></section>
    </div>

    <a-spin v-if="activeTab === 'models'" :spinning="loading">
      <section v-if="stats && !selectedModel" class="panel">
        <div class="sectionHead">
          <div>
            <h2>请求模型 Token 排行</h2>
          </div>
          <span>返回 {{ modelRows.length }} 个模型</span>
        </div>

        <template v-if="isAppMode">
          <div v-if="modelRows.length" class="appStatList">
            <button v-for="(record, index) in modelRows" :key="record.model" type="button" class="appStatCard" @click="selectModel(record.model)">
              <span class="appStatRank">{{ index + 1 }}</span>
              <span class="appStatMain">
                <strong class="appStatTitle">{{ record.model }}</strong>
                <span class="appStatMeta">{{ formatCount(record.request_count) }} 次请求 · {{ formatToken(record.total_tokens) }}</span>
              </span>
              <span class="appStatValue">{{ formatMoney(record.actual_cost) }}</span>
            </button>
          </div>
          <a-empty v-else description="所选日期内暂无官方模型统计" />
        </template>
        <template v-else>
          <div class="tableTools"><ColumnSettings v-model:value="visibleCols" v-model:width="tableWidth" :options="colOptions" @reset="resetColumns" /></div>
          <a-table
            row-key="model"
            :columns="visibleModelColumns"
            :data-source="modelRows"
            :pagination="false"
            :scroll="{ x: tableWidth }"
            :locale="{ emptyText: '所选日期内暂无官方模型统计' }"
            size="middle"
            @resize-column="resizeColumn"
          >
            <template #bodyCell="{ column, record, index }">
              <template v-if="column.dataIndex === 'rank'">
                <span class="rankNo">{{ index + 1 }}</span>
              </template>
              <template v-else-if="column.dataIndex === 'model'">
                <a-button type="link" class="modelLink" @click="selectModel(record.model)">
                  {{ record.model }}
                </a-button>
              </template>
              <template v-else-if="column.dataIndex === 'request_count'">
                {{ formatCount(record.request_count) }}
              </template>
              <template v-else-if="String(column.dataIndex).includes('tokens')">
                <span class="token">{{ formatToken(record[column.dataIndex]) }}</span>
              </template>
            </template>
          </a-table>
        </template>
      </section>

      <section v-else-if="stats" class="panel">
        <div class="sectionHead">
          <div>
            <h2>{{ selectedModel }} · 用户 Token 排行</h2>
          </div>
          <a-button v-if="!isAppMode" @click="clearModel">返回模型榜</a-button>
        </div>

        <a-empty v-if="userRows.length === 0" description="该请求模型在所选日期内暂无用户用量" />
        <template v-else-if="isAppMode">
          <div class="appModelDetailSummary">
            <article><span>请求数</span><strong>{{ formatCount(stats.summary.request_count) }}</strong></article>
            <article><span>总 Token</span><strong>{{ formatToken(stats.summary.total_tokens) }}</strong></article>
            <article><span>缓存占比</span><strong>{{ stats.summary.cache_rate.toFixed(2) }}%</strong></article>
            <article><span>实际消费</span><strong class="cost">{{ formatMoney(stats.summary.actual_cost) }}</strong></article>
          </div>
          <div class="appStatList">
            <article v-for="(record, index) in userRows" :key="record.user_id" class="appStatCard appUserStatCard">
              <span class="appStatRank">{{ index + 1 }}</span>
              <span class="appStatMain">
                <strong class="appStatTitle">{{ record.email || `用户 #${record.user_id}` }}</strong>
                <span class="appStatMeta">{{ formatCount(record.request_count) }} 次请求 · {{ formatToken(record.total_tokens) }}</span>
              </span>
              <strong class="appStatValue cost">{{ formatMoney(record.actual_cost) }}</strong>
            </article>
          </div>
        </template>
        <div v-else ref="userChartEl" class="userRankChart"></div>
      </section>

      <a-empty v-else-if="!loading" description="暂无可展示的官方统计数据" />
    </a-spin>

    <a-spin v-else :spinning="rankingLoading">
      <section class="panel">
        <div class="sectionHead">
          <div><h2>Sub2API 用户消费排行</h2></div>
          <span>返回 {{ ranking.length }} 个用户</span>
        </div>
        <template v-if="isAppMode">
          <div v-if="ranking.length" class="appStatList">
            <article v-for="(record, index) in ranking" :key="record.user_id" class="appStatCard appUserStatCard">
              <span class="appStatRank">{{ index + 1 }}</span>
              <span class="appStatMain">
                <strong class="appStatTitle">{{ record.email || `用户 #${record.user_id}` }}</strong>
                <span class="appStatMeta">{{ formatCount(record.request_count) }} 次请求 · {{ formatToken(record.total_tokens) }}</span>
              </span>
              <strong class="appStatValue cost">{{ formatMoney(record.actual_cost) }}</strong>
            </article>
          </div>
          <a-empty v-else description="所选日期内暂无用户消费数据" />
        </template>
        <template v-else>
          <div class="tableTools">
            <ColumnSettings
              v-model:value="visibleRankingCols"
              v-model:width="rankingTableWidth"
              :options="rankingColOptions"
              @reset="resetRankingColumns"
            />
          </div>
          <a-table
            row-key="user_id"
            :columns="visibleRankingColumns"
            :data-source="ranking"
            :pagination="false"
            :scroll="{ x: rankingTableWidth }"
            :locale="{ emptyText: '所选日期内暂无用户消费数据' }"
            size="middle"
            @resize-column="resizeRankingColumn"
          >
            <template #bodyCell="{ column, record, index }">
              <template v-if="column.dataIndex === 'rank'">
                <span class="rankNo">{{ index + 1 }}</span>
              </template>
              <template v-else-if="column.dataIndex === 'email'">
                <strong>{{ record.email || `用户 #${record.user_id}` }}</strong>
                <small>ID: {{ record.user_id }}</small>
              </template>
              <template v-else-if="column.dataIndex === 'request_count'">
                {{ formatCount(record.request_count) }}
              </template>
              <template v-else-if="column.dataIndex === 'total_tokens'">
                <span class="token">{{ formatToken(record.total_tokens) }}</span>
              </template>
              <template v-else-if="column.dataIndex === 'actual_cost'">
                <strong class="cost">{{ formatMoney(record.actual_cost) }}</strong>
              </template>
            </template>
          </a-table>
        </template>
      </section>
    </a-spin>
  </section>
</template>

<style scoped>
.appStatList { display: grid; gap: 10px; }.appStatCard { display: flex; align-items: center; width: 100%; min-width: 0; gap: 10px; padding: 12px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 10px; color: inherit; font: inherit; text-align: left; background: var(--surface2); }
button.appStatCard { cursor: pointer; }button.appStatCard:active { background: var(--primary-soft); }button.appStatCard:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }.appStatRank { display: inline-grid; flex: 0 0 28px; place-items: center; width: 28px; height: 28px; border-radius: 50%; color: var(--primary); font-weight: 700; background: var(--primary-soft); }
.appStatMain { display: grid; min-width: 0; flex: 1; gap: 4px; }.appStatTitle { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.appStatMeta { overflow: hidden; color: var(--text-secondary, #7a8395); font-size: 12px; text-overflow: ellipsis; white-space: nowrap; }.appStatValue { flex: 0 0 auto; color: var(--teal); font-variant-numeric: tabular-nums; }.appUserStatCard .appStatValue { color: var(--danger); }
.appModelDetailSummary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-bottom: 10px; }.appModelDetailSummary article { padding: 10px; border-radius: 9px; background: var(--surface2); }.appModelDetailSummary span { display: block; color: var(--text-secondary, #7a8395); font-size: 12px; }.appModelDetailSummary strong { display: block; margin-top: 4px; font-size: 18px; }
.summaryGrid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.summaryGrid section { padding: 14px 16px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 12px; background: var(--card-bg, #fff); }
.summaryGrid span { display: block; color: var(--text-secondary, #7a8395); font-size: 12px; margin-bottom: 6px; }
.summaryGrid strong { font-size: 21px; }
@media (max-width: 760px) { .summaryGrid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 420px) { .summaryGrid { grid-template-columns: 1fr; } }
.modelStatsPage { display: grid; gap: 16px; }
.modelFilters { flex-wrap: wrap; }
.modelInput { width: min(360px, 42vw); }
.limitSelect { width: 104px; }
.statsAlert { margin-bottom: 0; }
.rankingTabs { margin-bottom: -16px; }
.panel { min-width: 0; padding: 18px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 14px; background: var(--card-bg, #fff); box-shadow: var(--shadow-card); }
.sectionHead { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 15px; }
.sectionHead h2 { margin: 0; font-size: 18px; }
.sectionHead > span { color: var(--text-secondary, #7a8395); font-size: 13px; }
.userRankChart { width: 100%; height: 520px; border-radius: 12px; background: linear-gradient(180deg, rgba(22,119,255,.035), transparent 45%); }
@media (max-width: 640px) { .userRankChart { height: 430px; } }
.modelLink { height: auto; padding: 0; text-align: left; white-space: normal; }
.rankNo { display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; background: var(--primary-soft); color: var(--primary); font-weight: 700; }
.token { font-variant-numeric: tabular-nums; font-weight: 600; }
.token { color: var(--teal); }
.cost { color: var(--danger); font-variant-numeric: tabular-nums; }
small { display: block; margin-top: 3px; color: var(--text-secondary, #7a8395); }
@media (max-width: 900px) {
  .modelFilters, .modelFilters :deep(.ant-picker), .modelInput, .limitSelect { width: 100%; }
  .sectionHead { flex-direction: column; }
}
</style>
