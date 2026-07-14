# 部署说明

## 1. 部署边界

本系统有三类数据源，生产部署时必须保持职责分离：

| 数据源 | 用途 | 写入约束 |
|---|---|---|
| 本地审计 SQLite | 调额账本、现金/赠送、经营账、附件索引、审计日志、切账设置、对账批次和差异 | 由本系统正常读写 |
| Sub2API 官方 Admin API | 调整用户余额、二次确认、请求数、Token、标准消费、实际消费、用户排行和 requested 模型统计 | 仅通过官方 API 调额，不得用 SQL 改余额 |
| Sub2API PostgreSQL | 当前普通用户余额、远端调额事件关联、历史余额事件和真实对账 | 只读账号，禁止写入远端业务表 |

建议与 Sub2API 部署在同一服务器或可信内网。远端 PostgreSQL 不得暴露公网；跨服务器时优先使用内网或 SSH 隧道。

## 2. 目录建议

```text
/var/www/sub2api-audit-admin
  backend
  frontend/dist
  backend/database/database.sqlite
  backend/storage/app/private
```

以下内容必须持久化并纳入备份：

- `backend/database/database.sqlite`
- `backend/storage/app/private`
- 生产 `.env` 和外部密钥管理配置

附件使用 Laravel `local` 私有盘，不要把私有附件目录软链到 `public`。

## 3. 后端环境变量

### 3.1 基础配置和本地审计库

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
APP_KEY=
APP_LOCALE=zh_CN
APP_FALLBACK_LOCALE=zh_CN
APP_TIMEZONE=Asia/Shanghai

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/sub2api-audit-admin/backend/database/database.sqlite

FILESYSTEM_DISK=local
```

`DB_DATABASE` 必须改成生产服务器上的绝对路径。部署前先创建数据库文件并确保 PHP 运行用户可读写：

```bash
cd /var/www/sub2api-audit-admin/backend
install -m 660 -o www-data -g www-data /dev/null database/database.sqlite
```

运行用户不是 `www-data` 时，按实际用户和用户组调整。

### 3.2 Sub2API 只读数据库

```env
SUB2API_DB_CONNECTION=sub2api
SUB2API_DB_HOST=127.0.0.1
SUB2API_DB_PORT=5432
SUB2API_DB_DATABASE=sub2api
SUB2API_DB_USERNAME=sub2api_ro
SUB2API_DB_PASSWORD=
SUB2API_DB_SCHEMA=public
SUB2API_DB_SSLMODE=prefer
```

该账号必须只有读取所需表的权限，至少需要读取 `users`、`usage_logs`、`redeem_codes` 和 `payment_orders`。本系统不得直接更新 Sub2API 用户余额、兑换码、支付订单或其他远端业务表。

### 3.3 Sub2API 官方 Admin API

```env
SUB2API_ADMIN_API_URL=https://sub2api.example.com
SUB2API_ADMIN_API_KEY=
SUB2API_ADMIN_API_TIMEOUT=10
```

变量名必须是 `SUB2API_ADMIN_API_URL`，不要使用旧的 `SUB2API_ADMIN_BASE_URL`。

密码、数据库口令、API Key 和 Authorization 不得写入仓库、部署文档、命令历史、应用日志或验收截图。

## 4. 前端环境变量与构建版本

```env
VITE_API_BASE_URL=https://api.example.com/api/v1
```

生产构建固定使用 Node `22.22.0`：

```bash
source ~/.nvm/nvm.sh
nvm use 22.22.0
cd /var/www/sub2api-audit-admin/frontend
corepack pnpm install --frozen-lockfile
corepack pnpm build
```

## 5. 首次上线顺序

首次启用新统计、切账和对账能力时，严格按以下顺序执行。

### 5.1 进入维护并备份

1. 记录进入维护模式的**精确中国时间**，格式为 `YYYY-MM-DD HH:mm:ss`。
2. 停止本审计系统发起新的用户调额，确认没有进行中的调额请求。
3. 备份本地 SQLite 和私有附件：

```bash
cd /var/www/sub2api-audit-admin
cp -a backend/database/database.sqlite \
  "backend/database/database.sqlite.bak.$(date +%Y%m%d%H%M%S)"
tar -czf "private-files.$(date +%Y%m%d%H%M%S).tar.gz" \
  -C backend/storage/app private
```

### 5.2 部署后端并迁移

```bash
cd /var/www/sub2api-audit-admin/backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
```

不要在生产部署中重复执行 `php artisan key:generate`。已有生产 `APP_KEY` 必须保持不变。

### 5.3 首次锁定切账时间

将第 5.1 节记录的维护开始时间作为中国时间写入：

```bash
php artisan ledger:cutover --at="YYYY-MM-DD HH:mm:ss"
```

命令会同时显示中国时间和 UTC 时间。必须人工核对两者换算正确后再继续。

`ledger_cutover_at` 只允许首次写入，成功后永久锁定。重复执行会拒绝修改并打印当前切账时间；不得直接改 SQLite 或使用 `.env` 绕过锁定。

### 5.4 回填远端调额事件关联

```bash
php artisan ledger:link-sources
```

回填只允许按“Sub2API 用户 ID + 完整幂等键”匹配。禁止按 `ledger_no` 批量关联，也禁止人工猜测远端事件。

未唯一匹配的本地成功单保持成功状态且 `sub2api_source_id` 为空，后续由首页告警和对账暴露问题。

### 5.5 安装 Scheduler

Laravel 已配置每天 `00:15 Asia/Shanghai` 自动执行上一中国自然日对账，并启用 `withoutOverlapping`。服务器必须每分钟触发一次 Scheduler：

```cron
* * * * * cd /var/www/sub2api-audit-admin/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

可从 `deploy/laravel-scheduler.cron.example` 安装，或使用等价的 systemd timer。安装后执行：

```bash
php artisan schedule:list
```

确认 `ledger:reconcile` 的时区和下一次执行时间正确。

### 5.6 构建前端、清缓存并恢复服务

```bash
source ~/.nvm/nvm.sh
nvm use 22.22.0
cd /var/www/sub2api-audit-admin/frontend
corepack pnpm install --frozen-lockfile
corepack pnpm build

cd ../backend
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

重启 PHP-FPM、反向代理和需要的 Laravel worker 后，再恢复外部访问和本系统调额。

## 6. 对账命令和状态

自动处理上一中国自然日：

```bash
php artisan ledger:reconcile
```

手动补跑指定中国业务日期：

```bash
php artisan ledger:reconcile --date="YYYY-MM-DD"
```

同一业务日期只保留一个批次。手动补跑会在事务内重新计算并替换原批次差异明细，不会按旧逻辑返回重复批次 `409`，也不会重复累加。

批次状态只有：

```text
ok | warning | error
```

- `ok`：本地成功调额均正确关联，且没有外部事件或审计孤儿。
- `warning`：只有 `remote_external` 或 `remote_audit_orphan` 等需人工关注但不应自动认领的问题。
- `error`：存在本地缺失远端、用户/方向/金额不一致或重复 source link 等账实错误。

外部事件和审计孤儿只告警，不自动认领、不自动补录，也不拆分为现金和赠送。

## 7. 上线验收

### 7.1 基础检查

- `/api/v1/health` 返回 `status=ok`，时区为 `Asia/Shanghai`。
- 管理员可登录，附件上传和下载均走私有存储与鉴权。
- Sub2API 用户列表、当前普通启用用户余额快照可以读取。
- 首页官方统计失败时返回 `502 / SUB2API_STATS_UNAVAILABLE`，前端显示不可用，不展示假 0。
- 首页账务只统计切账后本系统成功调额，不把远端外部事件算作充值。
- 历史账页面只读，CSV 带 UTF-8 BOM，中文和中国时间正常。

### 7.2 官方用量基准

对已经结束的 `2026-07-01` 至 `2026-07-09` 查询，审计系统必须与 Sub2API 官方 Admin API 完全一致：

```text
请求数      8,506
Token       969,369,193
actual_cost 965.5262577
```

这些数值仅用于部署验收，不得写入业务代码、数据迁移或自动化测试。

### 7.3 旧账边界

核验已有旧账：

```text
实收   100
赠送   100
总调额 200
```

这些旧账必须保留在历史记录中，但不得进入切账后的首页当前财务统计、告警或当前对账。不得在代码或文档中硬编码生产远端事件 ID。

### 7.4 小额调增、调减与次日对账

分别使用测试用户执行一笔小额调增和一笔小额调减，逐项确认：

1. 官方调额 API 成功。
2. 二次余额确认一致。
3. 本地记录状态为 `succeeded`。
4. 唯一匹配时写入 `sub2api_source_id`；未关联时出现明确告警。
5. 首页现金、赠送、调增、调减和净额按定义变化。
6. 历史账显示远端事件及正确关联状态，但不反向改变实收入账。
7. 次日 `00:15` 自动对账生成一个批次，手动补跑同日时替换原明细。

## 8. 回滚与安全

- 回滚代码前先再次备份 SQLite 和私有附件。
- 不要删除已写入的 `ledger_cutover_at`，切账边界是永久审计事实。
- 涉及数据库结构回滚时，先确认旧版本能识别 `ok/warning/error` 和新增关联字段；不能确认时只回滚应用代码，不贸然执行 `migrate:rollback`。
- 应用日志只记录安全的接口路径、HTTP 状态、异常类型和响应字段形态，不记录密码、Token、API Key、Authorization 或完整敏感响应。
- 正式部署前必须由运维确认当前有效的 SSH 登录方式；不得在仓库或文档中保存服务器登录凭据。
