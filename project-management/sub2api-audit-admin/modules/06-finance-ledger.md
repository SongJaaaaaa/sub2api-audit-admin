# 06 财务账本

状态: 已完成

## 1. 目标

拆清现金账、用户额度账、赠送额度账和平台经营账。

## 2. 范围

- 现金收支账。
- 赠送额度账。
- 平台经营账。
- 调额单关联。
- 账本列表和筛选。

## 3. 依赖

- 05 强一致额度调整联调验收完成。

## 4. 交付物

- `backend/app/Models/CashEntry.php`
- `backend/app/Models/GiftQuotaEntry.php`
- `backend/app/Models/OperationExpense.php`
- 相关 Controller 和测试。
- `frontend/src/views/GiftQuotaListView.vue`
- `frontend/src/views/OperationExpenseView.vue`
- `frontend/src/views/LedgerAdjustmentListView.vue`

## 5. 进度

| 项 | 状态 | 备注 |
|---|---|---|
| 现金账 | 已完成 | `cash_entries`，调额二次确认成功后按现金金额入账 |
| 赠送额度账 | 已完成 | `gift_quota_entries`，赠送额度不进入经营支出 |
| 经营账 | 已完成 | `operation_expenses`，支持安全富文本说明 |
| 列表接口 | 已完成 | `/finance/cash`、`/finance/gifts`、`/finance/expenses` |
| 前端页面 | 已完成 | 现金账、赠送额度账、经营账已接真实 API |
| 测试 | 已完成 | `FinanceLedgerTest` 已通过 |
| 桌面端验收 | 已完成 | 表格横向滚动、金额列固定宽度，构建通过 |
| H5 手机端验收 | 已完成 | 表格启用横向滚动，关键金额字段不被遮挡 |
| 前后端联通验收 | 已完成 | Feature Test 覆盖调额成功后生成现金账和赠送账 |
| 异常路径验收 | 已完成 | 经营账失败提示、财务拆分不一致返回 422 |

## 6. 验收标准

- 用户付款 100、赠送 20 时，现金账为 100，额度账为 120，赠送账为 20。
- 赠送额度不进入经营支出。
- 桌面端可以筛选并查看现金账、赠送额度账、经营账。
- H5 手机端表格可横向滚动，关键金额字段不被遮挡。
- 前端页面必须接真实账本 API。
- 账本异常数据或接口失败时，前端必须显示失败状态，不能显示成功。

## 7. 测试命令

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/backend
php artisan test --filter=FinanceLedgerTest
```

## 8. 风险

- 现金、额度、赠送混账会导致账务统计不可信。

## 9. 完成记录

- 2026-07-06: 完成现金账、赠送额度账、经营账后端模型/接口/测试；调额成功后才生成财务账本；前端现金账、赠送额度账和经营账页面接入真实 API；`php artisan test` 和 `pnpm build` 已通过。
