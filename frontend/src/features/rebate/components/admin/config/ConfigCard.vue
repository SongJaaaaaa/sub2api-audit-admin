<script setup lang="ts">
defineProps<{
  sectionId: string
  title: string
  description: string
}>()
</script>

<template>
  <section :id="sectionId" class="configCard">
    <header class="configCardHead">
      <div>
        <h2>{{ title }}</h2>
        <p>{{ description }}</p>
      </div>
      <slot name="badge" />
    </header>
    <div class="configCardBody"><slot /></div>
  </section>
</template>

<style scoped>
.configCard {
  min-width: 0;
  scroll-margin-top: 84px;
  border: 1px solid var(--rebate-border);
  border-radius: 16px;
  background: var(--rebate-card);
  box-shadow: 0 1px 3px rgb(15 23 42 / 8%), 0 1px 2px -1px rgb(15 23 42 / 8%);
}

.configCardHead {
  display: flex;
  min-height: 82px;
  padding: 22px 24px 16px;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.configCardHead h2,
.configCardHead p { margin: 0; }
.configCardHead h2 { color: var(--rebate-text); font-size: 18px; line-height: 26px; }
.configCardHead p { margin-top: 2px; color: var(--rebate-muted); font-size: 13px; line-height: 20px; }
.configCardBody { padding: 0 24px 24px; }

.configCardBody :deep(.configFormGrid) {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 16px;
}

.configCardBody :deep(.configFormGrid.singleTail > :last-child:nth-child(odd)) { grid-column: 1 / 2; }
.configCardBody :deep(.ant-form-item) { margin-bottom: 16px; }
.configCardBody :deep(.ant-form-item-label > label) { color: var(--rebate-muted); font-size: 13px; font-weight: 600; }
.configCardBody :deep(.ant-input),
.configCardBody :deep(.ant-input-number),
.configCardBody :deep(.ant-input-group-addon) { min-height: 32px; }

.configCardBody :deep(.configHint) {
  margin-top: 0;
  padding: 16px;
  border-radius: 10px;
  background: #edf5ff;
  color: #174ea6;
  font-size: 13px;
  line-height: 21px;
}

.configCardBody :deep(.configHint strong) { display: block; margin-bottom: 4px; color: #174ea6; }
.configCard :deep(.enabledBadge) {
  padding: 2px 9px;
  border: 1px solid #9bd27d;
  border-radius: 4px;
  color: #0b9f45;
  font-size: 12px;
  white-space: nowrap;
}

@media (max-width: 760px) {
  .configCardHead { min-height: 76px; padding: 18px 16px 12px; }
  .configCardHead h2 { font-size: 17px; }
  .configCardBody { padding: 0 16px 16px; }
  .configCardBody :deep(.configFormGrid) { grid-template-columns: 1fr; }
  .configCardBody :deep(.configFormGrid.singleTail > :last-child:nth-child(odd)) { grid-column: auto; }
}
</style>
