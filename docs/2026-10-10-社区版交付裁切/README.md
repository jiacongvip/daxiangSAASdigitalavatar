# 社区版交付裁切（digitalavatar + 运行时）

## 当前预览包

`/Users/jiacong/Desktop/php/output/delivery-packages/community-ce-20261010-142426`

后台：http://127.0.0.1:8088/admin/ （admin / 123456）  
用户端：http://127.0.0.1:8088/portal/digitalavatar/

## Manifest

- 文件：`docs/delivery-manifests/community-ce.json`
- 版本：`2026-10-10.7`
- 新增：`syssetSupport`、`digitalavatarAsyncRuntime`

## 打包后必做 overlay（精简异步 worker）

源码完整 `AsyncWork.php` 不能进社区包。打包完成后执行：

```bash
PKG=output/delivery-packages/community-ce-YYYYMMDD-HHMMSS/tpxiangmu/tpdaxiangshop
OV=docs/2026-10-10-社区版交付裁切/overlay
mkdir -p "$PKG/app/command"
cp -f "$OV/app/command/AsyncWork.php" "$PKG/app/command/AsyncWork.php"
cp -f "$OV/config/console.php" "$PKG/config/console.php"
```

然后重启 worker：

```bash
docker exec community_ce_php supervisorctl restart tpdaxiangshop-async-worker
# 或
docker exec community_ce_php php /app/think async:work --once
```

## 系统配置页接口对照

| 前端 API | 后端方法 | 关键文件 |
|---|---|---|
| `/BackstageApi/getSyssetInfo` | `BackstageApi::getSyssetInfo` | `app/adminapi/controller/BackstageApi.php` |
| `/BackstageApi/saveSysset` | `BackstageApi::saveSysset` | 同上 |
| 地图字段确保 | `MapQuery::ensureH5MapKeyColumn` | `app/admin/common/MapQuery.php` |
| AI 配置读写 | `AiNewapi::getAidConfig` 等 | `app/admin/common/AiNewapi.php` |
| 图片上传 | `/Upload/uploadFile` | `app/adminapi/controller/Upload.php` + `app/admin/common/File.php` / `Pic.php` |
| 登录 | `/Login/index` | `app/adminapi/controller/Login.php` |

地图选点组件主要走浏览器地图 SDK（高德/百度/腾讯），密钥来自 sysset 表单字段。

## 数字人异步相关文件

- CE worker：`overlay/app/command/AsyncWork.php`（只跑 `DigitalAvatarTaskService::processQueue`）
- 日志：`app/admin/common/AsyncWorkerLogService.php`
- 队列实现：`app/plugin/digitalavatar/common/DigitalAvatarTaskService.php`
- 发布执行依赖（追爆 API 类，非完整插件）：`app/plugin/zhuibaoagent/common/Zhuibao*.php`（Provider/AvatarVoice/Image/NiwaCatalog 等）
- Supervisor：`docker/supervisor/async-worker.conf` → `php think async:work --digitalavatar-interval=3 --digitalavatar-limit=5`

## 刷新方式

1. 硬刷新后台系统配置页：http://127.0.0.1:8088/admin/#/backstage/sysset
2. 若刚拷了 PHP 文件，一般不必重建容器；opcache 若未即时生效可 `docker restart community_ce_php`
3. 异步任务：`docker exec community_ce_php supervisorctl restart tpdaxiangshop-async-worker`

## 2026-10-10.8：管理员列表 `/admin.php`

**根因**：预览包缺 `public/admin.php`，`POST /admin.php?s=/User/index` 被 nginx 直接 404 HTML。

**已补**：
- `public/admin.php` + `public/website_entry_dispatch.php`
- `app/admin/controller/{Base,Common,User,UserGroup}.php`
- `app/admin/view/user`（非 Ajax 兜底）

操作日志仍用 `adminapi.php` → `BackstageApi/getPlogList|getPlogUsers|delPlog`（已可用）。

刷新：http://127.0.0.1:8088/admin/#/user  
操作日志：http://127.0.0.1:8088/admin/#/backstage/plog

## 2026-10-10 数字人注册验证码修复

### 原因
- 登录样式 `niwa` 且未开短信时，注册接口 `submitMemberRegister` 会校验图形验证码 `captcha`。
- 用户端 SPA 注册提交只传了 `tel/pwd/confirm_password/smscode/agreed_protocol`，**漏传 `captcha`**。
- 后端 `verifyLoginCaptcha` 收到空字符串后固定返回「请输入4位数字验证码」（不是 session 错乱；错码会返回「验证码输入错误」）。

### 修复
- 源码：`模仿版超的对象/digital-avatar-creator-ui/src/App.tsx` 注册提交补上 `captcha`；`api.ts` 类型同步。
- 预览包编译产物：`community-ce-20261010-142426/.../public/portal/digitalavatar/assets/index-BSsreoPM.js` 已热补同样逻辑（备份 `.bak-captcha-*`）。
- 下次正式重建 portal SPA 后，把新 `assets` 拷进 CE 包即可，无需再手改 JS。

### 复测
1. 务必用 `http://127.0.0.1:8088/portal/digitalavatar/`（不要混用 localhost，避免 cookie 域不一致）。
2. 硬刷新（Cmd+Shift+R）清掉旧 JS。
3. 注册页输入手机号、密码、页面显示的 4 位验证码后提交。
4. 登录同理，也要带验证码。

curl 已验证：不传 captcha →「请输入4位数字验证码」；传正确 captcha → 注册成功并自动登录。

### 缓存穿透
预览包 `index.html` 已改为引用 `assets/index-BSsreoPM-captcha1.js`，避免旧 `immutable` 缓存继续命中漏传 captcha 的旧包。
