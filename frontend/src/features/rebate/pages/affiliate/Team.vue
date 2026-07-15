<script setup lang="ts">
import { onMounted } from 'vue'
import AsyncState from '../../components/AsyncState.vue'
import PageHeader from '../../components/PageHeader.vue'
import TeamCanvas from '../../components/affiliate/team/TeamCanvas.vue'
import TeamMetricGrid from '../../components/affiliate/team/TeamMetricGrid.vue'
import { useAffiliateTeam } from '../../composables/affiliate/useAffiliateTeam'

const team = useAffiliateTeam()
onMounted(team.load)
</script>

<template>
  <div class="rebatePage">
    <PageHeader title="我的推荐关系" description="查看你邀请的一级下级关系。上级信息不可查看。" />

    <AsyncState :loading="team.loading.value" :error="team.error.value" @retry="team.load">
      <TeamMetricGrid :direct-count="team.page.total" :team-count="team.page.total" :visible-count="team.items.value.length" />
      <TeamCanvas
        :root-name="team.rootName.value"
        :root-meta="team.rootMeta.value"
        :page-recharge="team.pageRecharge.value"
        :items="team.items.value"
        :page="team.page"
        @page="team.changePage"
      />
    </AsyncState>
  </div>
</template>
