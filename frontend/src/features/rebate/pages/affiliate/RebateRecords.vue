<script setup lang="ts">
import { onMounted } from 'vue'
import AsyncState from '../../components/AsyncState.vue'
import MetricGrid from '../../components/MetricGrid.vue'
import PageHeader from '../../components/PageHeader.vue'
import RebateRecordFilters from '../../components/affiliate/rebates/RebateRecordFilters.vue'
import RebateRecordsTable from '../../components/affiliate/rebates/RebateRecordsTable.vue'
import { useAffiliateRebateRecords } from '../../composables/affiliate/useAffiliateRebateRecords'

const records = useAffiliateRebateRecords()
onMounted(records.load)
</script>

<template>
  <div class="rebatePage">
    <PageHeader title="返利明细" description="查看一级返利来源、状态和确认记录。" />

    <AsyncState :loading="records.loading.value" :error="records.error.value" @retry="records.load">
      <MetricGrid :items="records.metrics.value" />
      <RebateRecordsTable
        :items="records.items.value"
        :current="records.page.current"
        :page-size="records.page.pageSize"
        :total="records.page.total"
        @change="records.tableChange"
      >
        <template #filters>
          <RebateRecordFilters v-model="records.type.value" :total="records.page.total" @change="records.search" />
        </template>
      </RebateRecordsTable>
    </AsyncState>
  </div>
</template>
