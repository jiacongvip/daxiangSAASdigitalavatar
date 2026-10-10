# community-ce 脱敏报告（2026-10-10）

包路径：`output/delivery-packages/community-ce-20261010-160636`

## 已处理

| 项 | 处理 |
|---|---|
| `.env` / `.env.example` | `AES_KEY` / `JWT_SECRET` 改为 `replace_*` 占位；本地 Docker 演示密码 `root123456`（仅本机 compose） |
| `config.php` | `authtoken` → `replace_me_authtoken` |
| `docker/mysql/data` | 未打入包 |
| Vue 明文 `.vue` | 0 个；仅 `public/admin` 编译产物 |
| 种子库客户痕迹 | `20-tpdaxiangshop.sql` 中「乌兰察布供电公司…」、`azheteng.cn` logo、手机号、站点 token/salt 已替换为 CE Demo / example.com / 13800138000 / ce_demo_* |
| 远程存储 / NewAPI | `ddwx_sysset` 中 key/secret/api_key 为空字符串 |
| PluginMarket | 使用社区 stub，非在线市场 |

## 扫描结论（未当密钥处理）

- SQL 列定义含 `appsecret` / `api_key` 等字段名：正常 schema，无真实填充密钥。
- 代码内第三方 API 基址（支付宝、地图、快递 100、`vapi.niwaai.com`、`diandashop.com` 安装域名校验等）：产品逻辑 URL，非本站密钥。
- `Base.php` 含 `https://www.diandashop.com/index/install2/checkdomain`：框架授权校验残留，开源后可按需改掉；当前未改业务源码。
- 全量 `public/admin` 压缩 JS 可能仍有商城/控制台 chunk 字符串：侧栏已由 `30-ce-slim-menus.sql` 数据库菜单挡住。

## Overlay 已套用

- CE `AsyncWork.php` + `config/console.php`
- PluginMarket stubs
- `30-ce-slim-menus.sql` / `40-ce-portal-bootstrap.sql`
- 数字人 SPA captcha 热补（`index-BSsreoPM-captcha1.js`）
- `docker-compose` 端口 8088/3308/6380，容器名 `community_ce_*`
- `vendor/` 已拷入（CLI `--vendor` 对 community-ce 通用分支无效，故手动同步；manifest 0.9 已加 `include.vendorRuntime`）

## 演示账号

- 后台：`admin` / `123456`
- 用户端：`13800138000` / `123456` + 页面图形验证码

## 残留风险 / 建议

1. 种子库仍含大量商城表结构与历史业务字段（表可空），体积与噪声偏大；后续可做更瘦的 CE schema。
2. 生产部署务必重写 `.env` 的 AES/JWT，并改掉 compose 默认 MySQL 密码。
3. 公开前建议再人工扫一遍 `20-tpdaxiangshop.sql` 与 `public/upload`（本包未带业务上传目录内容）。
