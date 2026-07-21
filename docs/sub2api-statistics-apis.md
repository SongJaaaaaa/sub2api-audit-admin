# Sub2API 统计来源与接口口径

## 1. 数据源边界

| 数据源 | 本系统用途 | 禁止事项 |
|---|---|---|
| 本地审计 SQLite | 现金、赠送、调增、调减、净额、充值用户排行、调额记录 | 不得拿远端调额或支付事件补成本地实收入账 |
| Sub2API 官方 Admin API | 用户、余额、远端事件、请求、Token、消费和 requested 模型统计 | 官方失败时不得用数据库 SQL 降级重算 |

模型和用量统计不再由审计系统直接聚合 `usage_logs`，避免产生第三套统计口径。

## 2. 时间口径

- 对外日期参数均为包含首尾日期的 `YYYY-MM-DD`。
- 业务时区固定为 `Asia/Shanghai`。
- 官方 Admin API 参数固定包含：

```text
start_date=YYYY-MM-DD
end_date=YYYY-MM-DD
timezone=Asia/Shanghai
```

- 本地 SQLite 查询使用中国时间半开区间：`[开始日 00:00:00, 结束日次日 00:00:00)`。
- 远端事件由 Admin API 返回，应用按 UTC 半开区间过滤。
- 切账时间只限制账务、历史分类和告警；官方用量始终展示用户选择的完整中国自然日。

## 3. 官方 Admin API

后端通过 `SUB2API_API_URL` 和 `SUB2API_ADMIN_API_KEY` 调用以下接口。

### 3.1 用量趋势

```http
GET /api/v1/admin/dashboard/trend
```

固定参数：

```text
start_date
end_date
timezone=Asia/Shanghai
granularity=day
```

映射字段：

```text
date
requests
input_tokens
output_tokens
cache_creation_tokens
cache_read_tokens
total_tokens
cost
actual_cost
```

首页请求数、Token、标准消费、实际消费及用量趋势均来自该接口。

### 3.2 requested 模型统计

```http
GET /api/v1/admin/dashboard/models
```

固定参数：

```text
start_date
end_date
timezone=Asia/Shanghai
model_source=requested
```

模型榜按 `total_tokens` 降序展示。requested 模型为空时，沿用 Sub2API 官方回退规则：使用实际 `model` 值。

### 3.3 用户实际消费排行

```http
GET /api/v1/admin/dashboard/users-ranking
```

固定传日期、时区和 `limit`。该榜按官方 `actual_cost` 口径展示，不使用 `cost` 或本地调额金额代替。

### 3.4 用户 Token 排行和指定模型用户排行

```http
GET /api/v1/admin/dashboard/user-breakdown
```

固定参数：

```text
start_date
end_date
timezone=Asia/Shanghai
model_source=requested
sort_by=total_tokens
limit
```

指定模型时额外传：

```text
model=<请求模型>
```

首页用户 Token 榜与模型页指定模型用户榜均使用该接口；不得与用户实际消费榜混为同一榜单。

## 4. 指标定义

### 4.1 Token

总 Token 完全沿用官方字段，并应等于：

```text
input_tokens
+ output_tokens
+ cache_creation_tokens
+ cache_read_tokens
```

不同官方接口可能把两类缓存 Token 合并为 `cache_tokens` 返回；审计系统只做字段映射，不用自定义 SQL重新计算官方排行。

### 4.2 消费

- `standard_cost` 映射官方 `cost`。
- `actual_cost` 映射官方 `actual_cost`。
- 用户消费排行只按 `actual_cost`。
- 模型页同时展示标准消费和实际消费，但默认排序仍为 Token。

### 4.3 本地财务

- `cash_total`：切账后范围内，本系统成功调增单的 `cash_amount`。
- `gift_total`：同范围成功调增单的赠送额度。
- `adjustment_in_total`：成功调增金额合计。
- `adjustment_out_total`：成功调减金额绝对值合计。
- `adjustment_net_total`：调增减去调减。
- 实收入账用户榜只按 `cash_amount`，不混入赠送、总调额或外部事件。

## 5. 审计系统接口

### 首页

```http
GET /api/v1/dashboard?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&limit=10
```

- 日期均缺省时默认今天。
- 只传一个日期、日期逆序或非法日期返回 `422`。
- `limit` 默认 10，最大 100。
- 响应分为 `finance`、`usage`、`balance`、`rankings`、`recent_adjustments` 和 `alerts`。

### 模型统计

```http
GET /api/v1/sub2api/model-stats?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&limit=20
GET /api/v1/sub2api/model-stats?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD&model=<模型>&limit=20
```

- 未指定模型：返回 requested 模型统计。
- 指定模型：返回该请求模型下的用户 Token 排行。
- 页面不混入充值、赠送或账务汇总。

## 6. 官方统计失败策略

以下情况统一返回：

```text
HTTP 502
code=SUB2API_STATS_UNAVAILABLE
```

包括：

- Admin API 网络或超时失败。
- Admin API 返回非成功 HTTP 状态。
- 响应顶层或数据字段形态不符合已确认结构。
- 必需行字段缺失。

前端必须显示“Sub2API 官方统计暂不可用”，清空旧统计和图表，不得把失败显示为 0。后端只记录路径、HTTP 状态、异常类型、实际字段键等安全结构化信息，不记录密码、API Key、Authorization 或完整敏感响应。
