# 09 对账中心

状态：已完成

## 1. 目标

按中国业务日期比较本地成功调额与远端后台余额调额事件，保存可追溯批次和差异明细，并支持每天自动运行和同日手动补跑。

余额兑换码和支付订单只用于历史账查询，不参与本地调额对账。

## 2. 对账范围

- 切账前日期不生成当前对账。
- 切账日范围为 `[精确切账时间, 次日 00:00)`。
- 其他日期为完整中国自然日。
- 本地 SQLite 查询使用中国时间边界。
- 远端 PostgreSQL 使用同一边界转换后的 UTC 半开区间。

## 3. 匹配规则

优先级固定为：

1. `sub2api_source_id`
2. 仅兼容旧记录的 `used_by + 完整 idempotency_key`
3. 禁止使用 `ledger_no`

远端 source ID 在本地字段上唯一；重复链接仍由对账识别并产生差异。

## 4. 差异类型

```text
local_missing_remote
remote_external
remote_audit_orphan
user_mismatch
direction_mismatch
amount_mismatch
duplicate_source_link
```

- `remote_external`：非本审计系统产生的后台调额。
- `remote_audit_orphan`：带完整审计标记但找不到本地记录。
- 外部事件和审计孤儿只告警，不自动认领、补录或拆分为现金和赠送。

## 5. 批次与状态

每个批次保存：

- 中国业务日期和实际起止时间。
- 本地成功笔数与调额净额。
- 远端匹配笔数与调额净额。
- 外部事件笔数与净额。
- 审计孤儿笔数与净额。
- 缺失或不一致数量。

状态只有：

```text
ok | warning | error
```

- `ok`：无差异、无外部事件、无审计孤儿。
- `warning`：只有外部事件或审计孤儿等告警。
- `error`：存在本地缺失、用户/方向/金额不一致或重复 source link 等账实错误。

同一业务日期只保留一个批次。手动补跑在事务内重新计算并替换原差异明细，不返回旧版重复批次 `409`，也不重复累加。

## 6. 命令与自动任务

```bash
php artisan ledger:reconcile
php artisan ledger:reconcile --date="YYYY-MM-DD"
```

- 不传日期时处理上一中国自然日。
- 后台手动补跑和命令行调用同一个 `ReconcileService`。
- `backend/routes/console.php` 每天 `00:15 Asia/Shanghai` 调度。
- 启用 `withoutOverlapping`。
- 服务器需每分钟执行一次 `php artisan schedule:run`。

## 7. 交付物

- `backend/app/Services/Reconcile/ReconcileService.php`
- `backend/app/Console/Commands/LedgerReconcileCommand.php`
- `backend/app/Models/ReconciliationBatch.php`
- `backend/app/Models/ReconciliationDiff.php`
- `backend/app/Http/Controllers/Api/V1/ReconcileController.php`
- `backend/routes/console.php`
- `backend/tests/Feature/ReconcileTest.php`
- `deploy/laravel-scheduler.cron.example`
- `frontend/src/api/reconcile.ts`
- `frontend/src/views/ReconcileView.vue`

## 8. 验收标准

- 七类差异均有自动化测试。
- source ID 优先，完整幂等键只兼容旧记录，重复 `ledger_no` 不误关联。
- 切账日精确边界正确，切账前日期不生成当前对账。
- 自动任务默认处理上一自然日。
- 同日手动补跑替换原批次明细，结果幂等。
- 前端正确展示 `ok`、`warning`、`error` 和差异明细。
- warning 可作为已完成但需关注的对账结果，不能用“必须 0 差异才算完成”的旧规则覆盖。

## 9. 测试

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/backend
php artisan test --filter=ReconcileTest
php artisan schedule:list
```
