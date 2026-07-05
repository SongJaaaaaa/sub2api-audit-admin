# 04 Sub2API 集成

状态: 已完成

## 1. 目标

接入线上 Sub2API 的只读库和 Admin API，为用户、额度、模型消费和调额提供数据源。

## 2. 范围

- Sub2API PostgreSQL 只读查询。
- Sub2API Admin API client。
- 用户列表和详情。
- `usage_logs` 统计。
- `redeem_codes`、`payment_orders` 来源统计。
- 管理端只读数据源入口。
- 管理端模型消耗统计入口。

不在本模块执行写额度操作。

## 3. 依赖

- 02 后端基础完成。
- 线上 SSH 或内网连接可用。
- Sub2API Admin API 凭据可用。

## 4. 交付物

- `backend/app/Services/Sub2Api/Sub2ApiReadRepository.php`
- `backend/app/Services/Sub2Api/Sub2ApiAdminClient.php`
- `backend/tests/Feature/Sub2ApiClientTest.php`
- `frontend/src/api/sub2api.ts`
- `frontend/src/views/Sub2ApiUsersView.vue`
- `frontend/src/views/Sub2ApiModelStatsView.vue`
- `frontend/src/router/index.ts`

## 5. 进度

| 项 | 状态 | 备注 |
|---|---|---|
| 只读库连接 | 已完成 | 已新增 `Sub2ApiReadRepository`，使用 `DB::connection('sub2api')` |
| Admin API client | 已完成 | 已新增 `Sub2ApiAdminClient`，支持用户、余额历史和后续调额调用 |
| 用户查询 | 已完成 | 支持用户分页搜索和详情，只返回规划确认字段 |
| 模型统计 | 已完成 | 支持 `usage_logs` 汇总和按模型聚合排行 |
| 前端 API 封装 | 已完成 | 已新增 `frontend/src/api/sub2api.ts`，接真实 `/api/v1/sub2api/*` |
| 前端用户数据源页面 | 已完成 | 已新增 `Sub2ApiUsersView.vue`，展示账号、余额、状态和创建时间 |
| 前端模型统计页面 | 已完成 | 已新增 `Sub2ApiModelStatsView.vue`，支持时间段筛选和模型排行 |
| 后端测试 | 已完成 | `Sub2ApiClientTest` 已通过 |
| 前端构建 | 已完成 | `pnpm typecheck`、`pnpm build` 已通过 |

## 6. 验收标准

- 能读取 `users` 数量。
- 能读取 `usage_logs` 聚合。
- 能调用 `/api/v1/admin/users`。
- 不输出敏感字段。
- 管理端菜单可以进入 Sub2API 数据源页面。
- 用户数据源页面可以查看账号、余额、状态和创建时间。
- 模型统计页面支持时间段筛选，不把 `duration_ms` 当在线时长。
- 后端接口完成但前端入口缺失时，本模块不能标记为完成。

## 7. 测试命令

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/backend
php artisan test --filter=Sub2ApiClientTest

cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/frontend
pnpm typecheck
pnpm build
```

## 8. 风险

- Admin API Key 可能轮换。
- 只读库不能暴露公网。
- `duration_ms` 不是在线时长，不能误做在线时长榜。

## 9. 完成记录

| 日期 | 内容 |
|---|---|
| 2026-07-05 | 回补 Sub2API 集成前端入口，新增真实 API 封装、用户数据源页面、模型消耗统计页面和菜单路由 |
| 2026-07-05 | 发现前端业务入口未同步，模块状态调整为后端已完成、前端待补 |
| 2026-07-05 | 完成 Sub2API 只读库仓库、Admin API client、用户查询、模型统计、充值来源统计和 Sub2ApiClientTest |
