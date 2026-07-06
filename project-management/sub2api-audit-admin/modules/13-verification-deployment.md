# 13 联调验收与部署

状态: 进行中，部署准备和本地联调已完成，真实 Sub2API 调额验收待测试用户

## 1. 目标

完成全链路验收和部署准备。

## 2. 范围

- 后端全量测试。
- 前端类型检查和构建。
- E2E 冒烟。
- 桌面端全链路验收。
- H5 手机端全链路验收。
- 异常路径验收。
- Sub2API 调额测试用户验证。
- 环境变量清单。
- 部署文档。
- 附件持久化目录。

## 3. 依赖

- 后端和前端主要模块完成。
- 测试 Sub2API 用户准备好。

## 4. 交付物

- `frontend/tests/e2e/admin-ledger.spec.ts`
- `docs/dev-checklist.md`
- `docs/deployment.md`
- 部署配置文件。

## 5. 进度

| 项 | 状态 | 备注 |
|---|---|---|
| 后端测试 | 已完成 | `php artisan test` 24 passed |
| 前端构建 | 已完成 | `pnpm typecheck`、`pnpm build` 已通过 |
| E2E | 已完成 | `frontend/tests/e2e/admin-ledger.spec.ts`，desktop/h5 通过 |
| 桌面端全链路验收 | 已完成 | 登录并打开首页、调额记录、现金/赠送账、经营账、对账、审计 |
| H5 手机端全链路验收 | 已完成 | Playwright iPhone 12 视口通过 |
| 异常路径验收 | 已完成 | 后端测试覆盖未授权、调额失败、二次确认失败、非法附件、重复对账 |
| 真实调额验收 | 待测试用户 | 当前 `.env` 未配置 `SUB2API_ADMIN_API_URL` / `SUB2API_ADMIN_API_KEY`，不能伪造真实入账验收 |
| 部署文档 | 已完成 | `docs/dev-checklist.md`、`docs/deployment.md`、`deploy/Caddyfile.example`、`deploy/supervisor-laravel-worker.conf` |

## 6. 验收标准

- `php artisan test` 通过。
- `pnpm typecheck` 通过。
- `pnpm build` 通过。
- E2E 通过。
- 桌面端核心流程通过。
- H5 手机端核心流程通过。
- 异常路径不会误显示成功。
- 调额成功后 Sub2API 真实入账。
- 失败场景不会显示成功。
- 对账差异为 0。

## 7. 测试命令

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/backend
php artisan test

cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/frontend
pnpm typecheck
pnpm build
npx playwright test
```

## 8. 风险

- 真实调额测试必须使用小额测试用户，避免影响生产用户额度。

## 9. 完成记录

- 2026-07-06: 完成本地联调准备、后端全量测试、前端类型检查和构建、Playwright 桌面/H5 冒烟、部署文档、Caddy 和 supervisor 示例。
- 2026-07-06: 真实 Sub2API 调额验收未执行；当前本地 `.env` 没有 Sub2API Admin API URL/Key，也没有指定小额测试用户。根据强一致调额原则，不能把未真实入账的验收标记为完成。
