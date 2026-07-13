# 07 统计排行榜

状态：已完成

## 1. 目标

按照三类数据源边界重构首页与模型统计，避免账务、用量和远端事件相互混算。

## 2. 日期口径

- 支持今天、本周、本月、近 7 天、近 30 天和自定义日期。
- 对外 `start_date`、`end_date` 均包含首尾日期。
- 本地 SQLite 使用中国时间半开区间。
- 远端 PostgreSQL 使用对应 UTC 半开区间。
- 官方用量按完整中国自然日请求，不受切账时间截断。
- 只传一个日期、日期非法或逆序时返回 `422`。

## 3. 首页接口

```http
GET /api/v1/dashboard?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&limit=10
```

核心响应：

```text
range
cutover_at
finance
usage
balance
rankings
recent_adjustments
alerts
```

### 3.1 本地财务

- `cash_total`：切账后范围内成功调增单的 `cash_amount`。
- `gift_total`：同范围成功调增单的赠送额度。
- `adjustment_in_total`：成功调增合计。
- `adjustment_out_total`：成功调减绝对值合计。
- `adjustment_net_total`：有符号净额。
- 财务趋势按日补齐现金、赠送、调增、调减和净额。

实收入账用户榜只按 `cash_amount` 排序，不混入赠送、总调额或远端外部事件。

### 3.2 官方用量

- 请求数、Token、标准消费、实际消费和日趋势只映射官方 Admin API。
- Token 为输入、输出、缓存创建和缓存读取四类之和。
- 用户实际消费榜按 `actual_cost`。
- 用户 Token 榜独立展示，不用金额榜冒充。
- 请求模型榜固定 requested 语义，默认按 Token 排序。

官方统计失败返回：

```text
HTTP 502
code=SUB2API_STATS_UNAVAILABLE
```

前端显示“Sub2API 官方统计暂不可用”，并销毁旧图表，不展示旧数据或假 0。

### 3.3 当前余额

当前余额快照只统计：

```text
role=user
status=active
deleted_at IS NULL
```

该数据不受首页日期变化影响，页面明确标注“当前快照”。

### 3.4 告警

告警汇总包括：

- 切账后本地成功但未关联远端 source ID 的调额。
- 已生成对账差异。
- 远端外部后台调额。
- 带审计标记但找不到本地记录的审计孤儿。

同一调整按本地调整 ID 去重。

## 4. 模型统计接口

```http
GET /api/v1/sub2api/model-stats
```

- 未选择模型：调用官方 `/dashboard/models`，固定 requested 维度，返回请求数、Token、标准消费和实际消费，默认按 Token 排序。
- 选择模型：调用 `/dashboard/user-breakdown`，传 `model_source=requested`、`model=<模型>`、`sort_by=total_tokens`，返回该请求模型下的用户 Token 排行。
- 模型页面不混入充值来源或账务汇总。

## 5. 首页页面结构

1. 实收入账 KPI：主值为未软删除 Sub2API 用户的 `total_recharged` 累计合计，并标注当前快照、不随日期变化。
2. 实际消费 KPI：主值 `actual_cost`，次值 Token 和请求数。
3. 普通启用用户当前余额 KPI：主值余额，次值用户数，并标注当前快照。
4. 财务趋势：现金、赠送、调增、调减。
5. 消费与 Token 趋势：实际消费、Token、请求数使用适合的轴和格式。
6. 四类独立排行：本地现金入账用户、用户实际消费、用户 Token、请求模型 Token。
7. 最近成功、失败和作废调额。
8. 对账告警入口。

## 6. 交付物

- `backend/app/Support/ChinaDateRange.php`
- `backend/app/Support/ChinaTime.php`
- `backend/app/Services/Stats/DashboardStatsService.php`
- `backend/app/Services/Stats/ModelStatsService.php`
- `backend/app/Http/Controllers/Api/V1/DashboardController.php`
- `backend/tests/Feature/DashboardStatsTest.php`
- `backend/tests/Feature/ModelStatsTest.php`
- `backend/tests/Unit/ChinaDateRangeTest.php`
- `frontend/src/api/dashboard.ts`
- `frontend/src/views/DashboardView.vue`
- `frontend/src/views/Sub2ApiModelStatsView.vue`

## 7. 验收标准

- 日期预设无多算一天，跨月和日末边界正确。
- 切账前本地账不进入当前财务、告警和对账。
- 旧结构 `external_total`、`recharge_total`、额度使用榜和按 `total_cost` 排序的模型消费榜不再作为当前口径。
- 用户实际消费榜、用户 Token 榜和模型 Token 榜相互独立。
- 官方失败时前端明确不可用，不显示 0。
- 桌面端和 H5 的筛选、趋势、排行和告警入口可用。

## 8. 测试

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/backend
php artisan test --filter=DashboardStatsTest
php artisan test --filter=ModelStatsTest
php artisan test --filter=ChinaDateRangeTest
```
