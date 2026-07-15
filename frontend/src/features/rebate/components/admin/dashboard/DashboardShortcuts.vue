<template>
  <nav class="dashboardShortcuts" aria-label="返利管理快捷入口">
    <strong>快捷入口：</strong>
    <RouterLink to="/rebate/withdrawals">待审提现 <span>→</span></RouterLink>
    <RouterLink to="/rebate/relationships">推荐关系 <span>→</span></RouterLink>
    <RouterLink to="/rebate/config">返利配置 <span>→</span></RouterLink>
  </nav>
</template>

<style scoped>
.dashboardShortcuts {
  display: flex;
  min-height: 48px;
  padding: 12px 16px;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
  border: 1px solid var(--rebate-border);
  border-radius: 8px;
  background: var(--rebate-low);
  font-size: 12px;
}

.dashboardShortcuts strong {
  color: var(--rebate-muted);
}

.dashboardShortcuts a {
  color: var(--rebate-accent);
  font-weight: 600;
  text-decoration: none;
}

.dashboardShortcuts a:hover {
  text-decoration: underline;
}

.dashboardShortcuts span {
  margin-left: 2px;
}
</style>
