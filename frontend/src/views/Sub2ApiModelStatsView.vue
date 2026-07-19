<script setup lang="ts">
import { ReloadOutlined, SearchOutlined } from '@ant-design/icons-vue'
import { message } from 'ant-design-vue'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import { BarChart } from 'echarts/charts'
import { DataZoomComponent, GridComponent, TooltipComponent } from 'echarts/components'
import { init, use, type ECharts } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import ColumnSettings from '../components/table/ColumnSettings.vue'
import { useTableColumns } from '../composables/useTableColumns'
import {
  getModelStats,
  type ModelStat,
  type ModelStatsRes,
  type ModelUserRank,
} from '../api/sub2api'

use([BarChart, DataZoomComponent, GridComponent, TooltipComponent, CanvasRenderer])

const loading = ref(false)
const stats = ref<ModelStatsRes | null>(null)
const statsError = ref('')
const range = ref<[Dayjs, Dayjs]>([dayjs().subtract(6, 'day'), dayjs()])
const model = ref('')
const limit = ref(20)
const modelOptions = ref<{ label: string; value: string }[]>([])
const userChartEl = ref<HTMLDivElement | null>(null)
let userChart: ECharts | null = null

const selectedModel = computed(() => stats.value?.selected_model || '')
const modelRows = computed<ModelStat[]>(() => [...(stats.value?.models || [])].sort((a, b) => b.total_tokens - a.total_tokens))
const userRows = computed<ModelUserRank[]>(() => [...(stats.value?.users || [])].sort((a, b) => b.total_tokens - a.total_tokens))

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

async function loadStats() {
  const [start, end] = range.value
  loading.value = true
  statsError.value = ''

  try {
    const res = await getModelStats({
      start_date: start.format('YYYY-MM-DD'),
      end_date: end.format('YYYY-MM-DD'),
      model: model.value.trim() || undefined,
      limit: limit.value,
    })
    stats.value = res

    if (res.models.length) {
      modelOptions.value = res.models.map((row) => ({ label: row.model, value: row.model }))
    }
  } catch (err) {
    stats.value = null
    const data = (err as { response?: { data?: { code?: string; message?: string } } }).response?.data
    statsError.value = data?.code === 'SUB2API_STATS_UNAVAILABLE'
      ? 'Sub2API 官方统计暂不可用，页面不会使用自定义 SQL 或假 0 代替。'
      : (data?.message || '读取模型统计失败。')
    message.error(statsError.value)
  } finally {
    loading.value = false
    renderUserChart()
  }
}

function renderUserChart() {
  nextTick(() => {
    if (!userChartEl.value || !selectedModel.value) return
    userChart ||= init(userChartEl.value)
    const rows = userRows.value
    userChart.setOption({
      grid: { left: 18, right: 22, top: 30, bottom: 105, containLabel: true },
      tooltip: {
        trigger: 'item',
        backgroundColor: 'rgba(17, 24, 39, .94)',
        borderWidth: 0,
        textStyle: { color: '#fff' },
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
      xAxis: { type: 'category', data: rows.map(row => row.email || `用户 #${row.user_id}`), axisLabel: { rotate: -28, interval: 0, width: 120, overflow: 'truncate', color: '#7a8395' }, axisTick: { show: false }, axisLine: { lineStyle: { color: '#d9dce3' } } },
      yAxis: { type: 'value', name: '总 Token', axisLabel: { formatter: formatToken, color: '#7a8395' }, splitLine: { lineStyle: { color: '#eef0f4' } } },
      dataZoom: rows.length > 10 ? [{ type: 'slider', xAxisIndex: 0, bottom: 5, height: 18, startValue: 0, endValue: 9 }, { type: 'inside', xAxisIndex: 0 }] : [],
      series: [{ type: 'bar', data: rows.map(row => row.total_tokens), barMaxWidth: 42, showBackground: true, backgroundStyle: { color: 'rgba(22,119,255,.06)', borderRadius: [7, 7, 0, 0] }, itemStyle: { color: { type: 'linear', x: 0, y: 1, x2: 0, y2: 0, colorStops: [{ offset: 0, color: '#1677ff' }, { offset: 1, color: '#69b1ff' }] }, borderRadius: [7, 7, 0, 0] }, emphasis: { itemStyle: { shadowBlur: 14, shadowColor: 'rgba(22,119,255,.35)' } } }],
    }, true)
  })
}

function resizeChart() { userChart?.resize() }

function clearModel() {
  userChart?.dispose()
  userChart = null
  model.value = ''
  loadStats()
}

function selectModel(name: string) {
  model.value = name
  loadStats()
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

onMounted(() => { window.addEventListener('resize', resizeChart); loadStats() })
onBeforeUnmount(() => { window.removeEventListener('resize', resizeChart); userChart?.dispose() })
</script>

<template>
  <section class="page modelStatsPage">
    <div class="pageHead pageHeadActionsOnly">
      <div class="headActions modelFilters">
        <a-range-picker
          v-model:value="range"
          :allow-clear="false"
          :disabled="loading"
          @change="loadStats"
        />
        <a-auto-complete
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
        <a-button type="primary" :loading="loading" @click="loadStats">
          <template #icon><SearchOutlined /></template>
          查询
        </a-button>
        <a-button :disabled="loading" @click="loadStats">
          <template #icon><ReloadOutlined /></template>
        </a-button>
      </div>
    </div>

    <a-alert
      v-if="statsError"
      type="error"
      show-icon
      :message="statsError"
      :description="stats ? '当前保留的是上一次成功查询结果。' : undefined"
      class="statsAlert"
    />

    <div v-if="stats" class="summaryGrid">
      <section><span>请求总数</span><strong>{{ formatCount(stats.summary.request_count) }}</strong></section>
      <section><span>Token 总数</span><strong>{{ formatToken(stats.summary.total_tokens) }}</strong></section>
      <section><span>缓存 Token</span><strong>{{ formatToken(stats.summary.cache_tokens) }}</strong></section>
      <section><span>缓存占比</span><strong>{{ stats.summary.cache_rate.toFixed(2) }}%</strong></section>
    </div>

    <a-spin :spinning="loading">
      <section v-if="stats && !selectedModel" class="panel">
        <div class="sectionHead">
          <div>
            <h2>请求模型 Token 排行</h2>
          </div>
          <span>返回 {{ modelRows.length }} 个模型</span>
        </div>

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
      </section>

      <section v-else-if="stats" class="panel">
        <div class="sectionHead">
          <div>
            <h2>{{ selectedModel }} · 用户 Token 排行</h2>
          </div>
          <a-button @click="clearModel">返回模型榜</a-button>
        </div>

        <a-empty v-if="userRows.length === 0" description="该请求模型在所选日期内暂无用户用量" />
        <div v-else ref="userChartEl" class="userRankChart"></div>
      </section>

      <a-empty v-else-if="!loading" description="暂无可展示的官方统计数据" />
    </a-spin>
  </section>
</template>

<style scoped>
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
.panel { min-width: 0; padding: 18px; border: 1px solid var(--border-color, #e8eaf0); border-radius: 14px; background: var(--card-bg, #fff); box-shadow: 0 8px 24px rgba(30, 42, 70, .05); }
.sectionHead { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 15px; }
.sectionHead h2 { margin: 0; font-size: 18px; }
.sectionHead > span { color: var(--text-secondary, #7a8395); font-size: 13px; }
.userRankChart { width: 100%; height: 520px; border-radius: 12px; background: linear-gradient(180deg, rgba(22,119,255,.035), transparent 45%); }
@media (max-width: 640px) { .userRankChart { height: 430px; } }
.modelLink { height: auto; padding: 0; text-align: left; white-space: normal; }
.rankNo { display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; background: rgba(22, 119, 255, .1); color: #1677ff; font-weight: 700; }
.token { font-variant-numeric: tabular-nums; font-weight: 600; }
.token { color: #08979c; }
small { display: block; margin-top: 3px; color: var(--text-secondary, #7a8395); }
@media (max-width: 900px) {
  .modelFilters, .modelFilters :deep(.ant-picker), .modelInput, .limitSelect { width: 100%; }
  .sectionHead { flex-direction: column; }
}
</style>
