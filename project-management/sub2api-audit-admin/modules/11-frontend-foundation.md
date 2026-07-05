# 11 前端基础

状态: 已完成

## 1. 目标

搭建 soybean-admin-antd 风格的管理端基础框架。

## 2. 范围

- Vue3 + Vite + TypeScript。
- AntDesignVue。
- Pinia。
- Router。
- Axios。
- 登录页。
- 管理端布局。
- 清理 Vite 默认演示页。
- 主菜单入口。
- API 请求封装。
- 图标主题按钮，支持跟随系统、浅色、深色主题。
- H5 手机端抽屉菜单。
- 移动端基础布局适配。

## 3. 依赖

- 01 项目基础完成。
- 03 管理员认证接口完成。

## 4. 交付物

- `frontend/src/main.ts`
- `frontend/src/api/http.ts`
- `frontend/src/stores/auth.ts`
- `frontend/src/layouts/AdminLayout.vue`
- `frontend/src/router/index.ts`
- `frontend/src/views/LoginView.vue`
- `frontend/src/views/DashboardView.vue`
- `frontend/src/config/menu.ts`
- `frontend/src/stores/theme.ts`
- `frontend/src/App.vue`

## 5. 进度

| 项 | 状态 | 备注 |
|---|---|---|
| Vite/Vue 初始化 | 已完成 | 已创建前端项目结构 |
| 清理默认演示页 | 已完成 | 已删除 HelloWorld、vite/vue 默认资产和演示内容 |
| AntDesignVue | 已完成 | 已接入 AntDesignVue 和图标库 |
| 路由 | 已完成 | 已新增 `frontend/src/router/index.ts` |
| 登录状态 | 已完成 | 已新增 Pinia auth store，保存 token 和管理员信息 |
| API 请求封装 | 已完成 | 已新增 Axios 实例和 token 注入 |
| 管理布局 | 已完成 | 已新增 `AdminLayout.vue` |
| 主菜单 | 已完成 | 已新增 `config/menu.ts`，包含当前规划主模块入口 |
| 主题模式 | 已完成 | 使用系统、太阳、月亮图标按钮切换，并保存到浏览器本地 |
| H5 抽屉菜单 | 已完成 | 手机端使用抽屉菜单，避免桌面侧栏挤压 |
| 移动端基础布局 | 已完成 | 顶栏、内容区、登录页和卡片栅格已按手机宽度适配 |
| 构建检查 | 已完成 | `pnpm typecheck`、`pnpm build` 已通过 |

## 6. 验收标准

- 登录页可提交。
- 登录后进入管理布局。
- 菜单包含所有主模块。
- 构建通过。
- 不保留 Vite 默认欢迎页和演示组件。
- 04 之后的业务模块可以直接接入菜单、路由和 API 封装。
- 顶栏使用图标按钮选择跟随系统、浅色、深色主题。
- 手机端使用 H5 抽屉菜单，常用页面不被侧栏挤压。
- 后续业务页面必须同时按桌面和手机端验收。

## 7. 测试命令

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/frontend
pnpm typecheck
pnpm build
```

## 8. 风险

- 不要继续使用旧系统用户端风格。

## 9. 完成记录

| 日期 | 内容 |
|---|---|
| 2026-07-05 | 增加图标主题按钮和 H5 基础适配，支持跟随系统、浅色、深色和手机端抽屉菜单 |
| 2026-07-05 | 完成前端基础，清理 Vite 默认页，接入 AntDesignVue、Router、Pinia、Axios、登录页、管理布局和主菜单 |
