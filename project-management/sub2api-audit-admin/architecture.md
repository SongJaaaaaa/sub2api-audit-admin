# Sub2API 审计管理后台系统架构

## 1. 架构目标

系统是只面向管理员的审计后台，核心目标是：

- 从本系统安全调整 Sub2API 用户余额，并形成可追溯台账。
- 正确区分实收、赠送、正向调额、负向调额和调额净额。
- 完全沿用 Sub2API 官方口径展示请求、Token、标准消费、实际消费和模型统计。
- 通过只读远端数据展示当前余额和历史余额事件。
- 对未关联成功单提供告警，不猜测、不自动认领。
- 提供附件、富文本、操作审计、CSV 导出和桌面/H5 管理页面。

## 2. 系统上下文

```text
管理员浏览器
    |
    v
Vue 3 + Ant Design Vue 前端
    |
    v
Laravel API 后端
    |
    |-- 本地 SQLite
    |     调额账本 / 财务账 / 审计日志 / 切账设置
    |
    |-- 私有文件存储
    |     凭证图片 / PDF / 其他附件
    |
    |-- Sub2API Admin API
    |     用户查询 / 余额调整 / 二次确认 / 官方 Dashboard 统计
    |
    `-- Sub2API PostgreSQL 只读连接
          用户余额 / 后台调额事件 / 兑换码 / 支付订单 / 历史账
```

远端 PostgreSQL 不承担本系统主库职责，也不用于重新计算官方用量统计。

## 3. 三类数据源边界

| 数据源 | 负责内容 | 明确禁止 |
|---|---|---|
| 本地审计 SQLite | `cash_total`、赠送、调增、调减、净额、实收入账用户榜、调额记录和告警 | 不得把远端外部调额或支付事件算成本地实收入账 |
| Sub2API 官方 Admin API | 请求数、四类 Token、标准消费、实际消费、用户实际消费榜、用户 Token 榜、requested 模型榜 | 官方接口失败时不得降级为自定义 SQL |
| Sub2API PostgreSQL 只读连接 | 当前普通启用用户余额、远端事件关联和历史余额事件 | 不得直接写用户余额、兑换码、支付订单或其他业务表 |

官方统计接口包括：

```text
/api/v1/admin/dashboard/trend
/api/v1/admin/dashboard/models
/api/v1/admin/dashboard/users-ranking
/api/v1/admin/dashboard/user-breakdown
```

## 4. 时间与切账

- 业务时区固定为 `Asia/Shanghai`。
- 对外参数为包含首尾日期的 `start_date`、`end_date`。
- 本地 SQLite 使用中国时间半开区间：`[开始日 00:00:00, 结束日次日 00:00:00)`。
- 远端 PostgreSQL 查询将相同边界转换为 UTC 半开区间。
- 官方 Admin API 传自然日日期和 `timezone=Asia/Shanghai`。
- 切账时间保存在 `system_settings.ledger_cutover_at`，首次写入后永久锁定。
- 切账只限制账务统计、历史/当前分类和告警；官方用量仍按完整中国自然日展示。

## 5. 后端模块

| 模块 | 责任 |
|---|---|
| Admin Auth | 管理员登录、退出、当前管理员和 Sanctum 鉴权 |
| Sub2API Integration | 官方 Admin API client、只读库查询、用户和余额历史入口 |
| Ledger Adjustment | 幂等调额、官方 API 调用、余额二次确认、远端 source ID 关联 |
| Ledger Cutover | 首次锁定切账时间、账务查询求交集 |
| Finance Ledger | 现金、赠送、经营账和调额明细 |
| Dashboard Stats | 本地财务、官方用量、当前余额快照、四类独立排行和告警 |
| Model Stats | requested 模型统计和指定模型下用户 Token 排行 |
| Balance Events | 历史/当前远端余额事件只读列表和 CSV 导出 |
| Attachment / Rich Text | 私有附件、下载鉴权和富文本过滤 |
| Audit | 调额、财务和附件等管理操作留痕 |

## 6. 前端模块

| 模块 | 页面或能力 |
|---|---|
| Layout | 登录、管理布局、侧边栏、顶栏、主题切换和 H5 抽屉 |
| Dashboard | 三张 KPI、财务趋势、消费/Token 趋势、四类排行、最近调额和告警入口 |
| Sub2API Users | 用户搜索、当前余额和发起调额 |
| Model Stats | requested 模型榜、指定模型下用户 Token 榜 |
| Ledger / Finance | 调额记录、现金账、赠送账和经营账 |
| Balance Events | 历史账只读筛选、分页和 CSV 导出 |
| Audit / Exception | 操作审计、失败和作废记录 |
| Attachment | 私有附件上传、预览和下载 |

金额、Token 和请求数使用各自的格式化方式，避免用金额格式展示 Token 或请求数。

## 7. 调额链路

```text
管理员提交调额
    |
    v
创建 ledger_adjustments 执行记录
    |
    v
生成 ledger_no、完整 idempotency_key 和审计备注标签
    |
    v
调用 Sub2API 官方余额调整 API
    |
    v
二次查询并确认用户余额
    |
    |-- 未确认成功：记录失败/异常，不展示为成功
    |
    `-- 确认成功：保存本地成功账本
              |
              v
       按 used_by + 完整 idempotency_key 查询远端事件
              |
              |-- 唯一匹配：保存 sub2api_source_id
              `-- 0 或多条：保留成功，source ID 为空并写 warning
```

`ledger_no` 只用于展示，禁止作为关联或兜底匹配条件。

## 8. 首页统计口径

### 财务

- `cash_total`：切账后范围内，本系统成功调增单的 `cash_amount`。
- `gift_total`：同范围成功调增单的赠送额度。
- `adjustment_in_total`：成功正向调额合计。
- `adjustment_out_total`：成功负向调额绝对值合计。
- `adjustment_net_total`：有符号调额净额。
- 实收入账用户榜只按 `cash_amount` 排序。

### 用量

- Token 等于输入、输出、缓存创建、缓存读取四类 Token 之和。
- 用户实际消费榜按官方 `actual_cost`。
- 用户 Token 榜与用户实际消费榜分开。
- 模型固定为 requested 语义，默认按 Token 排序。

### 当前余额

只统计：

```text
role=user
status=active
deleted_at IS NULL
```

该卡片是当前快照，不受首页日期筛选影响。

## 9. 历史账

历史账只读聚合以下远端事件：

- 后台余额调额 `admin_adjustment`
- 余额兑换码 `balance_redeem`
- 已完成且实际改变余额的支付订单 `payment_order`

第一版不包含 `affiliate_balance`。关联状态为：

- `linked`：已由 source ID 或旧记录完整幂等键关联本地单。
- `audit_orphan`：带本审计系统完整标记，但找不到本地记录。
- `external`：非本审计系统产生。

历史页面不得认领、补录、修改或删除远端事件，也不得影响当前实收入账统计。

## 10. 安全边界

- 只有管理员可访问业务接口。
- Sub2API PostgreSQL 使用只读账号，不暴露公网。
- Admin API Key 和数据库口令只放生产密钥配置，不提交仓库。
- 日志不得记录密码、Token、API Key、Authorization 或完整敏感响应。
- 官方响应结构不符合约定时记录安全字段形态并返回稳定错误，不写猜测式兼容。
- 附件不放 public 目录，下载必须经过后端鉴权。
