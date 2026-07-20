# Sub2API 审计管理后台

用于安全调整 Sub2API 用户余额、记录本地审计台账，并按统一口径查看财务、用量、余额、排行和历史余额事件。

## 数据边界

系统明确使用三类数据源，禁止混算：

| 数据源 | 用途 |
|---|---|
| 本地审计 SQLite | 本地现金/赠送、调增/调减、现金入账用户排行、调额记录和操作审计 |
| Sub2API 官方 Admin API | 请求数、四类 Token、标准消费、实际消费、用户排行和 requested 模型统计 |
| Sub2API PostgreSQL 只读连接 | 用户累计充值快照、普通启用用户当前余额、后台调额事件关联和历史余额事件 |

远端 PostgreSQL 只允许使用只读账号；余额修改只通过 Sub2API 官方 Admin API 完成。

## 目录

```text
sub2api-audit-admin/
  backend/             Laravel 12 API、SQLite 审计账本和自动任务
  frontend/            Vue 3、TypeScript、Ant Design Vue、ECharts
  docs/                统计口径、开发检查和部署说明
  deploy/              Caddy 部署示例
  project-management/  架构与模块进度文档
```

## 核心能力

- 中国自然日统计，统一时区 `Asia/Shanghai`，内部使用半开区间。
- 首页三张 KPI：Sub2API 累计充入快照、实际消费、普通启用用户当前余额快照。
- 四类独立排行：实收入账用户、用户实际消费、用户 Token、请求模型 Token。
- requested 模型统计及指定模型下的用户 Token 排行。
- 首次永久锁定切账时间，切账前旧账不进入当前财务统计。
- 调额成功后二次确认余额，并按用户 ID 与完整幂等键关联远端事件。
- 后台调额、余额兑换码、支付订单的只读历史账和 UTF-8 BOM CSV 导出。
- 官方统计不可用时返回 `502 / SUB2API_STATS_UNAVAILABLE`，前端不展示假 0。

## 本地启动

```bash
cd backend
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8010
```

另开终端，并使用 Node `22.22.0`：

```bash
source ~/.nvm/nvm.sh
nvm use 22.22.0
cd frontend
corepack pnpm install
VITE_API_PROXY_TARGET=http://127.0.0.1:8010 corepack pnpm dev --host 127.0.0.1 --port 5174
```

## 关键命令

```bash
# 首次写入切账时间；按中国时间解析，成功后永久拒绝修改
php artisan ledger:cutover --at="YYYY-MM-DD HH:mm:ss"

# 按用户 ID + 完整 idempotency_key 回填远端后台调额事件 ID
php artisan ledger:link-sources
```

## 验证

```bash
cd backend
php artisan test

find app routes database/migrations tests -name '*.php' -print0 \
  | xargs -0 -n1 php -l

source ~/.nvm/nvm.sh
nvm use 22.22.0
cd ../frontend
corepack pnpm build
```

## 文档

- `docs/sub2api-statistics-apis.md`：统计来源、官方接口和字段口径。
- `docs/dev-checklist.md`：本地、异常路径和业务验收清单。
- `docs/deployment.md`：维护切账、迁移、构建和上线验收顺序。
