# 03 管理员认证

状态: 已完成

## 1. 目标

实现仅管理员可登录的后台认证体系。

## 2. 范围

- 管理员表。
- 登录。
- 当前管理员。
- 退出。
- Sanctum token。

不做多角色权限。

## 3. 依赖

- 02 后端基础完成。
- 核心表结构迁移完成。

## 4. 交付物

- `backend/app/Models/Admin.php`
- `backend/app/Services/Auth/AdminAuthService.php`
- `backend/app/Http/Controllers/Api/V1/AuthController.php`
- `backend/tests/Feature/AdminAuthTest.php`

## 5. 进度

| 项 | 状态 | 备注 |
|---|---|---|
| 管理员表 | 已完成 | 已新增 `admins` 表，密码使用 Laravel hashed cast |
| 登录接口 | 已完成 | 已新增 `/api/v1/auth/login`，仅 active 管理员可登录 |
| 当前管理员接口 | 已完成 | 已新增 `/api/v1/auth/me`，使用 Sanctum token 鉴权 |
| 退出接口 | 已完成 | 已新增 `/api/v1/auth/logout`，退出后删除当前 token |
| 测试 | 已完成 | `AdminAuthTest` 已通过 |

## 6. 验收标准

- active 管理员可登录。
- disabled 管理员不可登录。
- 未登录不能访问后台接口。

## 7. 测试命令

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/backend
php artisan test --filter=AdminAuthTest
```

## 8. 风险

- 管理员密码不能明文存储。
- token 退出后必须失效。

## 9. 完成记录

| 日期 | 内容 |
|---|---|
| 2026-07-05 | 完成管理员认证模块，新增 Admin 模型、认证服务、AuthController、管理员表、Sanctum token 表和 AdminAuthTest |
