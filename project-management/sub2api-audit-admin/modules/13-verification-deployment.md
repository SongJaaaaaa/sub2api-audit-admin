# 13 联调验收与部署

状态: 未开始

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
| 后端测试 | 未开始 |  |
| 前端构建 | 未开始 |  |
| E2E | 未开始 |  |
| 桌面端全链路验收 | 未开始 |  |
| H5 手机端全链路验收 | 未开始 |  |
| 异常路径验收 | 未开始 |  |
| 真实调额验收 | 未开始 |  |
| 部署文档 | 未开始 |  |

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
