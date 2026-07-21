# 部署配置

| 文件 | 说明 |
|---|---|
| `Caddyfile.example` | 前端静态资源和 Laravel API 反向代理示例 |
| `laravel-scheduler.cron.example` | 每分钟触发 Laravel Scheduler 的 cron 示例 |

部署时必须根据真实目录、PHP 路径和运行用户调整示例。应用只通过带管理员 Key 的 Sub2API API 访问远端数据，不需要数据库连接信息。

完整维护、迁移、构建和验收顺序见：

```text
docs/deployment.md
```
