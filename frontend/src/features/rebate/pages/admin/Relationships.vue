<script setup lang="ts">
import PageHeader from '../../components/PageHeader.vue'
import RelationshipWorkspace from '../../components/admin/relationships/RelationshipWorkspace.vue'
import { useRelationships } from '../../composables/admin/useRelationships'

const {
  error,
  loading,
  options,
  result,
  selectedId,
  userLoading,
  changePage,
  load,
  queueUserSearch,
  selectUser,
} = useRelationships()
</script>

<template>
  <div class="rebatePage relationshipPage">
    <PageHeader
      title="推荐关系"
      description="可视化查看所选账号的直接上级归因与一级推荐关系。"
    />

    <RelationshipWorkspace
      :selected-id="selectedId"
      :options="options"
      :user-loading="userLoading"
      :loading="loading"
      :error="error"
      :result="result"
      @search="queueUserSearch"
      @select-user="selectUser"
      @load="load"
      @retry="load"
      @page="changePage"
    />
  </div>
</template>
