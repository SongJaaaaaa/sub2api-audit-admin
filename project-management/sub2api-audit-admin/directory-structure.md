# Sub2API 审计管理后台目录结构设计

默认新项目根目录:

```text
/Users/macbook/Desktop/sub2api审计/sub2api-audit-admin
```

## 1. 顶层目录

```text
sub2api-audit-admin/
  backend/
  frontend/
  docs/
  deploy/
  README.md
```

| 目录 | 说明 |
|---|---|
| `backend` | Laravel API 后端 |
| `frontend` | soybean-admin-antd 风格管理前端 |
| `docs` | 新项目自己的文档和上线手册 |
| `deploy` | 部署脚本、Caddy/Nginx、Docker 配置 |

## 2. 后端目录

```text
backend/app/
  Http/Controllers/Api/V1/
  Models/
  Services/
    Auth/
    Sub2Api/
    Ledger/
    Stats/
    Reconcile/
    Attachments/
    Audit/
  Support/
backend/config/
backend/database/migrations/
backend/routes/api.php
backend/tests/Feature/
```

## 3. 后端文件责任

| 文件/目录 | 责任 |
|---|---|
| `Services/Sub2Api/Sub2ApiReadRepository.php` | 只读查询 Sub2API PostgreSQL |
| `Services/Sub2Api/Sub2ApiAdminClient.php` | 调 Sub2API Admin API |
| `Services/Ledger/LedgerAdjustmentService.php` | 调额主流程，保证成功口径 |
| `Services/Ledger/Sub2ApiBalanceVerifier.php` | 调额后二次确认 |
| `Services/Ledger/LedgerNumberService.php` | 生成业务单号和幂等键 |
| `Services/Stats/DashboardStatsService.php` | 首页统计聚合 |
| `Services/Reconcile/ReconcileService.php` | 对账批次和差异计算 |
| `Services/Attachments/AttachmentService.php` | 私有附件上传和下载 |
| `Services/Audit/AuditLogService.php` | 操作审计 |
| `Support/Money.php` | 两位小数金额处理 |
| `Support/ChinaTime.php` | 中国时区范围计算 |
| `Support/SafeHtml.php` | 富文本安全过滤 |
| `Support/Sub2ApiNoteTag.php` | Sub2API notes 标签读写 |

## 4. 前端目录

```text
frontend/src/
  api/
  assets/
  components/
    table/
    filters/
    ledger/
    attachments/
    richtext/
  layouts/
  router/
  stores/
  styles/
  utils/
  views/
    DashboardView.vue
    UsersQuotaView.vue
    LedgerAdjustmentListView.vue
    GiftQuotaListView.vue
    OperationExpenseView.vue
    ReconcileView.vue
    ExceptionCenterView.vue
    AuditLogView.vue
    LoginView.vue
```

## 5. 前端文件责任

| 文件/目录 | 责任 |
|---|---|
| `api/http.ts` | Axios 实例、token 注入、统一错误处理 |
| `api/dashboard.ts` | 首页统计 API |
| `api/users.ts` | 用户搜索和详情 |
| `api/ledger.ts` | 调额、记录、赠送额度 |
| `api/reconcile.ts` | 对账批次和差异 |
| `components/table/ColumnSettings.vue` | 表格列显示配置 |
| `components/ledger/AdjustmentForm.vue` | 调额表单 |
| `components/attachments/AttachmentUploader.vue` | 附件上传 |
| `components/richtext/SafeRichTextEditor.vue` | 富文本编辑 |
| `layouts/AdminLayout.vue` | 管理端布局 |
| `stores/auth.ts` | 管理员登录状态 |

## 6. 文档目录

```text
docs/
  architecture.md
  api.md
  database.md
  deployment.md
  dev-checklist.md
  progress.md
  modules/
    backend-foundation.md
    sub2api-integration.md
    ledger-adjustment.md
    dashboard-stats.md
```

新项目内的 docs 用于项目执行期维护；当前 `/Users/macbook/Desktop/sub2api审计/project-management/sub2api-audit-admin` 用于立项前和开发过程总控。

## 7. 命名约定

- 后端类名使用清晰业务名，不缩写到难读。
- 前端变量使用短驼峰。
- 表名使用复数下划线。
- 金额字段统一使用 `*_amount`。
- Sub2API 用户字段统一使用 `sub2api_user_*`。
- 业务单号统一使用 `ledger_no`。
- 状态字段统一使用英文枚举，但页面展示中文。
