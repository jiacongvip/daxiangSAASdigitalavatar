# NEXT-STEPS（维护者）

公开仓库：**https://github.com/jiacongvip/daxiangSAASdigitalavatar**

首次推送已完成（`main` ← commit `8fda6ba`）。本地目录：

`/Users/jiacong/Desktop/php/tts-community`

## 后续更新

```bash
cd /Users/jiacong/Desktop/php/tts-community
# 用新的 delivery 包覆盖内容后：
git add -A
git commit -m "Describe change"
git push origin main
```

## 重建交付包

```bash
cd /Users/jiacong/Desktop/php/tools/delivery-packager
npm run build -- --name community-ce --manifest community-ce --no-legacy --no-zip
# 然后套用 docs/2026-10-10-社区版交付裁切/overlay/（含 AsyncWork、PluginMarket stub、CE SQL、captcha SPA）
# 并手动 rsync vendor（或依赖 manifest include.vendorRuntime）
```

清单：`docs/delivery-manifests/community-ce.json`  
脱敏报告：仓库根目录 `SANITIZE-REPORT.md`
