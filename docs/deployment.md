# 部署

## 1. 配置

生产环境只需要填写两个变量：

```env
SUB2API_API_URL=https://sub2api.example.com
SUB2API_ADMIN_API_KEY=
```

同一个地址用于管理员登录、用户读取、账变历史和官方统计。请求由后端携带 `x-api-key`，Key 不得写入仓库、前端构建产物、部署日志或命令历史。

应用名称、域名、时区、CORS、超时、SQLite、缓存、会话和队列均使用代码内默认值。`APP_KEY` 首次启动时自动生成到 `backend/storage/app.key`，无需手动配置；该文件和 `backend/database/database.sqlite` 必须持久化并备份。

## 2. 首次启动

```bash
cd /var/www/sub2api-audit-admin/backend
cp .env.example .env
# 填写上面的两个变量
touch database/database.sqlite
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
```

确保 PHP 运行用户可写 `backend/database/`、`backend/storage/` 和 `backend/bootstrap/cache/`。

## 3. 生成生产发布包

本地完成测试和提交后，只能使用发布脚本生成生产包。脚本从 Git `HEAD` 导出源码，再加入 `frontend/dist`，并拒绝任何 SQLite、`.env` 和 `app.key`：

```powershell
& 'D:\nvm\nodejs\npm.cmd' run build
powershell -NoProfile -ExecutionPolicy Bypass -File .\deploy\build-release.ps1
```

产物位于 `release-tmp/sub2api-audit-admin-production.zip`。禁止直接打包或复制整个本地工作目录，特别是 `backend/database/` 和 `backend/storage/`。

服务器上的 `backend/database/database.sqlite`、`backend/storage/app.key` 和附件目录属于持久数据。发布时只能覆盖生产包内的代码和前端资源，不得用本地文件替换这些目录。

## 4. 发布

```bash
cd /var/www/sub2api-audit-admin/frontend
corepack pnpm install --frozen-lockfile
corepack pnpm build

cd ../backend
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

App 的正式 API 地址已固定为 `https://autsub2.hyojooapi.top/api/v1`，构建时无需提供 Vite API 环境变量。

Scheduler 每分钟触发，用于同步 Sub2API 外部收入：

```cron
* * * * * cd /var/www/sub2api-audit-admin/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

当前应用没有常驻队列任务，不需要部署 Queue Worker。

## 5. 上线验收

- `/api/v1/health` 返回成功。
- Sub2API admin 角色可以登录，普通用户登录返回 403。
- 用户列表、余额历史、余额事件、支付订单和模型统计可查询。
- 连续调用同一列表接口不会出现间歇性 500/502。
- 调额、入账记录、历史账、利润和审计页面正常。
- 日志不记录密码、Token、API Key、Authorization 或完整敏感响应。

## 6. 备份与回滚

发布前备份 `backend/database/database.sqlite`、`backend/storage/app.key` 和 `backend/storage/app/private`。数据库接受新账本写入后，只允许前向修复，不执行破坏性 `migrate:rollback`。
