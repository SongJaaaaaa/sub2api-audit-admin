# Sub2API 审计管理后台项目文档索引

本目录用于管理新项目 `sub2api-audit-admin` 的架构、目录结构、开发计划和模块进度。

默认新项目目录:

```text
/Users/macbook/Desktop/sub2api审计/sub2api-audit-admin
```

## 文档入口

| 文档 | 用途 |
|---|---|
| `architecture.md` | 系统架构、模块边界、数据流、强一致调额链路 |
| `directory-structure.md` | 新项目推荐目录结构和文件责任 |
| `development-progress.md` | 总体开发进度、阶段状态、模块状态索引 |
| `progress.html` | 可视化进度看板，支持浏览器勾选和本地保存 |
| `module-progress-template.md` | 模块进度文档模板 |
| `modules/*.md` | 每个模块的独立进度、交付物、测试和风险 |

## 已确认原则

- 新项目独立落地，不在旧返利系统里继续改。
- 当前 `/Users/macbook/Desktop/分销` 只作为后端抽芯和历史验证参照。
- 后端优先 Laravel 12。
- 前端优先 Vue3 + Vite + TypeScript + Pinia + AntDesignVue，UI 参考 `soybean-admin-antd`。
- 只有管理员角色。
- 调额成功必须等于 Sub2API 真实入账成功。
- 中国时区 `Asia/Shanghai`。
- 金额和额度都保留两位小数。
- Sub2API 汇率 `1:1`。
- 对账要求 `0` 差异。

## 相关设计和计划

- `/Users/macbook/Desktop/sub2api审计/2026-07-05-sub2api-admin-ledger-design.md`
- `/Users/macbook/Desktop/sub2api审计/2026-07-05-sub2api-audit-admin-implementation-plan.md`
