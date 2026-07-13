# Sub2API 审计管理后台项目文档索引

本目录用于维护 `sub2api-audit-admin` 的架构、模块边界、开发进度和上线验收记录。

项目目录：

```text
/Users/macbook/Desktop/sub2api审计/sub2api-audit-admin
```

## 文档入口

| 文档 | 用途 |
|---|---|
| `architecture.md` | 三类数据源、调额、切账、历史账和对账架构 |
| `directory-structure.md` | 项目目录结构和文件责任 |
| `development-progress.md` | 总体开发进度和下一步动作 |
| `progress.html` | 可视化进度看板，支持本地勾选保存 |
| `module-progress-template.md` | 模块进度文档模板 |
| `modules/*.md` | 各模块交付物、测试和风险 |

## 当前统一原则

- 项目独立于旧返利系统运行。
- 后端使用 Laravel 12，前端使用 Vue 3、Vite、TypeScript、Pinia 和 Ant Design Vue。
- 本地审计主库默认使用 SQLite；Sub2API PostgreSQL 只读，禁止直接改远端余额或业务表。
- 调额只能调用 Sub2API 官方 Admin API，并在二次确认后标记成功。
- 统计数据源严格分开：
  - 本地账本负责实收、赠送、调增、调减、净额和实收入账用户榜。
  - 官方 Admin API 负责请求、Token、标准消费、实际消费、用户排行和 requested 模型统计。
  - 只读 PostgreSQL 负责当前余额、历史余额事件、远端事件关联和真实对账。
- 中国业务时区固定为 `Asia/Shanghai`，日期范围统一使用包含首尾日期的自然日口径。
- 切账时间首次写入后永久锁定；切账前记录只进入历史模块。
- 对账状态为 `ok | warning | error`。外部事件和审计孤儿只告警，不自动认领或补录。
- 密码、数据库口令、API Key 和 Authorization 不得进入代码、文档、日志或提交记录。

## 相关设计和计划

- `/Users/macbook/Desktop/sub2api审计/2026-07-05-sub2api-admin-ledger-design.md`
- `/Users/macbook/Desktop/sub2api审计/2026-07-05-sub2api-audit-admin-implementation-plan.md`
