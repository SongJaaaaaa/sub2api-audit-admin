# 09 对账中心

状态: 已完成

## 1. 目标

实现中国时区日报对账，并要求差异为 0。

## 2. 范围

- 对账批次。
- 现金合计。
- 额度合计。
- Sub2API 实际变更合计。
- 差异计算。
- 差异列表。

## 3. 依赖

- 05 强一致额度调整联调验收完成。
- 06 财务账本完成。

## 4. 交付物

- `backend/app/Services/Reconcile/ReconcileService.php`
- `backend/app/Http/Controllers/Api/V1/ReconcileController.php`
- `backend/tests/Feature/ReconcileTest.php`
- `frontend/src/views/ReconcileView.vue`

## 5. 进度

| 项 | 状态 | 备注 |
|---|---|---|
| 对账批次 | 已完成 | `reconciliation_batches`，业务日期唯一 |
| 公式实现 | 已完成 | 中国时区日报，成功调额额度合计对 Sub2API 已确认变动合计 |
| 差异列表 | 已完成 | 非 0 差异写入 `reconciliation_diffs` |
| 前端页面 | 已完成 | 对账中心可生成批次、查看差异 |
| 测试 | 已完成 | `ReconcileTest` 已通过 |
| 桌面端验收 | 已完成 | 批次表格和差异抽屉可查看核心金额 |
| H5 手机端验收 | 已完成 | 表格横向滚动，核心金额和状态可查看 |
| 前后端联通验收 | 已完成 | Feature Test 覆盖生成批次和差异列表 |
| 异常路径验收 | 已完成 | 重复对账 409，非 0 差异不会标记已对平 |

## 6. 验收标准

- 中国时区 `00:00:00` 到 `23:59:59` 为一天。
- 差异为 0 才能标记已对平。
- 不能人工把非 0 差异改成已对平。
- 桌面端可查看对账批次、差异列表和对平状态。
- H5 手机端可查看核心金额、差异原因和状态。
- 前端必须接真实对账 API。
- 非 0 差异、接口失败、重复对账都必须有明确失败或待处理状态。

## 7. 测试命令

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/backend
php artisan test --filter=ReconcileTest
```

## 8. 风险

- UTC 和中国时区混用会导致日报错位。

## 9. 完成记录

- 2026-07-06: 完成对账批次、差异明细、对账 API、前端对账中心和 `ReconcileTest`；差异非 0 时状态为 `diff`，不允许人工改成已对平；`php artisan test` 和 `pnpm build` 已通过。
