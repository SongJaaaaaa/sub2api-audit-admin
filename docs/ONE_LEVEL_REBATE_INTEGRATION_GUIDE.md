# 新项目一级推广返利接入文档

> 目标：把当前 Sub2Rebate 接入新项目，只保留客户端推广返利，返利层级固定为一级。本文同时说明现有代码位置、目标页面、接口复用范围、必须改造项和样式参考。

## 0. 项目根目录与迁移入口

### 0.1 源项目根目录

本文所有相对路径都从下面这个目录开始：

```text
D:\test项目\分销
```

主要目录：

```text
D:\test项目\分销\
├── backend\       Laravel 返利后端、管理 API、数据库迁移、队列任务
├── frontend\      Vue 3 客户端和管理端页面
├── docs\          接口、模块和本接入文档
├── deploy\        现有部署与 Sub2API 安全检查资料，不作为源码迁移入口
└── stitch_sub2rebate_affiliate_management_system\
                    页面效果稿、HTML 和设计规范
```

不要从 `deploy/*.tar.gz` 或 `deploy/.tmp-*` 复制源码。这些是旧发布快照，可能落后于当前工作区。迁移必须以根目录下的 `backend\` 和 `frontend\` 为准。

### 0.2 推荐的新项目目录结构

假设另一个项目根目录是：

```text
D:\你的新项目
```

推荐组织为：

```text
D:\你的新项目\
├── services\
│   └── rebate-api\              独立返利后端，来源为 D:\test项目\分销\backend
├── src\                          新项目原有前端源码
│   ├── api\                      合并返利 API client
│   ├── views\                    合并保留的返利页面
│   ├── stores\                   合并返利 Pinia store
│   ├── components\               合并返利公共组件
│   ├── types\                    合并返利类型
│   └── styles\                   合并或映射设计 Token
└── docs\
    └── rebate-integration.md     可复制本文作为新项目交接文档
```

返利后端保持独立数据库、独立 Redis 队列和独立环境变量。新项目通过 `/api/v1` 调用它。除非另一个项目本身也是 Laravel 12 且确认要共用一套数据库，否则不要把 `backend/app/Modules/*` 直接混进另一个 Laravel 应用。

### 0.3 后端怎么迁移

后端应整体迁移，源目录和目标目录如下：

```text
源：D:\test项目\分销\backend
目标：D:\你的新项目\services\rebate-api
```

需要迁移：

```text
backend\app\
backend\bootstrap\
backend\config\
backend\database\migrations\
backend\database\seeders\
backend\public\
backend\routes\
backend\storage\                     只保留目录结构和各级 .gitignore
backend\tests\
backend\artisan
backend\composer.json
backend\composer.lock
backend\phpunit.xml
backend\.env.example
backend\.env.testing
```

不要迁移：

```text
backend\.env                         包含当前环境配置和密钥
backend\vendor\                      目标目录重新安装依赖
backend\storage\logs\               运行日志
backend\storage\framework\**\*.tmp  测试和视图临时文件
backend\storage\framework\cache\*.php
                                      运行时生成缓存，只保留 .gitignore
backend\bootstrap\cache\*.php       环境生成缓存
backend\database\*.sqlite*           本地测试数据库和备份
backend\.phpunit.result.cache         测试缓存
```

迁移后先在 `services\rebate-api` 内完成本文的一级化代码改造，再安装依赖、生成新项目自己的 `.env`、运行数据库迁移和测试。当前 `backend\` 仍是多级版本，不能原封不动上线成一级版本。

后端最低依赖来自 `backend/composer.json`：

```text
PHP 8.2+
Laravel 12
Laravel Sanctum 4
Filament 3
PostgreSQL
Redis
```

### 0.4 Vue 前端怎么迁移

如果另一个项目也是 Vue 3，可以把下面文件合并到它现有的 `src` 目录，保持相同相对路径可以减少 import 修改。

页面：

```text
frontend\src\views\auth\LoginView.vue
frontend\src\views\dashboard\DashboardView.vue
frontend\src\views\promotion\MyRelationshipView.vue
frontend\src\views\promotion\PromotionView.vue
frontend\src\views\rebate\RebateRecordsView.vue
frontend\src\views\withdraw\WithdrawView.vue
frontend\src\views\admin\AdminDashboardView.vue
frontend\src\views\admin\AdminRelationshipView.vue
frontend\src\views\admin\AdminWithdrawalsView.vue
frontend\src\views\admin\AdminRebateConfigView.vue
```

页面依赖：

```text
frontend\src\api\admin.ts
frontend\src\api\auth.ts
frontend\src\api\dashboard.ts
frontend\src\api\promotion.ts
frontend\src\api\rebate.ts
frontend\src\api\withdraw.ts
frontend\src\components\common\
frontend\src\components\layout\
frontend\src\layouts\
frontend\src\stores\auth.ts
frontend\src\stores\dashboard.ts
frontend\src\stores\promotion.ts
frontend\src\stores\withdraw.ts
frontend\src\types\
frontend\src\utils\
frontend\src\mocks\                 当前 API 文件有静态 import；不需要 Mock 时可在迁移后一起删掉 Mock 分支
frontend\src\constants\pagination.ts
frontend\src\styles\
```

不能直接覆盖，需要人工合并：

```text
frontend\src\router\routes.ts         把保留页面加入新项目现有路由
frontend\src\router\index.ts          合并登录和管理员路由守卫
frontend\src\main.ts                  合并 Pinia、Element Plus 和样式入口
frontend\src\App.vue                  保留新项目自己的根组件
frontend\package.json                  只合并缺少的依赖
frontend\vite.config.ts                合并 @ 别名和 /api 开发代理
frontend\tailwind.config.js            合并扫描路径和设计色值
```

新项目缺少时需要合并的前端依赖：

```text
vue
vue-router
pinia
axios
element-plus
@element-plus/icons-vue
tailwindcss
```

如果另一个项目不是 Vue，不迁移 `frontend\src`。直接按第 7 节接口重新实现页面，样式参考第 8 节截图和设计 Token。

### 0.5 最小接入与完整迁移的区别

只想让另一个项目使用已经部署的返利系统：

```text
不用复制 backend
-> 新项目接入登录/SSO
-> 调用 https://返利域名/api/v1
-> 按第 7 节实现或迁移页面
```

想让另一个项目仓库独立拥有并部署这套返利系统：

```text
复制整个 backend 到 services\rebate-api
-> 合并选定 frontend 页面和依赖
-> 按本文完成一级化改造
-> 使用新的数据库、Redis、域名和环境变量部署
```

### 0.6 迁移完成后的第一批检查

1. `services\rebate-api\artisan` 存在，`composer.json` 能正常安装。
2. 所有迁移文件都在 `services\rebate-api\database\migrations`，没有复制 SQLite 文件。
3. 新 `.env` 使用新项目的返利数据库，Sub2API 数据库账号仍为只读。
4. 新项目前端 `VITE_API_BASE_URL` 指向新返利后端 `/api/v1`。
5. 前端只注册第 1.2 节列出的页面。
6. 后端完成一级返利、按账号查推荐关系、Sub2API 审核提现三个改造后才能上线。

## 1. 目标口径

### 1.1 一级返利定义

- A 邀请 B，B 的有效充值只给 A 返利。
- B 邀请 C，C 的有效充值只给 B 返利，A 不从 C 获得返利。
- 邀请关系仍允许形成 A -> B -> C 的结构，便于归因和审计，但计算时永远只读取直接上级。
- 客户端团队数据只统计直接下级，不再统计全部后代。
- 返利流水中的推广奖励层级固定为 `level = 1`。

### 1.2 保留页面

管理端只在导航中保留：

| 页面 | 路由 | 说明 |
|---|---|---|
| 数据看板 | `/admin/dashboard` | 平台用户、直接推荐、返利、待审核提现等汇总 |
| 推荐关系 | `/admin/relationships` | 初始不加载关系树，先搜索并选择账号，再查看该账号和直接下级 |
| 提现审核 | `/admin/withdrawals` | 只审核转入 Sub2API 额度的申请 |
| 返利配置 | `/admin/rebate-config` | 只保留里程碑、提现、Sub2API 余额来源开关 |

客户端只在导航中保留：

| 页面 | 路由 | 说明 |
|---|---|---|
| 仪表盘 | `/dashboard` | 返利余额、一级团队、返利趋势和最近记录 |
| 我的团队 | `/my-team` | 当前用户和直接下级，不展示更深层级 |
| 推广中心 | `/promotion` | 邀请链接、直接邀请数、直接下级转化 |
| 返利明细 | `/rebate/records` | 一级推广返利和里程碑奖励流水 |
| 提现管理 | `/withdraw` | 只允许申请转入当前账号对应的 Sub2API 额度 |

`/login` 是认证入口，不计入业务导航，但必须保留。

### 1.3 明确移除

- 多级衰减、最大返利深度、失效节点重算、多级实时预览。
- 用户个性化多级返利设置和管理端 `/admin/user-rebate` 页面。
- 支付宝提现、提现账号绑定、支付宝打款和“标记已打款”。
- 客户端额度充值、账户设置等不在本次范围内的导航入口。
- 充值赠送、风控等配置不在精简后的返利配置页展示。
- `InviteFirstRechargeBonusService` 当前发给被邀请人本人，不属于推广员返利；新项目默认关闭，除非另行确认保留。

## 2. 推荐接入架构

建议继续把 Sub2Rebate 作为独立返利服务，新项目前端通过 HTTP API 使用它，不要把金额计算散落到新项目业务代码中。

```text
新项目客户端 / 管理端
        |
        | Bearer Token + /api/v1
        v
Sub2Rebate Laravel API
        |
        +-- 本地 PostgreSQL：关系、事件、返利、余额、提现、审计
        +-- Redis：队列、缓存、会话
        +-- Sub2API 只读库：用户、邀请归因、充值/兑换事件
        +-- Sub2API Admin API：审核通过后增加用户 API 额度
```

本地用户 ID 继续直接复用 Sub2API `users.id`。这样登录、邀请人、提现目标账号和 Sub2API 额度调整都使用同一个稳定 ID，不需要额外账号映射表。

## 3. 返利规则

### 3.1 配置页目标结构

原页面 `frontend/src/views/admin/AdminRebateConfigView.vue` 中的“里程碑配置”和“多级返利配置”需要合并为一个“里程碑配置”卡片：

| 页面字段 | 配置键 | 用途 |
|---|---|---|
| 初始累充门槛 | `milestone.amount` | 新用户累计充值每达到一次该值，触发一次初始奖励 |
| 初始每次奖励 | `milestone.reward_amount` | 初始阶段每次给直接上级的奖励 |
| 初始最多奖励次数 | `milestone.max_times` | 同一直接下级最多触发次数 |
| 下级累充返利门槛 | `rebate.stage_amount` | 初始阶段结束后，每新增累计充值达到该值触发一次返利 |
| 每次分配奖励池 | `rebate.stage_reward_amount` | 每次触发时全部发给直接上级，不再分池 |

需要从页面和保存 payload 中删除：

- `rebate.decay_factor`
- `rebate.max_depth`
- `rebate.inactive_node_mode`
- 多级开关、层级预览和用户多级覆盖配置

“Sub2API 余额调整开关”继续保留现有三个来源开关：

| 开关 | 配置键 |
|---|---|
| Sub2API 原生充值参与返利 | `rebate.sub2api_native_recharge_enabled` |
| Sub2API 后台调额参与返利 | `rebate.sub2api_admin_adjust_enabled` |
| Sub2API 兑换参与返利 | `rebate.sub2api_redeem_enabled` |

这些开关控制扫描到的外部额度事件是否进入返利事件队列，不是提现开关。

### 3.2 计算示例

假设配置为：

```text
milestone.amount = 100
milestone.reward_amount = 15
milestone.max_times = 2
rebate.stage_amount = 100
rebate.stage_reward_amount = 15
```

B 是 A 的直接下级：

| B 累计充值 | A 获得 | 原因 |
|---:|---:|---|
| 100 | 15 | 第一次里程碑 |
| 200 | 15 | 第二次里程碑 |
| 300 | 15 | 完成里程碑后，第一个累充返利台阶 |
| 400 | 15 | 第二个累充返利台阶 |

若 C 是 B 的直接下级，C 的充值只给 B，不给 A。

### 3.3 后端改造位置

当前处理链：

```text
RechargeEventService
-> ProcessRebateEventJob
-> MilestoneService
-> DecayRebateService
-> InviteFirstRechargeBonusService
```

目标处理链：

```text
RechargeEventService
-> ProcessRebateEventJob
-> MilestoneService
-> DirectRebateService
```

对应文件：

| 职责 | 现有文件 | 目标处理 |
|---|---|---|
| 统一充值事件 | `backend/app/Modules/Payment/Services/RechargeEventService.php` | 直接复用，保留 `source_type + source_id` 幂等 |
| 队列编排 | `backend/app/Jobs/ProcessRebateEventJob.php` | 将 `DecayRebateService` 替换为 `DirectRebateService`；默认不调用首充本人奖励 |
| 初始里程碑 | `backend/app/Modules/Milestone/Services/MilestoneService.php` | 复用，只奖励 `parent_user_id` |
| 后续返利 | `backend/app/Modules/Rebate/Services/DecayRebateService.php` | 新项目建议抽成 `DirectRebateService`，只取直接上级并发放完整奖励池 |
| 邀请关系 | `backend/app/Modules/Invite/Services/InviteService.php` | 保留归因和关系写入；新增/复用直接上级查询，不读取完整返利上级链 |
| 余额入账 | `backend/app/Modules/Rebate/Services/RebateBalanceService.php` | 直接复用 |

最快的兼容做法是把 `rebate.max_depth` 固定为 1。当前衰减服务在只有一个接收人时会把整个奖励池发给一级上级。但新项目推荐改成独立的 `DirectRebateService`，避免以后配置被误改后重新出现多级返利，也让业务含义更直接。

一级上级无返利资格时，本次奖励不向更上级转移，直接不发放并写审计记录。

## 4. 账号与推荐关系接入

### 4.1 账号来源

现有认证已经复用 Sub2API：

- 登录接口：`POST /api/v1/auth/login`
- 当前用户：`GET /api/v1/auth/me`
- 前端 Token：`localStorage.sr_token`
- 请求头：`Authorization: Bearer <token>`
- 后端认证服务：`backend/app/Modules/Auth/Services/Sub2RebateAuthService.php`
- Sub2API 用户读取：`backend/app/Modules/Sub2Api/Repositories/Sub2ApiUserRepository.php`

新项目前端只需把 `VITE_API_BASE_URL` 指向返利后端，例如：

```env
VITE_API_BASE_URL=https://rebate-api.example.com/api/v1
VITE_USE_MOCK=false
```

现有 `POST /api/v1/auth/login` 只接受 Sub2API 账号密码，不能直接拿新项目已有 Token 换取 Sanctum Token。接入时二选一：

- 新项目登录页同时使用 Sub2API 账号密码调用该接口，直接复用现有实现。
- 新项目已有独立登录态时，新增服务端签名的 SSO Token 交换接口，由两个后端确认 `userId` 后签发 Sanctum Token。

不要把 Sub2API Admin API Key 或 SSO 签名密钥下发到浏览器。

### 4.2 推荐关系来源

关系事实来源为 Sub2API `user_affiliates.inviter_id`：

```text
Sub2API 注册携带 aff_code
-> user_affiliates.inviter_id
-> InviteService::syncFromSub2Api()
-> 本地 referral_paths.parent_user_id
```

本地 `referral_paths` 可以继续保存完整结构链，一级限制只作用于查询统计和返利计算。不要通过删除祖先路径来实现一级返利，否则会破坏历史归因和审计。

### 4.3 管理端推荐关系必须先选账号

当前 `AdminRelationshipView.vue` 会在 `onMounted` 时无参数调用 `getRelationshipTree()`，后端也允许无 `userId` 时返回多个根节点。目标行为应改为：

1. 首次进入只显示账号搜索框和空状态，不请求关系树。
2. 输入用户名、邮箱或用户 ID，调用 `GET /api/v1/admin/users?keyword=...&pageSize=10`。
3. 选择一名用户后，调用 `GET /api/v1/admin/relationship-tree?userId={id}&maxDepth=1`。
4. 后端把 `userId` 改为必填；缺失时返回 422，不再全量查根节点。
5. 响应只包含所选用户和直接下级，节点数量再大也不会首次加载全站关系。

改造文件：

- `frontend/src/views/admin/AdminRelationshipView.vue`
- `frontend/src/api/admin.ts` 的 `getRelationshipTree`
- `backend/app/Http/Controllers/Api/V1/Admin/AdminRelationshipController.php`
- `backend/app/Modules/Invite/Services/InviteService.php`

客户端“我的团队”仍以当前登录用户为强制根节点：

```http
GET /api/v1/invite/tree?maxDepth=1
```

后端不能接受客户端传入其他根用户 ID。

## 5. 充值事件怎么接入

### 5.1 新项目仍使用同一个 Sub2API

直接复用现有扫描方案：

| 来源 | Sub2API 数据 | 本地开关 |
|---|---|---|
| 原生充值 | `payment_orders` 已完成余额订单 | `rebate.sub2api_native_recharge_enabled` |
| 后台调额 | `redeem_codes` 中后台余额类型记录 | `rebate.sub2api_admin_adjust_enabled` |
| 兑换 | `redeem_codes` 已使用记录 | `rebate.sub2api_redeem_enabled` |

扫描服务和命令：

- `backend/app/Modules/Sub2Api/Services/Sub2ApiRechargeScannerService.php`
- `backend/app/Console/Commands/ScanSub2ApiRechargeEventsCommand.php`
- `php artisan sub2api:scan-recharge-events --limit=100`
- Scheduler 当前每 5 分钟运行一次，队列随后处理返利事件。

`users.balance` 会因充值、消费、退款和管理员调额变化，不能只监控余额差值发返利。

### 5.2 充值发生在新项目自己的订单表

当前仓库没有给任意外部项目调用的通用充值事件 HTTP 接口。此场景需要新增服务端接口，再复用 `RechargeEventService::createRechargeEvent()`：

```http
POST /api/v1/integration/recharge-events
X-Client-Id: new-project
X-Timestamp: 1784044800
X-Signature: <HMAC-SHA256>
Content-Type: application/json

{
  "userId": 1001,
  "sourceType": "new_project.order",
  "sourceId": "ORDER-20260715-0001",
  "amount": "100.00",
  "currency": "CNY",
  "occurredAt": "2026-07-15 14:00:00",
  "remark": "订单支付成功"
}
```

`sourceType + sourceId` 必须全局唯一。支付平台重复回调时返回同一个事件，不允许重复发奖。签名密钥只放两个服务端，不能进入前端代码。

## 6. 提现只到 Sub2API

### 6.1 目标流程

用户所说的“提现”在新项目中统一表示“把返利余额转入本人 Sub2API API 额度”：

```text
用户提交申请
-> 锁定并冻结返利余额
-> withdraw_records 写 pending / api_quota
-> 管理员审核
   -> 拒绝：解冻并退回可用返利余额
   -> 通过：状态改 processing，在事务外调用 Sub2API Admin API
      -> 成功：扣除冻结余额，累计 withdrawn_amount，记录 paid
      -> 失败：保留可重试状态和错误，不重复扣款
```

当前 `POST /api/v1/withdraw/to-api-quota` 是即时转入：用户提交后直接调用 Sub2API，并不经过管理端审核。因此它不能原样用于目标流程。

### 6.2 接口调整

推荐保留现有路径，改变为“创建待审核申请”：

```http
POST /api/v1/withdraw/to-api-quota

{
  "amount": "50.00",
  "remark": "转入 API 额度"
}
```

成功响应的记录状态应为 `pending`，不再立即返回已经增加后的 Sub2API 余额。

管理端：

| 接口 | 目标行为 |
|---|---|
| `GET /api/v1/admin/withdrawals` | 只列出或默认筛选 `type=api_quota` |
| `POST /api/v1/admin/withdrawals/{id}/approve` | 审核并调用 Sub2API 加额，成功后直接完成 |
| `POST /api/v1/admin/withdrawals/{id}/reject` | 解冻返利余额并拒绝 |
| `POST /api/v1/admin/withdrawals/{id}/paid` | 新项目删除，或仅保留历史兼容但不在页面显示 |

后端改造位置：

- `backend/app/Modules/Withdraw/Services/WithdrawService.php`
- `backend/app/Modules/Admin/Services/AdminWithdrawService.php`
- `backend/app/Modules/Sub2Api/Services/Sub2ApiAdminClient.php`
- `backend/app/Modules/Withdraw/Models/WithdrawRecord.php`
- `backend/app/Http/Controllers/Api/V1/Admin/AdminWithdrawController.php`

前端改造位置：

- `frontend/src/views/withdraw/WithdrawView.vue`：删除支付宝 Tab 和账号绑定提示，只保留 Sub2API 额度申请。
- `frontend/src/api/withdraw.ts`：删除支付宝账号和普通提现调用，只保留配置、申请、记录。
- `frontend/src/stores/withdraw.ts`：提交后按待审核记录处理。
- `frontend/src/views/admin/AdminWithdrawalsView.vue`：账户列改为 Sub2API 用户，审核通过即执行加额，不显示“标记已打款”。

### 6.3 提现配置保留项

精简后的“提现配置”建议保留：

| 配置键 | 说明 |
|---|---|
| `withdraw.min_amount` | 单次最低转入金额 |
| `withdraw.api_quota_daily_limit` | 每日申请次数，0 表示不限 |
| `withdraw.api_quota_daily_amount_limit` | 每日申请总金额，0 表示不限 |
| `withdraw.to_api_quota_rate` | 返利余额到 API 额度换算比例 |
| `withdraw.review_mode` | 固定为 `manual`，页面无需提供切换 |

`withdraw.to_api_quota_enabled` 在“只能提现到 Sub2API”的产品中可以固定开启并从页面隐藏，避免管理员关闭后客户端没有任何提现方式。

## 7. 页面与接口位置

### 7.1 客户端

| 页面 | Vue 文件 | 前端 API | 后端接口 | 后端入口 |
|---|---|---|---|---|
| 仪表盘 | `frontend/src/views/dashboard/DashboardView.vue` | `frontend/src/api/dashboard.ts` | `GET /dashboard/summary`、`/dashboard/rebate-trends`、`/dashboard/recent-activities` | `DashboardController.php` |
| 我的团队 | `frontend/src/views/promotion/MyRelationshipView.vue` | `getInviteTree` | `GET /invite/tree?maxDepth=1` | `InviteController.php` -> `InviteService.php` |
| 推广中心 | `frontend/src/views/promotion/PromotionView.vue` | `frontend/src/api/promotion.ts` | `GET /promotion/summary`、`/promotion/conversions`、`/invite/records` | `PromotionController.php`、`InviteController.php` |
| 返利明细 | `frontend/src/views/rebate/RebateRecordsView.vue` | `frontend/src/api/rebate.ts` | `GET /rebate/records` | `RebateRecordController.php` |
| 提现管理 | `frontend/src/views/withdraw/WithdrawView.vue` | `frontend/src/api/withdraw.ts` | `GET /withdraw/config`、`POST /withdraw/to-api-quota`、`GET /withdraw/records` | `WithdrawController.php` -> `WithdrawService.php` |

上述接口完整地址都带 `/api/v1` 前缀。

一级口径需要同步修改：

- `DashboardController::summary()`：`teamInviteCount` 改为直接下级数，或删除该字段只保留 `directInviteCount`。
- `PromotionController::summary()`：团队数、转化数只查询 `parent_user_id = 当前用户 ID`。
- `PromotionController::conversions()`：只允许直接下级付款人。
- `InviteService::tree()`：客户端最大深度固定为 1，不信任前端传入更大值。
- 页面文案删除“多级返利”“团队全部层级”等表述。

### 7.2 管理端

| 页面 | Vue 文件 | 前端 API | 后端接口 | 后端入口 |
|---|---|---|---|---|
| 数据看板 | `frontend/src/views/admin/AdminDashboardView.vue` | `getAdminDashboard`、`getAdminTrends` | `GET /admin/dashboard`、`GET /admin/trends` | `AdminDashboardController.php` |
| 推荐关系 | `frontend/src/views/admin/AdminRelationshipView.vue` | `getAdminUsers`、`getRelationshipTree` | `GET /admin/users`、`GET /admin/relationship-tree` | `AdminUserController.php`、`AdminRelationshipController.php` |
| 提现审核 | `frontend/src/views/admin/AdminWithdrawalsView.vue` | `getAdminWithdrawals`、`approveWithdraw`、`rejectWithdraw` | `/admin/withdrawals*` | `AdminWithdrawController.php` -> `AdminWithdrawService.php` |
| 返利配置 | `frontend/src/views/admin/AdminRebateConfigView.vue` | `getFullRebateConfig`、`saveFullRebateConfig` | `GET/PUT /admin/rebate-config` | `AdminConfigController.php` -> `ConfigService.php` |

路由和导航位置：

- `frontend/src/router/routes.ts`
- `frontend/src/components/layout/SideNav.vue`
- `frontend/src/components/layout/AdminSideNav.vue`
- `backend/routes/api.php`

### 7.3 核心数据表

| 表 | 用途 |
|---|---|
| `users` | Sub2API 用户本地快照，ID 与 Sub2API 一致 |
| `referral_paths` | 直接上级和历史结构路径 |
| `payment_records` | 已确认的充值事实 |
| `rebate_events` | 待处理/处理中/已处理的幂等返利事件 |
| `user_rebate_progress` | 每个下级的累计充值和里程碑次数 |
| `rebate_records` | 返利明细 |
| `rebate_balances` | 可用、冻结、已提现返利余额 |
| `withdraw_records` | 转入 Sub2API 的申请和处理状态 |
| `config_items` | 返利、提现和来源开关配置 |
| `audit_logs` | 管理员审核、配置变更和资金动作审计 |

## 8. 样式参考

当前项目已经有可直接复用的 Vue 页面、设计 Token 和效果稿，不需要重新发明一套后台样式。

### 8.1 设计 Token

- `frontend/src/styles/tokens.css`
- `frontend/src/styles/index.css`
- `frontend/src/styles/element-plus.css`
- `frontend/tailwind.config.js`
- `stitch_sub2rebate_affiliate_management_system/precision_rebate_intelligence/DESIGN.md`

核心视觉：浅灰页面背景、白色内容面、深色正文、靛蓝交互色、绿色成功态、红色风险态；表格保持紧凑，状态使用 Tag，不使用大面积装饰。

### 8.2 页面效果稿

| 页面 | 截图 | HTML 参考 |
|---|---|---|
| 管理端数据看板 | `stitch_sub2rebate_affiliate_management_system/admin_dashboard_sub2rebate/screen.png` | 同目录 `code.html` |
| 管理端推荐关系 | `stitch_sub2rebate_affiliate_management_system/referral_visualization_sub2rebate_admin/screen.png` | 同目录 `code.html` |
| 管理端返利配置 | `stitch_sub2rebate_affiliate_management_system/sub2rebate_admin_1/screen.png` | 同目录 `code.html` |
| 管理端提现审核 | `stitch_sub2rebate_affiliate_management_system/sub2rebate_admin_5/screen.png` | 同目录 `code.html` |
| 客户端仪表盘 | `stitch_sub2rebate_affiliate_management_system/user_dashboard_sub2rebate/screen.png` | 同目录 `code.html` |
| 客户端推广中心 | `stitch_sub2rebate_affiliate_management_system/promotion_center_sub2rebate_user/screen.png` | 同目录 `code.html` |
| 客户端提现 | `stitch_sub2rebate_affiliate_management_system/withdraw_management_interactive_sub2rebate_user/screen.png` | 同目录 `code.html` |

使用效果稿时要做一级化改文案：推广中心删除 L2-L5 和“multi-level”描述；推荐关系只画根节点和一级下级；提现页删除支付宝账户信息，改成 Sub2API 用户及预计到账额度。

## 9. 环境配置

后端至少需要：

```env
APP_URL=https://rebate-api.example.com
FRONTEND_URL=https://new-project.example.com
CORS_ALLOWED_ORIGINS=https://new-project.example.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sub2rebate
DB_USERNAME=sub2rebate
DB_PASSWORD=<local-db-password>

SUB2API_DB_HOST=127.0.0.1
SUB2API_DB_PORT=5432
SUB2API_DB_DATABASE=sub2api
SUB2API_DB_USERNAME=sub2rebate_ro
SUB2API_DB_PASSWORD=<read-only-password>

SUB2API_BASE_URL=https://api.example.com
SUB2API_ADMIN_API_KEY=<server-only-key>
SUB2API_INVITE_URL_TEMPLATE=https://api.example.com/register?aff={code}
SUB2API_AFFILIATE_PAGE_URL=https://api.example.com/affiliate

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

生产环境要求：

- `SUB2API_DB_USERNAME` 必须是只读账号。
- `SUB2API_ADMIN_API_KEY` 只允许返利后端使用，限制来源 IP 和最小接口权限。
- 队列 Worker 必须常驻，Scheduler 必须每分钟执行。
- 部署后执行迁移和配置种子，确认 `config_items` 包含本文列出的配置键。
- 跨域只放行新项目真实域名。

## 10. 建议实施顺序

1. 精简前端路由和两侧导航，只留下本文页面。
2. 把返利计算固定为直接上级，补 `DirectRebateService` 和金额测试。
3. 把仪表盘、推广汇总、转化记录和团队树统一改成直接下级口径。
4. 改管理端推荐关系页：必须选账号，后端强制 `userId`，只返回一级。
5. 合并返利配置页，删除多级字段和用户多级覆盖入口。
6. 将 Sub2API 即时转入改为待审核申请，管理端审核通过后执行加额。
7. 接入 Sub2API 扫描器，或新增新项目充值事件接口。
8. 做历史数据兼容和完整回归，再切换新项目域名。

## 11. 验收清单

- A -> B -> C 场景中，B 充值只给 A，C 充值只给 B。
- A 的仪表盘、推广中心和我的团队都不统计 C。
- 所有新推广返利流水 `level = 1`，奖励总额等于配置的单次奖励池。
- 管理端推荐关系首次进入不发树查询；选择账号后才请求，并且只返回直接下级。
- 客户端没有支付宝提现入口，没有提现账号绑定入口。
- 用户提交转入 Sub2API 后状态为待审核，Sub2API 余额此时不变。
- 管理员拒绝后冻结余额全部退回。
- 管理员通过后 Sub2API 只加一次；重复点击、接口重试不会重复加额。
- Sub2API 调用失败时本地资金不丢失，记录错误并可重试。
- 同一个充值 `sourceType + sourceId` 重复上报不会重复返利。
- 普通用户不能访问 `/api/v1/admin/*`，浏览器中不存在 Sub2API Admin API Key。
- 桌面端和移动端均完成页面、空状态、加载态和长文本检查。

## 12. 当前实现与目标差异汇总

| 项目 | 当前实现 | 新项目目标 |
|---|---|---|
| 正常返利 | 最多按 `rebate.max_depth` 多级衰减 | 只给直接上级 |
| 推广统计 | 查询完整后代树 | 只统计直接下级 |
| 管理推荐关系 | 首次进入会加载默认树 | 必须先搜索选择账号 |
| 客户团队树 | 可请求多层 | 后端固定一级 |
| 返利配置 | 里程碑、多级、充值赠送、提现、风控 | 里程碑合并后续门槛、提现、Sub2API 来源开关 |
| 提现方式 | 支付宝审核 + Sub2API 即时转入 | 仅 Sub2API，且必须审核 |
| 提现完成 | 审批后再标记打款 | 审批成功即完成 Sub2API 入账 |
| 首充本人奖励 | 默认可能启用 | 默认关闭，不属于推广返利 |

这份差异表是改造边界。只隐藏菜单不能达到目标，后端查询、计算和提现状态机必须同步修改。
