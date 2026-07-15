<template>
  <div class="relationshipLegend">
    <strong>图例：</strong>
    <span><i class="rootDot" />所选账号</span>
    <span><i class="memberDot" />一级直接下级</span>
    <span><i class="lineMark" />直接推荐关系</span>
  </div>
</template>

<style scoped>
.relationshipLegend {
  display: flex;
  min-height: 42px;
  padding: 10px 16px;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
  border: 1px solid var(--rebate-border);
  border-radius: 8px;
  background: var(--rebate-low);
  color: var(--rebate-text);
  font-size: 12px;
}

.relationshipLegend strong {
  color: var(--rebate-muted);
}

.relationshipLegend span {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.relationshipLegend i {
  display: inline-block;
  width: 10px;
  height: 10px;
}

.rootDot {
  border-radius: 50%;
  background: var(--rebate-accent);
}

.memberDot {
  border-radius: 50%;
  background: var(--rebate-success);
}

.relationshipLegend .lineMark {
  width: 16px;
  height: 1px;
  background: #cbd5e1;
}
</style>
