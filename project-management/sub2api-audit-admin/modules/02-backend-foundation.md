# 02 后端基础

状态: 已完成

## 1. 目标

建立 Laravel API 基础能力，包括配置、数据库连接、健康检查和统一响应格式。

## 2. 范围

- `config/sub2api.php`
- `config/ledger.php`
- `database.connections.sub2api`
- `/api/v1/health`
- 基础测试

不包含管理员登录和业务接口。

## 3. 依赖

- 01 项目基础完成。

## 4. 交付物

- `backend/config/sub2api.php`
- `backend/config/ledger.php`
- `backend/routes/api.php`
- `backend/tests/Feature/HealthTest.php`

## 5. 进度

| 项 | 状态 | 备注 |
|---|---|---|
| 配置文件 | 已完成 | 已新增 `config/sub2api.php`、`config/ledger.php`，并设置应用时区 |
| Sub2API 数据库连接 | 已完成 | 已新增 `database.connections.sub2api`，凭据从环境变量读取 |
| 健康检查接口 | 已完成 | 已新增 `/api/v1/health`，返回 `status=ok` 和 `timezone=Asia/Shanghai` |
| 测试 | 已完成 | `HealthTest` 已通过 |

## 6. 验收标准

- `/api/v1/health` 返回 `status=ok`。
- 返回时区为 `Asia/Shanghai`。
- 测试通过。

## 7. 测试命令

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/backend
php artisan test --filter=HealthTest
```

## 8. 风险

- 新项目 `.env` 不应提交。
- Sub2API 数据库密码只放环境变量。

## 9. 完成记录

| 日期 | 内容 |
|---|---|
| 2026-07-05 | 完成后端基础配置、Sub2API 只读连接配置、健康检查接口和 HealthTest |
