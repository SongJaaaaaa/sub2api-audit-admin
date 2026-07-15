# 部署与数据切换

## 1. 数据边界

生产环境使用两套 PostgreSQL 连接：

| 连接 | 用途 | 权限 |
|---|---|---|
| 默认 `pgsql` | 管理员、审计账本、利润、推广关系、返利余额和提现 | 本应用读写 |
| `sub2api` | Sub2API 用户、邀请归因、充值和兑换事件 | 只读 |

Sub2API 余额只能通过官方 Admin API 调整，禁止直接更新 Sub2API 数据库。附件目录 `backend/storage/app/private` 独立持久化并备份。

生产 `.env` 至少配置：

```env
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
# 必须先验证同一 Idempotency-Key 的重复调额只生效一次，再改为 true。
SUB2API_BALANCE_IDEMPOTENCY_VERIFIED=false

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

数据库密码和 API Key 不得写入仓库、部署日志或命令历史。

## 2. 切换前备份

1. 进入维护模式，停止管理端写入、推广端写入、Scheduler 和所有 Queue Worker。
2. 等待正在处理的余额调整和提现任务结束；旧返利库不能存在冻结余额或未完成提现。
3. 对两套 SQLite 执行 checkpoint，确认源目录不存在 `-wal`、`-shm`、`-journal` 旁路文件。
4. 同时备份审计 SQLite、旧返利 SQLite、私有附件和生产 `.env`。
5. 对两份 SQLite 计算 SHA-256，并把文件大小、哈希和停机时间写入变更单。

示例：

```bash
stamp=$(date +%Y%m%d%H%M%S)
mkdir -p "/backup/sub2api-audit/$stamp"
sqlite3 backend/database/database.sqlite 'PRAGMA wal_checkpoint(TRUNCATE);'
sqlite3 /srv/sub2rebate/backend/database/database.sqlite 'PRAGMA wal_checkpoint(TRUNCATE);'
test -z "$(find backend/database /srv/sub2rebate/backend/database -maxdepth 1 -type f \( -name '*.sqlite-wal' -o -name '*.sqlite-shm' -o -name '*.sqlite-journal' \) -print -quit)"
cp -a backend/database/database.sqlite "/backup/sub2api-audit/$stamp/audit.sqlite"
cp -a /srv/sub2rebate/backend/database/database.sqlite "/backup/sub2api-audit/$stamp/rebate.sqlite"
tar -czf "/backup/sub2api-audit/$stamp/private-files.tar.gz" -C backend/storage/app private
sha256sum "/backup/sub2api-audit/$stamp/audit.sqlite" "/backup/sub2api-audit/$stamp/rebate.sqlite"
```

迁移命令会拒绝任何带 `-wal`、`-shm` 或 `-journal` 的源文件，以 SQLite 只读模式打开备份，并在 PostgreSQL 目标事务提交前再次核对 SHA-256。源文件变化会让整个目标事务回滚，可修复备份后重新执行。

## 3. PostgreSQL 迁移顺序

先创建空 PostgreSQL 数据库和最小权限应用账号，再部署代码并安装依赖：

```bash
cd /var/www/sub2api-audit-admin/backend
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
```

先对审计主库执行 dry run，确认表清单、行数和跳过表正确，再提交：

```bash
php artisan audit:migrate-sqlite-to-postgres \
  --source=/backup/sub2api-audit/时间戳/audit.sqlite --dry-run

php artisan audit:migrate-sqlite-to-postgres \
  --source=/backup/sub2api-audit/时间戳/audit.sqlite --commit
```

该命令按外键顺序迁移持久业务表，保留主键 ID，校验行数和关键金额，并重置 PostgreSQL 序列。`cache`、`session`、`jobs`、`personal_access_tokens` 和 `migrations` 不迁移，所有管理员需要重新登录。

然后导入旧返利库：

```bash
php artisan rebate:import-legacy \
  --source=/backup/sub2api-audit/时间戳/rebate.sqlite --dry-run

php artisan rebate:import-legacy \
  --source=/backup/sub2api-audit/时间戳/rebate.sqlite --commit
```

旧返利导入会：

- 保留 Sub2API 用户 ID 和直接推荐关系；
- 把可用余额和已提现金额分别写为 `legacy_opening`、`legacy_withdrawn` 不可变流水；
- 把历史提现写为 `succeeded + read_only`，不创建调额任务，不允许重放；
- 在审计日志和历史记录中保存源 SQLite SHA-256；
- 把导入时间写为 `rebate_cutover_at`，只处理此时间后的新充值事件。

切换时间统一截断到秒，并以同一秒边界初始化三类复合游标，避免 Sub2API `timestamp(0)` 事件因微秒格式差异漏扫。

未导入旧返利库时，必须在仍处于维护模式且旧系统已停止写入后显式锁定切换边界：

```bash
php artisan rebate:cutover
```

旧返利导入命令会同时锁定该边界并初始化三类扫描游标，不要再用不同时间重复执行 `rebate:cutover`。

当前旧库的 `4` 个用户、`4` 条关系、可用余额 `24`、已提现 `6`、`3` 条成功提现只是迁移前参考。验收必须以停机备份的命令动态统计结果为准。

## 4. 恢复服务

构建前端并刷新 Laravel 缓存：

```bash
cd /var/www/sub2api-audit-admin/frontend
corepack pnpm install --frozen-lockfile
corepack pnpm build

cd ../backend
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

解除维护模式前，使用测试账号验证 Sub2API 对同一 `Idempotency-Key` 的重复调额只产生一条远端流水且只增加一次余额。该契约未验证时，保持 `SUB2API_BALANCE_IDEMPOTENCY_VERIFIED=false`；返利提现会进入异常状态并保留冻结余额，不会发起远端加额。验证通过后改为 `true` 并重新执行 `php artisan config:cache`。这是上线阻断条件。

先手动执行一次扫描和已提交任务恢复，确认命令成功后再启动常驻服务：

```bash
php artisan rebate:scan
php artisan rebate:recover-queue
```

Queue Worker 必须常驻，Scheduler 必须每分钟触发：

```cron
* * * * * cd /var/www/sub2api-audit-admin/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

启动 Worker 后检查进程、失败任务和应用日志，再解除维护模式。数据库队列至少运行：

```bash
php artisan queue:work --queue=default --tries=3 --timeout=120
```

生产环境应由 systemd 或 Supervisor 托管 Worker，不要以前台会话长期运行。

`rebate:recover-queue` 只重新投递 `pending` 事件和 `processing` 提现。确认失败原因已经修复后，失败返利事件必须显式重投，再恢复队列：

```bash
php artisan rebate:retry-events 123 124
# 或按上限批量重投失败事件
php artisan rebate:retry-events --limit=100
php artisan rebate:recover-queue
```

## 5. 上线验收

- 两个迁移命令的源 SHA-256 与备份记录一致，所有行数和金额校验通过。
- 管理员可重新登录，旧账本、附件索引、审计、利润结算均可查询。
- 返利配置为里程碑 `100/15/2`、后续台阶 `100/15`、最低提现 `2`、每日 `10` 次、每日总金额不限、换算比例 `1`。
- 旧余额只有 `legacy_opening`、`legacy_withdrawn` 流水；历史提现为只读成功记录，没有对应待执行任务。
- 新充值只奖励直接上级；提现申请先冻结，管理员审核通过后只增加一次 Sub2API 额度。
- `SUB2API_BALANCE_IDEMPOTENCY_VERIFIED=true` 的验证记录已附在变更单，响应丢失后的重试不会重复加额。
- `/api/v1/health`、Scheduler、Worker 和失败任务监控正常。

## 6. 回滚边界

解除维护模式、接受 PostgreSQL 新写入之前，可以停止新服务并切回原应用和两份 SQLite 备份。

一旦 PostgreSQL 接受了充值事件、返利、提现或管理端写入，只允许在 PostgreSQL 上前向修复。此时不得切回 SQLite、不得执行破坏性 `migrate:rollback`，否则会丢失新资金事实。需要回退应用代码时，先备份 PostgreSQL，并确认旧代码能够读取当前状态字段。

每次部署继续备份 PostgreSQL、私有附件和 `.env`。应用日志不得记录密码、Token、API Key、Authorization 或完整敏感响应。
