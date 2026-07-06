# 部署说明

## 目标结构

```text
/var/www/sub2api-audit-admin
  backend
  frontend/dist
  storage/app/private
```

建议部署在 Sub2API 同服务器或同内网，Sub2API PostgreSQL 只走内网或 SSH 隧道，不暴露公网。

## 后端环境变量

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
APP_KEY=
APP_LOCALE=zh_CN
APP_FALLBACK_LOCALE=zh_CN

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sub2api_audit_admin
DB_USERNAME=sub2api_audit_admin
DB_PASSWORD=

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

FILESYSTEM_DISK=local

SUB2API_DB_CONNECTION=sub2api
SUB2API_DB_HOST=127.0.0.1
SUB2API_DB_PORT=5432
SUB2API_DB_DATABASE=sub2api
SUB2API_DB_USERNAME=sub2api_ro
SUB2API_DB_PASSWORD=
SUB2API_DB_SCHEMA=public
SUB2API_DB_SSLMODE=prefer

SUB2API_ADMIN_BASE_URL=https://sub2api.example.com
SUB2API_ADMIN_API_KEY=
```

## 前端环境变量

生产构建时建议使用真实 API 域名:

```env
VITE_API_BASE_URL=https://api.example.com/api/v1
```

## 构建部署

```bash
cd backend
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

cd ../frontend
pnpm install --frozen-lockfile
pnpm build
```

## 附件持久化

附件使用 Laravel `local` 私有盘，默认目录:

```text
backend/storage/app/private
```

生产环境必须持久化该目录，并纳入备份。不要把附件目录软链到 public。

## 队列

当前业务接口以同步链路为主，仍建议部署 queue worker 以便后续扩展。

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

## 上线后检查

- `/api/v1/health` 返回 `ok` 和 `Asia/Shanghai`。
- 管理员可登录。
- Sub2API 用户列表可读取。
- 模型统计可读取 `usage_logs`。
- 小额测试用户真实调额成功后，新系统才显示成功。
- 附件上传后文件落在私有目录。
- 对账差异为 0 才显示已对平。
