# 部署与数据迁移

## 1. 数据边界

生产环境使用两套 PostgreSQL 连接：

| 连接 | 用途 | 权限 |
|---|---|---|
| 默认 pgsql | Sub2API 管理员映射、审计账本、附件、利润和对账 | 本应用读写 |
| sub2api | Sub2API 用户、余额、充值、兑换和用量数据 | 只读 |

管理员账号由 Sub2API 管理。本系统登录时调用 Sub2API 用户 API 验证管理员身份，并在本地同步管理员资料。Sub2API 余额只能通过官方 Admin API 调整，禁止直接更新 Sub2API 数据库。

生产环境至少配置：

~~~env
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Shanghai

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sub2api_audit
DB_USERNAME=sub2api_audit
DB_PASSWORD=
DB_SSLMODE=prefer

SUB2API_DB_CONNECTION=sub2api
SUB2API_DB_HOST=127.0.0.1
SUB2API_DB_PORT=5432
SUB2API_DB_DATABASE=sub2api
SUB2API_DB_USERNAME=sub2api_ro
SUB2API_DB_PASSWORD=
SUB2API_DB_SSLMODE=prefer

SUB2API_ADMIN_API_URL=https://sub2api.example.com
SUB2API_ADMIN_API_KEY=
SUB2API_USER_API_URL=https://sub2api.example.com
SUB2API_USER_API_TIMEOUT=10
SUB2API_REMOTE_RECONCILE_DELAY_SECONDS=60

CACHE_STORE=database
SESSION_DRIVER=database
~~~

数据库密码和 API Key 不得写入仓库、部署日志或命令历史。附件目录 backend/storage/app/private 需要独立持久化和备份。

## 2. 审计 SQLite 迁移

只有从旧审计 SQLite 切换到 PostgreSQL 时才执行本节。

1. 进入维护模式并停止管理端写入和 Scheduler。
2. 对审计 SQLite 执行 checkpoint。
3. 确认源目录没有 -wal、-shm、-journal 旁路文件。
4. 备份审计 SQLite、私有附件和生产环境文件，记录 SHA-256。

~~~bash
stamp=$(date +%Y%m%d%H%M%S)
mkdir -p "/backup/sub2api-audit/$stamp"
sqlite3 backend/database/database.sqlite 'PRAGMA wal_checkpoint(TRUNCATE);'
test -z "$(find backend/database -maxdepth 1 -type f \( -name '*.sqlite-wal' -o -name '*.sqlite-shm' -o -name '*.sqlite-journal' \) -print -quit)"
cp -a backend/database/database.sqlite "/backup/sub2api-audit/$stamp/audit.sqlite"
tar -czf "/backup/sub2api-audit/$stamp/private-files.tar.gz" -C backend/storage/app private
sha256sum "/backup/sub2api-audit/$stamp/audit.sqlite"
~~~

先创建空 PostgreSQL 数据库并执行迁移，再对源文件 dry run：

~~~bash
cd /var/www/sub2api-audit-admin/backend
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force

php artisan audit:migrate-sqlite-to-postgres \
  --source=/backup/sub2api-audit/时间戳/audit.sqlite --dry-run

php artisan audit:migrate-sqlite-to-postgres \
  --source=/backup/sub2api-audit/时间戳/audit.sqlite --commit
~~~

命令保留主键 ID，校验行数和关键金额，并重置 PostgreSQL 序列。缓存、会话、队列、Token、迁移记录及旧返利表不会迁移，所有管理员需要重新登录。

## 3. 发布

~~~bash
cd /var/www/sub2api-audit-admin/frontend
corepack pnpm install --frozen-lockfile
corepack pnpm build

cd ../backend
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
~~~

Scheduler 每分钟触发，由 Laravel 在每天 00:15 执行 ledger:reconcile：

~~~cron
* * * * * cd /var/www/sub2api-audit-admin/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
~~~

当前应用没有常驻队列任务，不需要部署 Queue Worker。

## 4. 上线验收

- /api/v1/health 返回成功。
- Sub2API admin 角色可以登录，资料同步到只读管理员账号页。
- Sub2API 普通用户登录管理后台返回 403。
- Sub2API 用户列表、余额历史和模型统计可查询。
- 调额、入账记录、历史账、利润、对账和审计页面正常。
- Scheduler 正常执行 ledger:reconcile。
- 应用日志不记录密码、Token、API Key、Authorization 或完整敏感响应。

## 5. 回滚边界

数据库接受新写入前，可以停止新服务并切回已备份版本。数据库接受新账本或管理端写入后，只允许在 PostgreSQL 上前向修复；不要执行破坏性 migrate:rollback。

需要回退应用代码时，先备份 PostgreSQL、私有附件和生产环境文件，并确认旧代码能够读取当前字段。
