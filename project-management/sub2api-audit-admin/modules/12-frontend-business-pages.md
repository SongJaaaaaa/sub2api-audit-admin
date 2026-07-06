# 12 前端业务页面

状态: 已完成

## 1. 目标

实现管理端核心业务页面。

## 2. 范围

- 首页统计看板、ECharts 图表和模型消费排行。
- Sub2API 用户数据源。
- Sub2API 模型消耗统计。
- 用户与额度。
- 调额表单。
- 额度调整记录。
- 赠送额度记录。
- 经营账。
- 对账中心。
- 异常中心。
- 操作审计。
- 表格列配置。
- H5 手机端页面适配。

## 3. 依赖

- 11 前端基础完成。
- 后端对应 API 完成。

从 04 开始，业务页面不再集中等到最后开发。每个后端模块完成时，都要同步补齐对应前端页面或入口。

## 4. 交付物

- `frontend/src/views/UsersQuotaView.vue`
- `frontend/src/views/Sub2ApiUsersView.vue`
- `frontend/src/views/Sub2ApiModelStatsView.vue`
- `frontend/src/views/LedgerAdjustmentListView.vue`
- `frontend/src/views/GiftQuotaListView.vue`
- `frontend/src/views/OperationExpenseView.vue`
- `frontend/src/views/ReconcileView.vue`
- `frontend/src/views/ExceptionCenterView.vue`
- `frontend/src/views/AuditLogView.vue`
- `frontend/src/components/table/ColumnSettings.vue`
- `frontend/src/components/ledger/AdjustmentForm.vue`

## 5. 进度

| 项 | 状态 | 备注 |
|---|---|---|
| 首页 | 已完成 | 已接 `/dashboard` 专用统计 API，包含充值榜、额度使用榜、模型消费榜、调额动态 |
| Sub2API 用户数据源 | 已完成 | 04 回补，接真实 `/api/v1/sub2api/users` |
| Sub2API 模型消耗统计 | 已完成 | 04 回补，接真实 `/api/v1/sub2api/model-stats` |
| 用户与额度 | 已完成 | 可搜索用户并打开调额弹窗 |
| 调额表单 | 已完成 | `components/ledger/AdjustmentForm.vue`，提交前强确认文案已完成 |
| 记录表格 | 已完成 | 成功记录和异常记录已接 API，额度调整记录支持列配置 |
| 对账页面 | 已完成 | `ReconcileView.vue` 接真实 API |
| 审计页面 | 已完成 | `AuditLogView.vue` 接真实 API |
| 表格列配置 | 已完成 | `components/table/ColumnSettings.vue` |
| 桌面端验收 | 已完成 | Playwright desktop 冒烟通过 |
| H5 手机端验收 | 已完成 | Playwright h5 冒烟通过 |
| 前后端联通验收 | 已完成 | `pnpm e2e` 覆盖登录和核心业务页面 |
| 异常路径验收 | 已完成 | 页面失败提示、调额未确认不显示成功、后端异常测试通过 |

## 6. 验收标准

- 表格支持列选择。
- 调额表单提交前有确认文案。
- 不显示“Sub2API 未确认成功”的成功状态。
- 页面在常见桌面宽度下不拥挤。
- 每个业务页面都要接真实 API 或明确标记为 mock，不能让假数据误导验收。
- 每个业务页面都要按手机端 H5 检查，筛选区、表格、抽屉、弹窗不能遮挡主要操作。
- 表格在手机端允许横向滚动，但默认展示字段必须克制。

## 7. 测试命令

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/frontend
pnpm typecheck
pnpm build
```

## 8. 风险

- 账本字段多，默认列必须克制，扩展字段放列配置。

## 9. 完成记录

| 日期 | 内容 |
|---|---|
| 2026-07-05 | 按 soybean 管理后台风格重做首页看板，新增 ECharts 模型消费榜、请求占比和周期对比，完成桌面/H5布局验收 |
| 2026-07-05 | 回补首页工作台，接入 Sub2API 用户、模型统计、成功调额和异常中心真实 API |
| 2026-07-05 | 回补 04 对应业务页面，完成 Sub2API 用户数据源和模型消耗统计页面，均接真实后端 API |
| 2026-07-06 | 完成 04-10 全部业务页面、列配置组件和调额表单组件；新增 Playwright 桌面/H5 冒烟，`pnpm typecheck`、`pnpm build`、`pnpm e2e` 已通过 |
