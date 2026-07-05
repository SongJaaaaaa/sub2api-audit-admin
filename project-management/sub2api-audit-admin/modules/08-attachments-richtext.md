# 08 附件和富文本

状态: 已完成

## 1. 目标

为账单、经营账和异常单提供私有附件和安全富文本说明。

## 2. 范围

- 私有附件上传。
- 附件下载鉴权。
- 文件类型限制。
- 富文本过滤。
- 附件审计。

## 3. 依赖

- 03 管理员认证完成。
- 05 强一致额度调整联调验收完成。

## 4. 交付物

- `backend/app/Services/Attachments/AttachmentService.php`
- `backend/app/Support/SafeHtml.php`
- `backend/app/Http/Controllers/Api/V1/AttachmentController.php`
- `frontend/src/components/attachments/AttachmentUploader.vue`
- `frontend/src/components/richtext/SafeRichTextEditor.vue`

## 5. 进度

| 项 | 状态 | 备注 |
|---|---|---|
| 私有存储 | 已完成 | 附件存储到 Laravel `local` 私有盘 |
| 上传接口 | 已完成 | `/attachments` 支持图片和 PDF |
| 下载鉴权 | 已完成 | 下载路由在 Sanctum 鉴权内，前端用 Bearer Token 下载 blob |
| 富文本过滤 | 已完成 | `SafeHtml` 过滤 script、事件属性和 javascript URL |
| 前端组件 | 已完成 | `AttachmentUploader`、`SafeRichTextEditor` |
| 测试 | 已完成 | `AttachmentTest` 已通过 |
| 桌面端验收 | 已完成 | 经营账详情抽屉可上传和下载附件 |
| H5 手机端验收 | 已完成 | 上传按钮和附件列表使用响应式布局 |
| 前后端联通验收 | 已完成 | Feature Test 覆盖上传、私有下载鉴权 |
| 异常路径验收 | 已完成 | 非法文件 422、未登录下载 401、富文本脚本过滤 |

## 6. 验收标准

- 未登录不能下载附件。
- 可上传图片和 PDF。
- 禁止可执行文件。
- 富文本不允许脚本。
- 桌面端可上传、预览、下载附件。
- H5 手机端可上传图片并查看已上传附件。
- 前端必须接真实附件 API。
- 非法文件、未登录下载、富文本脚本注入都必须进入失败路径。

## 7. 测试命令

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/backend
php artisan test --filter=AttachmentTest
```

## 8. 风险

- 凭证图片不能放公开目录。

## 9. 完成记录

- 2026-07-06: 完成附件表、上传/下载接口、私有存储、富文本过滤、前端上传和富文本组件；经营账详情接入附件；`AttachmentTest`、`FinanceLedgerTest`、`pnpm build` 已通过。
