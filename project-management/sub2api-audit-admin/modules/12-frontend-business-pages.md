# 12 前端业务页面

状态：已完成

## 1. 目标

完成审计系统桌面端与 H5 业务页面，接入真实后端 API，并按金额、Token、请求数和状态分别展示，不让失败数据伪装为 0。

## 2. 页面范围

| 页面 | 状态 | 核心内容 |
|---|---|---|
| 首页 | 已完成 | 三张 KPI、财务趋势、消费/Token 趋势、四类排行、最近调额和告警入口 |
| Sub2API 用户 | 已完成 | 用户搜索、当前余额和发起调额 |
| 模型统计 | 已完成 | requested 模型 Token 榜、指定模型下用户 Token 排行 |
| 调额记录 | 已完成 | 成功、失败、异常、作废记录和详情 |
| 用户与额度 | 已完成 | 当前用户余额、调额表单和业务确认 |
| 赠送额度 | 已完成 | 赠送额度账本列表 |
| 经营账 | 已完成 | 平台经营支出列表和录入 |
| 历史账 | 已完成 | 三类远端余额事件、筛选、分页、关联状态和 CSV 导出 |
| 对账中心 | 已完成 | 批次汇总、`ok/warning/error`、七类差异和手动补跑 |
| 异常中心 | 已完成 | 失败、异常和作废记录 |
| 操作审计 | 已完成 | 管理操作及新旧对账状态翻译 |

## 3. 首页展示规则

顶部 KPI：

1. **实收入账**：主值为未软删除 Sub2API 用户的 `total_recharged` 累计合计，明确标注当前快照且不随日期变化。
2. **实际消费**：主值 `actual_cost`，次值 Token 和请求数。
3. **普通启用用户当前余额**：主值余额，次值用户数，明确标注当前快照且不随日期变化。

四类排行必须独立：

- 本地现金入账用户榜。
- 用户实际消费榜。
- 用户 Token 榜。
- 请求模型 Token 榜。

金额使用金额格式，Token 使用整数缩写和千分位，请求数使用计数格式。不得复用统一金额格式。

## 4. 官方统计失败状态

当首页或模型统计收到：

```text
HTTP 502
code=SUB2API_STATS_UNAVAILABLE
```

页面必须：

- 显示“Sub2API 官方统计暂不可用”。
- 清空旧官方统计数据。
- 销毁旧 ECharts 实例，避免恢复请求时复用已卸载 DOM。
- 不显示假 0，也不以本地 SQL 数据替代官方统计。

本地财务、余额或其他独立模块仍可按接口实际状态展示，不能把官方失败误写成整页成功。

## 5. 历史账页面

- 默认 `period=history`，展示切账前 30 天。
- 支持日期、用户/关键词、来源、方向、关联状态和 period 筛选。
- 来源：后台余额调额、余额兑换码、已完成且实际改变余额的支付订单。
- 状态：`linked`、`audit_orphan`、`external`。
- 页面只读，无认领、补录、修改和删除入口。
- CSV 复用列表筛选，忽略分页，由后端流式导出 UTF-8 BOM 文件。

## 6. 对账页面

- 展示业务日期、实际起止时间、本地与远端笔数和净额、外部事件、审计孤儿及问题数。
- 状态使用 `ok`、`warning`、`error`。
- 操作审计兼容展示旧快照中的 `balanced`、`diff`，但新业务不再生成旧状态。
- 同日手动补跑会替换原批次明细，不提示旧版“重复对账 409”。

## 7. 交付物

- `frontend/src/views/DashboardView.vue`
- `frontend/src/views/Sub2ApiUsersView.vue`
- `frontend/src/views/Sub2ApiModelStatsView.vue`
- `frontend/src/views/UsersQuotaView.vue`
- `frontend/src/views/LedgerAdjustmentListView.vue`
- `frontend/src/views/GiftQuotaListView.vue`
- `frontend/src/views/OperationExpenseView.vue`
- `frontend/src/views/BalanceEventsView.vue`
- `frontend/src/views/ReconcileView.vue`
- `frontend/src/views/ExceptionCenterView.vue`
- `frontend/src/views/AuditLogView.vue`
- `frontend/src/api/*.ts`
- `frontend/src/router/index.ts`
- `frontend/src/config/menu.ts`

## 8. 验收标准

- 页面均接真实 API，不保留会误导验收的 mock 数字。
- 桌面端筛选、表格、趋势、抽屉和弹窗不拥挤。
- H5 筛选和主要操作不被遮挡；宽表允许横向滚动。
- 官方统计错误不会保留旧图表或显示假 0。
- 历史账只读、中文正常、CSV 可用。
- 首页四类排行名称、排序字段和展示单位一致。
- Node `22.22.0` 下生产构建通过。

## 9. 测试命令

```bash
source ~/.nvm/nvm.sh
nvm use 22.22.0
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/frontend
corepack pnpm build
corepack pnpm e2e
```
