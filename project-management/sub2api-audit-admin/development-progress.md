# Sub2API 审计管理后台开发进度

## 1. 当前结论

统计、切账和历史账重构已落地到本地工作区，当前阶段是：

```text
代码实现和本地自动化验证完成 -> 生产切账与真实小额调额验收
```

生产环境仍必须先确认有效 SSH 登录方式、只读数据库连接和官方 Admin API 配置，不能用猜测凭据继续连接。

## 2. 阶段状态

| 阶段 | 状态 | 说明 |
|---|---|---|
| S0 立项与资料整理 | 已完成 | 架构、计划和模块文档已建立 |
| S1 新项目初始化 | 已完成 | Laravel 12、Vue 3、文档和部署目录已建立 |
| S2 后端基础 | 已完成 | SQLite 主库、Sub2API 只读连接、健康检查和鉴权 |
| S3 Sub2API 集成 | 已完成 | 官方 Admin API、只读仓库、用户和余额历史 |
| S4 账本和调额 | 已完成 | 调额、二次确认、切账、source ID 关联和财务账 |
| S5 统计与排行榜 | 已完成 | 本地财务、官方用量、当前余额和四类独立排行 |
| S6 历史账与审计 | 已完成 | 历史事件、CSV 和操作审计 |
| S7 前端管理后台 | 已完成 | 首页、模型、历史账、审计和 H5 页面 |
| S8 自动化验证 | 已完成 | 后端 79 passed / 689 assertions / 1 skipped，PHP 语法、Node 22.22.0 构建和 diff 检查通过 |
| S9 生产上线 | 待执行 | 维护、备份、首次切账、回填、Scheduler 和真实调额验收 |

## 3. 模块进度索引

| 模块 | 进度文档 | 状态 |
|---|---|---|
| 项目基础 | `modules/01-project-foundation.md` | 已完成 |
| 后端基础 | `modules/02-backend-foundation.md` | 已完成 |
| 管理员认证 | `modules/03-admin-auth.md` | 已完成 |
| Sub2API 集成 | `modules/04-sub2api-integration.md` | 已完成 |
| 强一致调额 | `modules/05-ledger-adjustment.md` | 已完成 |
| 财务账本 | `modules/06-finance-ledger.md` | 已完成 |
| 统计排行榜 | `modules/07-dashboard-stats.md` | 已完成 |
| 附件和富文本 | `modules/08-attachments-richtext.md` | 已完成 |
| 操作审计 | `modules/10-audit-log.md` | 已完成 |
| 前端基础 | `modules/11-frontend-foundation.md` | 已完成 |
| 前端业务页面 | `modules/12-frontend-business-pages.md` | 已完成 |
| 联调验收与部署 | `modules/13-verification-deployment.md` | 进行中，待生产验收 |

## 4. 本次统计与台账重构

| 项目 | 状态 | 当前口径 |
|---|---|---|
| 中国日期范围 | 已完成 | 自然日包含首尾，内部统一半开区间；SQLite 中国时间、PostgreSQL UTC |
| 切账设置 | 已完成 | `ledger_cutover_at` 首次写入后永久锁定 |
| 本地财务统计 | 已完成 | 实收、赠送、调增、调减、净额独立 |
| 官方用量 | 已完成 | Admin API trend，失败返回 `502 / SUB2API_STATS_UNAVAILABLE` |
| 用户排行 | 已完成 | 实收入账、实际消费、Token 三类分开 |
| 模型统计 | 已完成 | requested 语义，默认按 Token；指定模型展示用户 Token 榜 |
| 当前余额 | 已完成 | 普通、启用、未删除用户快照，不随日期变化 |
| 远端事件关联 | 已完成 | source ID 优先，旧记录仅用用户 ID + 完整幂等键 |
| 历史账 | 已完成 | 三类远端余额事件只读列表、筛选、分页和 CSV |

## 5. 已完成验证

本地全量自动化验证已通过：

```text
后端全量测试  79 passed / 689 assertions / 1 skipped
PHP 语法检查  通过
Node 版本      22.22.0
前端生产构建  通过
git diff --check 通过
```

覆盖全部后端 Unit / Feature 测试，包括日期边界、Dashboard、历史账、切账/source link、官方统计、模型统计和调额。前端已使用 Node `22.22.0` 执行 `vue-tsc -b && vite build`；仅有 Vite 大 chunk 提示，不影响构建结果。

本轮最终 diff、中文 UTF-8 和敏感信息复核已通过；正式部署前如代码再次变化需重新检查，并在生产环境完成官方统计基准、首次切账和真实小额调额验收。

## 6. 上线验收重点

- 维护开始时记录精确中国时间，备份本地 SQLite 后首次执行 `ledger:cutover`。
- 使用 `ledger:link-sources` 按完整幂等键回填，不按单号关联。
- 对 `2026-07-01` 至 `2026-07-09` 的请求数、Token 和 `actual_cost` 与官方 Admin API 对齐。
- 旧账保留但不进入切账后当前财务和告警。
- 分别执行一笔小额调增和调减，验证余额、source ID、首页和历史账。

## 7. 下一步

1. 由运维确认生产 SSH 登录方式、只读数据库和 Admin API 配置。
2. 按 `docs/deployment.md` 执行维护、备份、切账、回填、Scheduler 和真实小额调额验收。
3. 若部署前代码发生变化，重新执行全量测试、构建、diff、UTF-8 和敏感信息检查。
