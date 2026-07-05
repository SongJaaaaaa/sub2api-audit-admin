# 05 强一致额度调整

状态: 已完成

## 1. 目标

实现从新系统调整 Sub2API 额度，并保证只有 Sub2API 真实入账后才显示成功。

## 2. 范围

- 业务单号。
- 幂等键。
- Sub2API notes 标签。
- 调额调用。
- 二次确认。
- 成功单。
- 作废单。
- 异常单。
- 用户与额度基础页面。
- 额度调整记录基础页面。
- 异常中心基础页面。
- 前后端真实联通验收。
- H5 调额流程验收。

## 3. 依赖

- 03 管理员认证完成。
- 04 Sub2API 集成完成。
- 核心表结构完成。

## 4. 交付物

- `backend/database/migrations/*_create_ledger_adjustments_table.php`
- `backend/app/Models/LedgerAdjustment.php`
- `backend/app/Services/Ledger/LedgerAdjustmentService.php`
- `backend/app/Services/Ledger/Sub2ApiBalanceVerifier.php`
- `backend/app/Services/Ledger/LedgerNumberService.php`
- `backend/app/Support/Sub2ApiNoteTag.php`
- `backend/app/Http/Controllers/Api/V1/LedgerAdjustmentController.php`
- `backend/tests/Feature/LedgerAdjustmentTest.php`
- `frontend/src/api/ledger.ts`
- `frontend/src/views/UsersQuotaView.vue`
- `frontend/src/views/LedgerAdjustmentListView.vue`
- `frontend/src/views/ExceptionCenterView.vue`

## 5. 进度

| 项 | 状态 | 备注 |
|---|---|---|
| 表结构和模型 | 已完成 | 新增 `ledger_adjustments` 表和模型 |
| 业务号生成 | 已完成 | `ADJYYYYMMDD0001` 格式 |
| 幂等键 | 已完成 | 业务号 + UUID，随 Sub2API 请求头发送 |
| Sub2API 调额 | 已完成 | 后端调用 Admin API 调额接口 |
| 二次确认 | 已完成 | 调额后重新查询用户余额，匹配预期余额才成功 |
| 作废异常 | 已完成 | 调用失败写作废，确认失败写异常 |
| 前端 API 封装 | 已完成 | `frontend/src/api/ledger.ts` 调用真实 `/ledger-adjustments` |
| 用户与额度基础页面 | 已完成 | 可搜索用户并打开调额弹窗 |
| 成功记录基础页面 | 已完成 | `LedgerAdjustmentListView` 读取成功记录 |
| 异常中心基础页面 | 已完成 | `ExceptionCenterView` 读取作废和异常记录 |
| 后端测试 | 已完成 | `LedgerAdjustmentTest` 已通过 |
| 前端构建 | 已完成 | `pnpm build` 已通过 |
| 提交前确认文案 | 已完成 | 提交前明确提示“只有 Sub2API 入账并二次确认后才生成成功记录” |
| 前后端真实联通验收 | 已完成 | 浏览器实测登录、用户列表、调额提交、异常中心列表 |
| H5 调额流程验收 | 已完成 | 390px 手机视口实测菜单、调额弹窗、确认文案、异常提示和异常中心 |

## 6. 验收标准

- Sub2API 成功且二次确认成功才显示成功。
- Sub2API 失败时原单作废。
- 作废必须有异常原因。
- 失败单不出现在成功列表。
- 前端调额提交前必须有明确确认文案。
- 浏览器实测调额成功后，成功记录页能看到同一业务单号。
- 浏览器实测调额失败或未确认时，异常中心能看到原单和异常原因。
- H5 手机端能完成搜索用户、打开调额弹窗、查看结果记录。

## 7. 测试命令

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/backend
php artisan test --filter=LedgerAdjustmentTest

cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/frontend
pnpm build
```

## 8. 风险

- Sub2API 返回成功但查询确认延迟，需要进入异常中心，不能直接成功。
- 本地验收环境未配置 Sub2API Admin API，已验证异常路径不会误报成功；真实入账成功路径需要生产密钥和 Sub2API 环境再做上线前验收。

## 9. 完成记录

- 2026-07-05: 复核 05 进度，确认后端 API、二次确认、失败作废、异常记录和基础前端入口已完成，但提交确认文案、真实前后端联通验收和 H5 调额流程验收待补。
- 2026-07-05: 完成强一致额度调整后端 API、二次确认、失败作废、异常记录、前端入口和构建验证。
- 2026-07-05: 补充提交前确认文案；完成前后端真实联通验收和 H5 调额流程验收，确认本地异常路径不显示成功并进入异常中心。
