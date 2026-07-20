# 部署配置

| 文件 | 说明 |
|---|---|
| `Caddyfile.example` | 前端静态资源和 Laravel API 反向代理示例 |

部署时必须根据真实目录、PHP 路径和运行用户调整示例。Sub2API PostgreSQL 必须使用只读账号，不得暴露公网，也不得从本系统直接写入。

完整维护、迁移、构建和验收顺序见：

```text
docs/deployment.md
```
