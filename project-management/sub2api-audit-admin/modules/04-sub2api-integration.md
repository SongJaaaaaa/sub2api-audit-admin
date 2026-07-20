# 04 Sub2API 集成

状态：已完成

## 1. 目标

以明确的数据边界接入 Sub2API：

- 官方 Admin API 用于余额调整、二次确认和官方统计。
- PostgreSQL 只读连接用于当前余额、远端余额事件和历史账。
- 不从远端数据库重新聚合官方用量，也不直接写远端业务表。

## 2. 数据源边界

| 来源 | 用途 |
|---|---|
| `Sub2ApiAdminClient` | 用户、余额调整、余额确认、Dashboard trend/models/users-ranking/user-breakdown |
| `Sub2ApiReadRepository` | 普通启用用户余额快照、后台调额事件、兑换码、支付订单和关联数据 |
| 本地 SQLite | 审计账本、切账和操作审计 |

Sub2API PostgreSQL 必须使用只读账号。模型和用量统计不得降级为直接聚合远端 `usage_logs`。

## 3. 官方统计接口

```text
GET /api/v1/admin/dashboard/trend
GET /api/v1/admin/dashboard/models
GET /api/v1/admin/dashboard/users-ranking
GET /api/v1/admin/dashboard/user-breakdown
```

统一传入：

```text
start_date=YYYY-MM-DD
end_date=YYYY-MM-DD
timezone=Asia/Shanghai
```

模型维度固定为 `model_source=requested`；用户 Token 排行使用 `sort_by=total_tokens`。

## 4. 失败策略

官方统计网络失败、非成功 HTTP 状态或响应结构不符合已确认形态时：

```text
HTTP 502
code=SUB2API_STATS_UNAVAILABLE
```

- 不返回假 0。
- 不使用 SQL 重新统计作为降级结果。
- 日志只记录接口路径、HTTP 状态、异常类型和安全字段形态。
- 不记录密码、API Key、Authorization 或完整敏感响应。
- 不增加猜测式字段兼容；需要新结构时先记录日志并确认真实响应。

## 5. 环境变量

```env
SUB2API_DB_CONNECTION=sub2api
SUB2API_DB_HOST=
SUB2API_DB_PORT=5432
SUB2API_DB_DATABASE=
SUB2API_DB_USERNAME=sub2api_ro
SUB2API_DB_PASSWORD=
SUB2API_DB_SCHEMA=public
SUB2API_DB_SSLMODE=prefer

SUB2API_ADMIN_API_URL=
SUB2API_ADMIN_API_KEY=
SUB2API_ADMIN_API_TIMEOUT=10
```

## 6. 交付物

- `backend/config/sub2api.php`
- `backend/config/database.php`
- `backend/app/Services/Sub2Api/Sub2ApiAdminClient.php`
- `backend/app/Services/Sub2Api/Sub2ApiReadRepository.php`
- `backend/app/Http/Controllers/Api/V1/Sub2Api/Sub2ApiDataController.php`
- `backend/app/Exceptions/Sub2ApiStatsUnavailableException.php`
- `backend/tests/Feature/Sub2ApiClientTest.php`
- `frontend/src/api/sub2api.ts`
- `frontend/src/views/Sub2ApiUsersView.vue`
- `frontend/src/views/Sub2ApiModelStatsView.vue`

## 7. 验收标准

- 普通用户列表和当前余额可以通过只读数据源获取。
- 官方四个 Dashboard 接口参数和响应映射正确。
- Token 包含输入、输出、缓存创建和缓存读取四类。
- 用户实际消费榜按 `actual_cost`，与用户 Token 榜分开。
- 模型固定 requested 语义，未选模型展示模型统计，选中模型展示该模型下用户 Token 榜。
- 官方统计失败稳定返回 `502 / SUB2API_STATS_UNAVAILABLE`，前端不展示旧数据或假 0。
- 远端数据库账号无写权限，代码中没有远端更新语句。

## 8. 风险

- 官方接口字段发生变化时应先获取安全结构化日志，再按真实结构修改映射，禁止猜测兼容。
- 生产连接信息只能放密钥配置，不得进入仓库或文档。
- 无有效 SSH 登录方式时停止线上连接尝试，由运维确认后再继续。
