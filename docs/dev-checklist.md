# 开发与联调检查清单

## 1. 本地启动

后端审计主库默认使用 SQLite：

```bash
cd backend
cp .env.example .env
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8010
```

前端固定使用 Node `22.22.0` 完成验收：

```bash
source ~/.nvm/nvm.sh
nvm use 22.22.0
cd frontend
corepack pnpm install
VITE_API_PROXY_TARGET=http://127.0.0.1:8010 corepack pnpm dev --host 127.0.0.1 --port 5174
```

## 2. 必跑检查

```bash
cd backend
php artisan test

find app routes database/migrations tests -name '*.php' -print0 \
  | xargs -0 -n1 php -l

source ~/.nvm/nvm.sh
nvm use 22.22.0
cd ../frontend
corepack pnpm build
```

需要跑浏览器冒烟时：

```bash
corepack pnpm e2e
```

## 3. 日期与统计验收

- 今天、本周、本月、近 7 天、近 30 天和自定义范围均包含首尾日期。
- 近 7 天等于今天及之前 6 天；近 30 天等于今天及之前 29 天。
- 本地 SQLite 使用中国时间半开区间，远端事件使用对应 UTC 半开区间。
- 切账日账务从精确切账时间开始，不从当天 00:00 开始。
- 官方用量不受切账时间截断。
- Token 包含输入、输出、缓存创建和缓存读取四类。
- 用户实际消费榜和用户 Token 榜为两个独立榜单。
- 模型固定为 requested 语义，指定模型后展示该模型下用户 Token 排行。
- 官方统计失败返回 `502 / SUB2API_STATS_UNAVAILABLE`，前端不显示旧数据或假 0。

## 4. 财务与余额验收

- 实收入账只统计成功调增单的 `cash_amount`。
- 赠送、调增、调减绝对值和调额净额分别正确。
- 失败、异常、作废单不进入财务统计。
- 切账前本地账不进入当前首页财务和告警。
- 当前余额仅统计 `role=user`、`status=active`、`deleted_at IS NULL` 的用户。
- 当前余额卡明确标注为快照，不随首页日期变化。
- 充值用户排行只按现金实收，不混入赠送、外部事件或总调额。

## 5. 调额与远端关联验收

真实调额必须使用小额测试用户：

1. 记录测试用户当前 Sub2API 余额。
2. 从审计系统提交一笔小额调增或调减。
3. 确认官方调额 API 成功且二次余额确认一致。
4. 确认本地状态为 `succeeded`，并写入 `sub2api_source_id`；未唯一匹配时允许成功但必须产生未关联告警。
5. 远端事件关联只能使用 `sub2api_source_id`，或旧记录的“用户 ID + 完整幂等键”。
6. 人为制造重复 `ledger_no` 时不得发生误关联。
7. 检查首页、调额记录、历史账和操作审计。

如官方 API 失败或二次确认不一致，本系统不得显示成功。

## 6. 历史账验收

- 默认 `period=history`，展示切账前 30 天范围。
- `history`、`current`、`all` 与精确切账时间正确求交集。
- 数据源仅包含后台余额调额、余额兑换码、已完成且实际改变余额的余额支付订单。
- 第一版不纳入 `affiliate_balance`。
- 支持用户、关键词、来源、方向、关联状态、时间、分页筛选。
- `linked`、`audit_orphan`、`external` 分类正确。
- CSV 与列表复用同一筛选，忽略分页，首字节包含 UTF-8 BOM，中文无乱码，时间为中国时间。
- 历史账只读，不支持认领、补录、修改、删除，也不影响当前实收入账统计。

## 7. 页面验收

### 桌面端

- 首页三张 KPI、三类趋势、四类排行、最近调额和告警入口可查看。
- 模型统计、Sub2API 用户、调额记录、现金/赠送账、经营账、历史账、异常中心、操作审计可打开。
- 调额提交前有明确确认文案。
- 金额、Token、请求数使用不同格式化方式。

### H5

- 390px 左右宽度可登录和切换菜单。
- 筛选区不遮挡按钮。
- 表格可横向滚动，关键金额和状态可查看。
- 弹窗、抽屉可关闭，图表不会绑定到已销毁 DOM。

## 8. 安全检查

- 生产环境不配置或连接 Sub2API PostgreSQL。
- 代码、测试、文档和日志中不得出现服务器密码、数据库密码、Admin API Key 或 Authorization。
- 结构异常只记录安全字段形态，不增加猜测式兼容。
- 附件使用私有盘，下载必须鉴权，富文本脚本保存后被过滤。
