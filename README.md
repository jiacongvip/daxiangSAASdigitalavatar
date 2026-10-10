# daxiangSAAS digitalavatar（Community CE）

开源社区版：**插件运行时底座 + 完整 `digitalavatar`（AI 数字人）插件**。  
许可证：[Apache-2.0](./LICENSE)。

> 不含商城业务菜单；管理端只交付 `public/admin` 编译产物（无 Vue 明文源码）。  
> 商业插件与在线插件市场不在本仓库范围内。

## 快速启动（Docker）

```bash
cd tpxiangmu/tpdaxiangshop
cp -n .env.example .env
docker compose up -d
```

等待 MySQL 首次初始化（会自动执行精简菜单与演示账号 SQL）。

| 入口 | 地址 | 账号 |
|---|---|---|
| 管理后台 | http://127.0.0.1:8088/admin/ | `admin` / `123456` |
| 数字人用户端 | http://127.0.0.1:8088/portal/digitalavatar/ | `13800138000` / `123456` + 页面验证码 |
| MySQL | 127.0.0.1:3308 | root / root123456 |
| Redis | 127.0.0.1:6380 | （无密码） |

请尽量用 **127.0.0.1** 打开用户端（不要混用 localhost），避免验证码 session 域名不一致。

## 异步任务（数字人队列）

容器内 Supervisor 已注册 CE 精简版 worker：`php think async:work`（只跑数字人队列）。

```bash
docker exec community_ce_php supervisorctl status
docker exec community_ce_php supervisorctl restart tpdaxiangshop-async-worker
```

## 目录说明

- `tpxiangmu/tpdaxiangshop/`：可运行后端（ThinkPHP）+ `public/admin` + `public/portal/digitalavatar`
- `app/plugin/digitalavatar/`：数字人主插件
- `database/ce/` 与 `docker/mysql/init/30|40-*.sql`：开源默认精简菜单 / 门户引导
- `SANITIZE-REPORT.md`：脱敏检查记录
- `BOOT-LOCAL.md`：本地试跑补充说明

## 范围边界

**包含**

- 插件扫描 / 安装版本 / 本地授权展示
- 系统配置、管理员、操作日志、素材中心（精简菜单）
- 完整 digitalavatar 后台与用户 SPA
- 数字人异步 worker（overlay 精简版）

**不包含**

- 商城商品/订单等业务菜单与完整商城插件集
- 在线插件市场（`PluginMarket` 为本地 stub）
- Vue Admin 源码明文、老版 HTML 后台整站模板
- UniApp 手机端（后续可选）

## 生产部署提示

1. 务必修改 `.env` 中的 `AES_KEY`、`JWT_SECRET` 与数据库密码。  
2. 数字人云厂商密钥在后台「数字人设置」中自行配置，不要提交进 Git。  
3. 详见 `SANITIZE-REPORT.md`。

## 下一步（维护者）

见仓库根目录 `NEXT-STEPS.md`。
