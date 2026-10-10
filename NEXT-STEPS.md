# NEXT-STEPS（维护者）

公开仓库目标：https://github.com/jiacongvip/daxiangSAASdigitalavatar

若本机已完成 `git init` 与首次 commit，可用下面命令推送（**不要**对非空远端盲目 `--force`）：

```bash
cd /Users/jiacong/Desktop/php/tts-community
git remote -v
# 若还没有 remote：
git remote add origin git@github.com:jiacongvip/daxiangSAASdigitalavatar.git
# 或 HTTPS：
# git remote add origin https://github.com/jiacongvip/daxiangSAASdigitalavatar.git

git branch -M main
git push -u origin main
```

若 SSH 失败，可改用 `gh auth login` 后再：

```bash
gh repo view jiacongvip/daxiangSAASdigitalavatar
git push -u origin main
```

本地交付包来源：`output/delivery-packages/community-ce-20261010-160636`  
裁切清单：`docs/delivery-manifests/community-ce.json`  
打包后 overlay：`docs/2026-10-10-社区版交付裁切/overlay/`
