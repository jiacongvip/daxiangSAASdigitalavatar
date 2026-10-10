# community-ce 本地试跑（digitalavatar + 插件运行时）

## 启动

```bash
cd tpxiangmu/tpdaxiangshop
cp -n .env.example .env   # 若还没有 .env
docker compose up -d
```

- 管理后台：http://127.0.0.1:8088/admin/ （`admin` / `123456`）
- 数字人用户端：http://127.0.0.1:8088/portal/digitalavatar/ （演示：`13800138000` / `123456` + 页面验证码）
- MySQL：`127.0.0.1:3308` root/root123456
- Redis：`127.0.0.1:6380`

首次初始化会自动执行：
- `20-tpdaxiangshop.sql` 基础库
- `30-ce-slim-menus.sql` 精简侧栏（插件 + 系统）+ 本地 digitalavatar 授权
- `40-ce-portal-bootstrap.sql` 用户端域名绑定与演示会员

## 异步 worker

容器内 Supervisor 已注册 `php think async:work`（CE 精简版，仅跑数字人队列）。

```bash
docker exec community_ce_php supervisorctl status
docker exec community_ce_php supervisorctl restart tpdaxiangshop-async-worker
```

## 说明

本包不含商城业务菜单；Vue 仅交付 `public/admin` 编译产物（无 `.vue` 明文）。
