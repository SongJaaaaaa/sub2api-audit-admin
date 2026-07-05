<template>
  <section class="page">
    <div class="pageHead">
      <div>
        <h1>首页</h1>
        <p>Sub2API 数据源、模型消耗和后续审计模块入口。</p>
      </div>
    </div>

    <div class="entryGrid">
      <RouterLink class="entry" to="/sub2api/users">
        <strong>Sub2API 用户</strong>
        <span>账号、余额、状态、充值快照</span>
      </RouterLink>
      <RouterLink class="entry" to="/sub2api/models">
        <strong>模型消耗统计</strong>
        <span>usage_logs 汇总和模型排行</span>
      </RouterLink>
    </div>
  </section>
</template>
