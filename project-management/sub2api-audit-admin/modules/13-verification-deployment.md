# 13 联调验收与部署

状态：进行中；本地实现与自动化验证已完成，等待生产切账和真实调额验收

## 1. 目标

完成自动化验证、安全检查、首次生产切账、远端关联回填、Scheduler 安装和真实小额调额闭环验收。

## 2. 当前进度

| 项 | 状态 | 备注 |
|---|---|---|
| 后端全量测试 | 已完成 | 79 passed、689 assertions、1 skipped |
| PHP 语法检查 | 已完成 | app、routes、迁移和 tests 全部通过 |
| 前端生产构建 | 已完成 | Node `22.22.0`，`vue-tsc -b && vite build` 通过 |
| Diff 检查 | 已完成 | `git diff --check` 通过 |
| UTF-8 与敏感信息复核 | 已完成 | 中文文件可正常解码，未发现生产凭据写入工作区 |
| 部署文档 | 已完成 | SQLite、切账、回填、Scheduler、验收和安全边界已更新 |
| 生产 SSH 登录 | 待运维确认 | 不猜测用户名或凭据，不保存登录密码 |
| 官方统计验收 | 待生产执行 | 指定历史日期与官方 Admin API 对齐 |
| 真实调增/调减 | 待生产执行 | 使用小额测试用户 |

## 3. 必跑自动化检查

后端：

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/backend
php artisan test

find app routes database/migrations tests -name '*.php' -print0 \
  | xargs -0 -n1 php -l
```

前端：

```bash
source ~/.nvm/nvm.sh
nvm use 22.22.0
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin/frontend
corepack pnpm build
```

最终工作区：

```bash
cd /Users/macbook/Desktop/sub2api审计/sub2api-audit-admin
git diff --check
git status --short
git diff
```

同时扫描服务器密码、数据库口令、API Key 实值和 Authorization。扫描命中环境变量名或安全说明时必须人工判断，不能误删正常模板。

## 4. 首次上线顺序

1. 记录进入维护模式的精确中国时间并停止本系统调额。
2. 备份 `backend/database/database.sqlite` 和私有附件。
3. 部署代码并执行 `php artisan migrate --force`。
4. 首次执行 `php artisan ledger:cutover --at="YYYY-MM-DD HH:mm:ss"`，核对中国时间和 UTC。
5. 执行 `php artisan ledger:link-sources`，只按用户 ID 和完整幂等键回填。
6. 安装每分钟 `php artisan schedule:run`，用于同步 Sub2API 外部收入。
7. 使用 Node `22.22.0` 构建前端，清理并重建 Laravel 缓存。
8. 恢复服务后执行官方统计、旧账和真实小额调额验收。

详细命令见 `docs/deployment.md`。

## 5. 验收标准

### 官方统计

对 `2026-07-01` 至 `2026-07-09` 查询结果必须与官方 Admin API 一致：

```text
请求数      8,506
Token       969,369,193
actual_cost 965.5262577
```

这些基准只用于生产验收，不得写入业务代码或自动化测试。

### 旧账边界

已有旧账：

```text
实收   100
赠送   100
总调额 200
```

必须保留历史可查，但不得进入切账后当前财务和告警。不得在代码、测试或文档中硬编码生产远端事件 ID。

### 真实调额

分别执行一笔小额调增和调减，确认：

- 官方 API 调额和二次余额确认成功。
- 本地状态、金额拆分和 `sub2api_source_id` 正确。
- 未唯一关联时保留成功但产生告警。
- 首页、历史账和审计日志一致。

## 6. 安全边界

- Sub2API PostgreSQL 只读，不直接写远端数据库。
- 生产 SSH 登录方式由运维确认；认证失败后不继续猜用户名或凭据。
- 服务器密码、数据库密码和 Admin API Key 不得进入代码、测试、文档、日志、截图或最终回复。
- 官方响应结构未知时先记录安全结构化日志，再基于真实输出修正，不写猜测式兼容。
