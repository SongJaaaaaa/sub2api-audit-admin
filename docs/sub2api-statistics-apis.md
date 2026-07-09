# Sub2API 统计接口清单

来源：`https://api.sjiaa.cc.cd/admin/usage` 当前前端包。

说明：

- 前端 axios 默认 `baseURL` 是 `/api/v1`，所以下表接口实际请求路径需要加 `/api/v1` 前缀。
- 例如：`GET /admin/usage/stats` 实际是 `GET /api/v1/admin/usage/stats`。
- 后台接口需要登录态 / Authorization。

## Admin Usage 页面主接口

| 模块 | 方法 | 接口 | 主要用途 | 常见参数 | 主要统计/返回内容 |
|---|---:|---|---|---|---|
| 用量明细 | GET | `/admin/usage` | 查询后台用量日志列表 | `page`, `page_size`, `user_id`, `api_key_id`, `account_id`, `model`, `group_id`, `request_type`, `stream`, `billing_type`, `billing_mode`, `start_date`, `end_date`, `sort_by`, `sort_order`, `exact_total` | 用量日志明细、分页总数 |
| 用量汇总 | GET | `/admin/usage/stats` | 查询当前筛选范围的总览统计 | 同 `/admin/usage` 的筛选条件 | 总请求数、总 Token、输入/输出 Token、缓存 Token、总费用、实际费用、账号成本、平均耗时 |
| 用户搜索 | GET | `/admin/usage/search-users` | 用量页用户筛选下拉搜索 | `q` | 用户 ID、邮箱、删除状态 |
| API Key 搜索 | GET | `/admin/usage/search-api-keys` | 用量页 API Key 筛选下拉搜索 | `user_id`, `q` | API Key ID、名称、归属用户 |
| 清理任务列表 | GET | `/admin/usage/cleanup-tasks` | 查看用量清理任务 | `page`, `page_size` | 任务状态、清理范围、删除行数、错误信息、创建时间 |
| 创建清理任务 | POST | `/admin/usage/cleanup-tasks` | 提交用量清理任务 | `start_date`, `end_date`, `timezone` | 任务创建结果 |
| 取消清理任务 | POST | `/admin/usage/cleanup-tasks/{id}/cancel` | 取消 pending/running 的清理任务 | `id` | 取消结果 |

## Admin Dashboard 统计接口

这些接口会被 `/admin/usage` 页里的趋势图、模型/分组/端点分布、用户消费排行等组件复用。

| 模块 | 方法 | 接口 | 主要用途 | 常见参数 | 主要统计/返回内容 |
|---|---:|---|---|---|---|
| 总览 | GET | `/admin/dashboard/stats` | 后台 Dashboard 总览 | 无或时间参数 | 请求、用户、Token、费用等汇总 |
| 实时指标 | GET | `/admin/dashboard/realtime` | 实时统计 | 无 | 实时请求/流量/状态指标 |
| 用量趋势 | GET | `/admin/dashboard/trend` | 趋势图 | `start_date`, `end_date`, `granularity`, 筛选条件 | 按日/小时的请求、Token、费用趋势 |
| 快照 V2 | GET | `/admin/dashboard/snapshot-v2` | 一次性获取统计快照 | `start_date`, `end_date`, `granularity`, `user_id`, `api_key_id`, `model`, `group_id`, `request_type`, `billing_type` 等 | 汇总卡片、趋势、模型分布、分组分布、端点分布 |
| 模型统计 | GET | `/admin/dashboard/models` | 模型分布/排行 | `start_date`, `end_date`, `model_source`, 其他筛选 | 请求模型、上游模型、映射模型的请求数、Token、费用、账号成本 |
| 分组统计 | GET | `/admin/dashboard/groups` | 分组分布 | `start_date`, `end_date`, 其他筛选 | 分组请求数、Token、实际费用、标准费用、账号成本 |
| 用户拆分 | GET | `/admin/dashboard/user-breakdown` | 图表行展开后的用户明细 | `start_date`, `end_date`, `model`, `model_source`, `group_id`, `endpoint`, `endpoint_type` 等 | 某模型/分组/端点下的用户请求、Token、费用 |
| 用户消费排行 | GET | `/admin/dashboard/users-ranking` | 用户消费排行 | `start_date`, `end_date`, 筛选条件 | 用户、请求数、Token、实际消费 |
| 用户趋势 | GET | `/admin/dashboard/users-trend` | 用户维度趋势 | `start_date`, `end_date`, `user_id` 等 | 用户请求/Token/费用趋势 |
| API Key 趋势 | GET | `/admin/dashboard/api-keys-trend` | API Key 维度趋势 | `start_date`, `end_date`, `api_key_id` 等 | API Key 请求/Token/费用趋势 |
| 批量用户用量 | POST | `/admin/dashboard/users-usage` | 批量查询用户用量 | `user_ids` | 多用户用量统计 |
| 批量 API Key 用量 | POST | `/admin/dashboard/api-keys-usage` | 批量查询 API Key 用量 | `api_key_ids` | 多 API Key 用量统计 |

## Usage Dashboard 普通用量接口

这些是普通用量页/用户侧用量统计接口，同样属于 Sub2API 用量统计能力。

| 模块 | 方法 | 接口 | 主要用途 | 常见参数 | 主要统计/返回内容 |
|---|---:|---|---|---|---|
| 用户用量汇总 | GET | `/usage/stats` | 查询当前用户或指定 API Key 的用量汇总 | `period` 或 `start_date`, `end_date`, `api_key_id` | 请求数、Token、费用、耗时 |
| 用户 Dashboard 总览 | GET | `/usage/dashboard/stats` | 用户侧 Dashboard 汇总 | 无或时间参数 | 用户侧总览统计 |
| 用户趋势 | GET | `/usage/dashboard/trend` | 用户侧趋势图 | `start_date`, `end_date`, `granularity`, `api_key_id` | 请求/Token/费用趋势 |
| 用户模型统计 | GET | `/usage/dashboard/models` | 用户侧模型分布 | `start_date`, `end_date`, `api_key_id` | 模型请求、Token、费用 |
| 用户快照 V2 | GET | `/usage/dashboard/snapshot-v2` | 用户侧统计快照 | `start_date`, `end_date`, `granularity`, `api_key_id` | 汇总、趋势、分布 |
| 批量 API Key 用量 | POST | `/usage/dashboard/api-keys-usage` | 用户侧批量 API Key 用量 | `api_key_ids` | 多 Key 请求、Token、费用 |
| 用户错误列表 | GET | `/usage/errors` | 查询当前用户错误请求 | `page`, `page_size`, 筛选条件 | 错误请求列表、分页 |
| 用户错误详情 | GET | `/usage/errors/{id}` | 查询错误详情 | `id` | 错误详情 |

## Ops / 错误统计接口

`/admin/usage` 的错误 Tab 使用其中一部分；Ops 页面也复用这些统计接口。

| 模块 | 方法 | 接口 | 主要用途 | 常见参数 | 主要统计/返回内容 |
|---|---:|---|---|---|---|
| Ops 总览 | GET | `/admin/ops/dashboard/overview` | 运维总览 | 时间范围、平台、分组 | 成功数、错误数、限流数、Token、延迟 |
| Ops 快照 V2 | GET | `/admin/ops/dashboard/snapshot-v2` | 运维统计快照 | 时间范围、平台、分组 | 吞吐、错误、延迟、Token、账号状态等 |
| 吞吐趋势 | GET | `/admin/ops/dashboard/throughput-trend` | QPS/TPS/请求趋势 | 时间范围、平台、分组 | 吞吐趋势 |
| 延迟直方图 | GET | `/admin/ops/dashboard/latency-histogram` | 延迟分布 | 时间范围、平台、分组 | P50/P90/P95/P99、直方图 |
| 错误趋势 | GET | `/admin/ops/dashboard/error-trend` | 错误趋势 | 时间范围、平台、分组 | 错误数、429/529、业务限流趋势 |
| 错误分布 | GET | `/admin/ops/dashboard/error-distribution` | 错误分类分布 | 时间范围、平台、分组 | 错误类型/分类占比 |
| OpenAI Token 统计 | GET | `/admin/ops/dashboard/openai-token-stats` | OpenAI Token 统计 | 时间范围、平台、分组 | Token 消耗统计 |
| 并发统计 | GET | `/admin/ops/concurrency` | 系统/平台并发 | `platform`, `group_id` | 并发、队列等 |
| 用户并发统计 | GET | `/admin/ops/user-concurrency` | 用户并发 | 无或筛选条件 | 用户并发统计 |
| 账号可用性 | GET | `/admin/ops/account-availability` | 账号池可用性 | `platform`, `group_id` | 可用/异常/停用账号数量 |
| 实时流量摘要 | GET | `/admin/ops/realtime-traffic` | 实时流量 | 时间窗口参数 | 实时请求、Token、错误、吞吐 |
| 错误日志列表 | GET | `/admin/ops/errors` | 后台错误日志列表 | `page`, `page_size`, `type`, `category`, `status_code`, `sort_by`, `sort_order` | 错误明细、分页 |
| 错误详情 | GET | `/admin/ops/errors/{id}` | 错误日志详情 | `id` | 请求 ID、用户/账号、模型、端点、状态、消息 |
| 请求错误列表 | GET | `/admin/ops/request-errors` | 请求错误列表 | 分页和筛选 | 请求侧错误 |
| 请求错误详情 | GET | `/admin/ops/request-errors/{id}` | 请求错误详情 | `id` | 请求错误详情 |
| 关联上游错误 | GET | `/admin/ops/request-errors/{id}/upstream-errors` | 查看请求关联的上游错误 | `id` | 上游错误列表 |
| 上游错误列表 | GET | `/admin/ops/upstream-errors` | 上游错误列表 | 分页和筛选 | 上游错误 |
| 上游错误详情 | GET | `/admin/ops/upstream-errors/{id}` | 上游错误详情 | `id` | 上游错误详情 |
| QPS WebSocket | WS | `/api/v1/admin/ops/ws/qps` | 实时 QPS 推送 | 登录态 | 实时 QPS 数据流 |

## 页面表格字段参考

| 表格 | 字段 |
|---|---|
| 用量明细表 | 用户、API Key、账号、模型、Reasoning Effort、端点、分组、类型、计费模式、Token、费用、首 Token、耗时、时间、User Agent、IP 地址 |
| 用量 Excel 导出 | 时间、用户、API Key、账号、模型、上游模型、Reasoning Effort、分组、入站端点、上游端点、类型、输入 Token、输出 Token、缓存读取 Token、缓存创建 Token、输入成本、输出成本、缓存读取成本、缓存创建成本、用户倍率、账号倍率、原始成本、用户计费、账号计费、首 Token、耗时、Request ID、User Agent、IP 地址 |
| 错误列表 | 用户、API Key、账号、平台、模型、端点、分组、类型、分类、状态码、错误消息、时间、User Agent、IP、操作 |
| 模型分布表 | 模型、请求数、Token、Actual、账号成本、Standard |
| 分组分布表 | 分组、请求数、Token、Actual、账号成本、Standard |
| 端点分布表 | 端点、请求数、Token、Actual、Standard |

