# Sub2API 审计管理后台系统架构

## 1. 架构目标

新系统是纯管理后台，核心目标是:

- 看清 Sub2API 用户使用情况、模型消费、充值和额度使用排行。
- 统一记录现金、额度、赠送额度、经营支出。
- 从新系统调整 Sub2API 额度，并保证不会出现“新系统成功但 Sub2API 未入账”。
- 对 Sub2API 外部直改进行扫描回收和补录。
- 提供可追溯的附件、富文本、操作审计和对账闭环。

## 2. 系统上下文

```text
管理员浏览器
    |
    v
Vue3 + AntDesignVue 管理前端
    |
    v
Laravel API 后端
    |
    |-- 本系统 PostgreSQL: 账本、附件索引、对账批次、审计日志
    |-- Redis: 缓存、队列、锁
    |-- 私有文件存储: 凭证图片、PDF、附件
    |
    |-- Sub2API PostgreSQL 只读连接: users / usage_logs / redeem_codes / payment_orders
    |
    `-- Sub2API Admin API: 用户查询、额度调整、余额历史、账号统计
```

## 3. 后端模块

| 模块 | 责任 |
|---|---|
| Admin Auth | 管理员登录、退出、当前管理员 |
| Sub2API Integration | 只读库查询、Admin API client、连接健康检查 |
| Ledger Adjustment | 强一致调额、正式成功单、作废异常单 |
| Finance Ledger | 现金账、赠送额度账、经营账 |
| Dashboard Stats | 首页排行榜、时间筛选、模型分类 |
| Attachment | 私有附件上传、下载、权限控制 |
| Rich Text | 富文本保存前过滤、展示安全处理 |
| Reconcile | 中国时区日报、0 差异对账、差异明细 |
| Audit | 所有危险操作留痕 |

## 4. 前端模块

| 模块 | 页面 |
|---|---|
| Layout | 登录页、管理端布局、侧边栏、顶栏 |
| ThemeResponsive | 图标主题按钮、跟随系统/浅色/深色主题、H5 抽屉菜单、移动端布局 |
| Dashboard | 充值榜、额度使用榜、模型消费榜、时间筛选 |
| UsersQuota | 用户搜索、当前额度、发起调额 |
| Ledger | 额度调整记录、列显示配置、详情抽屉 |
| GiftQuota | 赠送额度记录 |
| OperationExpense | 平台经营账 |
| Reconcile | 对账批次、差异结果 |
| ExceptionCenter | 作废单、异常单、补充原因 |
| AuditLog | 操作审计查询 |
| Attachment | 上传、预览、下载 |

前端必须把手机 H5 作为正式使用场景，不只按桌面后台验收。全局主题支持三种模式:

- 跟随系统。
- 固定浅色。
- 固定深色。

主题入口使用图标按钮，系统模式用显示器图标，浅色用太阳图标，深色用月亮图标。主题选择保存在浏览器本地，刷新后保持管理员上次选择。

## 5. 强一致调额链路

```text
管理员提交调额
    |
    v
创建 ledger_adjustments 内部执行记录
    |
    v
生成 ledger_no / idempotency_key / notes 标签
    |
    v
调用 Sub2API POST /api/v1/admin/users/{id}/balance
    |
    v
二次查询用户详情或余额历史确认
    |
    |-- 成功 -> 写正式成功账本、现金账、赠送账、审计
    |
    `-- 失败/超时 -> 原单作废或异常，必须填写原因，不显示成功
```

业务页面只展示二次确认后的成功记录为成功。内部执行中状态只用于后端流程，不作为成功账单展示。

## 6. 反向同步链路

```text
Sub2API 后台发生调额
    |
    v
扫描 redeem_codes / balance-history / 可用外部记录
    |
    v
识别 source_platform = sub2api 或 scanner
    |
    v
写入基础记录
    |
    v
管理员后续补充备注、富文本、附件
```

反向同步不猜测原因。能识别的只写确定字段，不能确定的进入待补充状态。

## 7. 数据源

| 数据 | 来源 |
|---|---|
| 用户和当前额度 | Sub2API `users` / Admin users API |
| 充值和兑换 | `payment_orders`、`redeem_codes` |
| 调额回收 | `redeem_codes.type = admin_balance`、余额历史接口 |
| 模型消费 | `usage_logs` |
| 上游账号统计 | Admin accounts stats API |
| 本系统正式账 | 本系统 PostgreSQL |
| 附件 | 本系统私有存储 |

## 8. 部署架构

建议部署在和 Sub2API 同服务器或同内网:

```text
Caddy / Nginx
    |
    |-- admin.example.com -> frontend dist
    |
    `-- api.example.com -> Laravel API

Laravel API
    |
    |-- 本系统 PostgreSQL
    |-- Redis
    |-- Sub2API PostgreSQL 内网只读连接
    `-- Sub2API Admin API 内网或白名单 HTTPS
```

不要把 Sub2API PostgreSQL 直接暴露到公网。开发机访问时使用 SSH 隧道。

## 9. 安全边界

- 管理员才能登录。
- 附件不放 public 目录。
- 下载附件必须走后端鉴权。
- 富文本入库前过滤危险标签和脚本。
- 调额、作废、补账、删除附件都写审计。
- Sub2API Admin API Key 只放 `.env` 或部署密钥，不提交到代码。

## 10. 时间和金额口径

- 业务时区: `Asia/Shanghai`
- 数据库存储: 推荐 UTC
- 展示和统计: 转中国时区
- 金额: decimal，两位小数
- 额度: decimal，两位小数
- 汇率: 1 元 = 1 Sub2API 额度
- 对账差异: 必须为 0
