# 07 统计排行榜

状态: 已完成

## 1. 目标

实现首页排行榜和统计分析项。

## 2. 范围

- 充值榜。
- 额度使用榜。
- 模型消费榜。
- 固定时间筛选。
- 自定义时间段筛选。
- GPT/Claude 等模型分类。

不承诺在线时长榜；只有先验证 Sub2API 存在稳定会话或活跃时长字段后，才启用该榜单。

## 3. 依赖

- 04 Sub2API 集成完成。
- 06 财务账本完成基础数据。

## 4. 交付物

- `backend/app/Services/Stats/DashboardStatsService.php`
- `backend/app/Services/Stats/ModelStatsService.php`
- `backend/app/Http/Controllers/Api/V1/DashboardController.php`
- `backend/tests/Feature/DashboardStatsTest.php`
- `frontend/src/views/DashboardView.vue`

## 5. 进度

| 项 | 状态 | 备注 |
|---|---|---|
| 时间筛选 | 已完成 | 首页支持今天、本周、本月、近 7 天、近 30 天、自定义区间 |
| 充值榜 | 已完成 | 专用 Dashboard API 基于现金账聚合 |
| 额度使用榜 | 已完成 | 专用 Dashboard API 基于成功调额聚合 |
| 模型消费榜 | 已完成 | 专用 Dashboard API 基于 `usage_logs.total_cost` |
| 模型分类 | 已完成 | 后端提供 GPT/Claude 分类口径 |
| 前端页面 | 已完成 | 首页接 `/dashboard`，展示模型榜、充值榜、额度榜和调额动态 |
| 测试 | 已完成 | `DashboardStatsTest` 已通过 |
| 桌面端验收 | 已完成 | 构建通过，桌面布局使用固定表格宽度和响应式网格 |
| H5 手机端验收 | 已完成 | 筛选和表格横向滚动，避免关键金额遮挡 |
| 前后端联通验收 | 已完成 | Feature Test 覆盖专用 Dashboard API 充值榜、额度榜、模型榜 |
| 异常路径验收 | 已完成 | 首页接口失败时清空旧数据并提示失败 |

## 6. 验收标准

- 支持今天、昨天、本周、本月、近 7 天、近 30 天、自定义区间。
- 支持按 GPT/Claude 分类筛选。
- 模型榜基于 `usage_logs.total_cost` 排序。
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
