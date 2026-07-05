# 01 项目基础

状态: 已完成

## 1. 目标

创建独立新项目 `sub2api-audit-admin`，建立后端、前端、文档和部署目录。

## 2. 范围

- 初始化 Git 仓库。
- 创建 Laravel 后端。
- 创建 Vue3 管理前端。
- 添加基础 `.gitignore`。
- 建立新项目 docs 目录。

不包含业务接口和页面开发。

## 3. 依赖

- 本机 PHP、Composer、Node、pnpm 可用。
- 新项目路径确认。

## 4. 交付物

- `backend/`
- `frontend/`
- `docs/`
- `deploy/`
- `.gitignore`
- `README.md`

## 5. 进度

| 项 | 状态 | 备注 |
|---|---|---|
| 目录创建 | 已完成 | 已创建 `backend/`、`frontend/`、`docs/`、`deploy/` |
| Laravel 初始化 | 已完成 | `backend/` 使用 Laravel 12 |
| Vue 前端初始化 | 已完成 | `frontend/` 使用 Vue3 + Vite + TypeScript |
| Git 初始化 | 已完成 | 新项目根目录已初始化 Git 仓库 |
| 首次提交 | 已完成 | 已提交项目基础模块 |

## 6. 验收标准

- 新项目目录存在。
- 后端能执行 `php artisan --version`。
- 前端能执行 `pnpm install`。
- Git 仓库已初始化并有首次提交。

## 7. 测试命令

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/backend
php artisan --version

cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/frontend
pnpm install
```

## 8. 风险

- 项目路径含中文，部分工具输出可能显示 `??`，但文件内容不受影响。
- 本机 Homebrew 旧版本曾导致 PHP/Composer 安装失败，已更新 Homebrew 并安装可用的 PHP/Composer。

## 9. 完成记录

| 日期 | 内容 |
|---|---|
| 2026-07-05 | 完成新项目初始化、Laravel 12 后端、Vue3 前端、项目文档目录、部署目录、Git 初始化和首次提交 |
