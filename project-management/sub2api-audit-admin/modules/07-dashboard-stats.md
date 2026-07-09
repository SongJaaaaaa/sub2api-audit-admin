# 07 统计排行榜

状态: 已完成

## 1. 目标

实现首页排行榜和统计分析项。

## 2. 范围

- 入账榜（实收现金，仅本系统账本行，按 `cash_amount` 排序）。
- 额度使用榜。
- 模型消费榜。
- 固定时间筛选。
- 自定义时间段筛选。
- GPT/Claude 等模型分类（前端标签展示）。

**充值口径说明（2026-07-09 确认）：**
- `cash_total`：实收现金，来自本系统账本 `sum(cash_amount)`，increment、succeeded。
- `gift_total`：赠送额度，来自本系统账本 `sum(gift_quota_amount)`，increment、succeeded。
- `external_total`：外部调整，不经本系统直接在 sub2api 后台操作的，取 `redeem_codes.admin_balance`（排除已有本系统 ledger_no 的），无法区分现金与赠送，仅作汇总展示，不进入排行榜。
- `recharge_total`：到账总额 = cash + gift + external，用于趋势图聚合，不再单独作为首页主指标。
- 首页 KPI 共 3 张卡片：充值金额 / 总消费 / Sub2API 总额度；赠送额度不在首页单独展示。

不承诺在线时长榜；只有先验证 Sub2API 存在稳定会话或活跃时长字段后，才启用该榜单。

## 3. 依赖

- 04 Sub2API 集成完成。
- 06 财务账本完成基础数据。

## 4. 交付物

- `backend/app/Services/Stats/DashboardStatsService.php`
- `backend/app/Http/Controllers/Api/V1/DashboardController.php`
- `backend/tests/Feature/DashboardStatsTest.php`
- `frontend/src/api/dashboard.ts`
- `frontend/src/views/DashboardView.vue`

注：`ModelStatsService.php` 未独立交付，模型统计能力已内聚在 `Sub2ApiReadRepository` 和 `Sub2ApiDataController`。

## 5. 进度

| 项 | 状态 | 备注 |
|---|---|---|
| 时间筛选 | 已完成 | 首页支持今天、本周、本月、近 7 天、近 30 天、自定义区间 |
| 入账榜（原充值榜） | 已完成 | 按 `cash_amount` 排序，仅含本系统账本行，每行展示实收/调额总额 |
| 赠送额度汇总 | 已完成 | 后端保留 `gift_total`，首页不单独展示 |
| 外部调整汇总 | 已完成 | `external_total`，不进排行，仅展示总量 |
| 额度使用榜 | 已完成 | 专用 Dashboard API 基于成功调额聚合 |
| 模型消费榜 | 已完成 | 专用 Dashboard API 基于 `usage_logs.total_cost` |
| 模型分类 | 已完成 | 前端 `modelTag()` 按模型名称正则归类 GPT/Claude/Gemini/其他 |
| 前端页面 | 已完成 | 首页 3 张 KPI 卡片：充值金额/总消费/Sub2API 总额度 |
| 测试 | 已完成 | `DashboardStatsTest` 已更新并通过 |
| 桌面端验收 | 已完成 | 构建通过，3 卡片布局 |
| H5 手机端验收 | 已完成 | 筛选和表格横向滚动，避免关键金额遮挡 |
| 前后端联通验收 | 已完成 | Feature Test 覆盖 cash_total/gift_total/external_total/入账榜/额度榜 |
| 异常路径验收 | 已完成 | 首页接口失败时清空旧数据并提示失败 |

## 6. 验收标准

- 支持今天、本周、本月、近 7 天、近 30 天、自定义区间。
- 首页展示 3 张 KPI 卡片：充值金额 / 总消费 / Sub2API 总额度。
- 充值榜展示充值金额，每行展示用户和笔数。
- 外部调整（不经本系统）不进入入账榜，通过 `external_total` 单独呈现。
- 模型榜基于 `usage_logs.total_cost` 排序（在 `/sub2api/model-stats` 页面）。
- 桌面端首页排行榜和时间筛选可正常使用。
- H5 手机端排行榜卡片和筛选区不遮挡。
- 前端必须接真实统计 API。
- 统计接口失败时，页面必须提示失败，不能展示旧数据当作成功。

## 7. 测试命令

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/backend
php artisan test --filter=DashboardStatsTest
```

## 8. 风险

- `usage_logs.duration_ms` 是请求耗时，不是在线时长。
- 首页第一版复用 04 的 `/sub2api/model-stats`，只能支撑模型消费相关看板；充值榜和额度使用榜必须等 06/07 后端聚合接口完成后才能补齐。
- 本地验收发现后端未连到含 `usage_logs` 的 Sub2API 数据库时会报 `no such table: usage_logs`，正式联调前必须确认只读数据源指向线上 Sub2API。

## 9. 完成记录

- 2026-07-06: 完成 `/dashboard` 专用统计 API、`DashboardStatsService` 和 `DashboardStatsTest`；首页切换到专用 API，并补齐充值榜、额度使用榜和模型分类；`php artisan test` 和 `pnpm build` 已通过。
- 2026-07-09: 按充值展示口径调整首页：主指标使用 `recharge_total`，去掉赠送额度卡片和首页趋势中的赠送额度；榜单恢复为充值榜；用户额度页改为用户充值页并去掉顶部本页余额；`npm run build` 已通过。
