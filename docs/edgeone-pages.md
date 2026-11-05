# GitHub 与 EdgeOne Pages 发布

当前发布方式：本机 WordPress 维护内容 → 导出 `dist/` → GitHub → EdgeOne Pages。公开站只有 HTML/CSS/JS、图片和字体；不运行 PHP、WordPress 后台或数据库。保留现有本机架构，AWS 动态部署作为后续备选，不需要安装 Docker。

## 1. 本机导出

先启动本机网站，确认后台修改已保存，然后在项目根目录执行（Node.js 22+）：

```powershell
node .\scripts\Export-Static.mjs
node .\scripts\Preview-Static.mjs
```

预览地址为 `http://127.0.0.1:8081/`，只绑定回环地址；Ctrl+C 停止。它只读取 `dist/`，不依赖 WordPress。可以直接刷新 About、Privacy 或商品深层地址，未匹配地址显示自定义 404。

拿到正式域名或 EdgeOne 项目地址后，用实际 HTTPS 地址重新导出：

```powershell
node .\scripts\Export-Static.mjs --site-url https://你的实际域名
```

也可设置 `STATIC_SITE_URL` 环境变量。地址仅允许 HTTPS 根地址，不带子路径。未设置域名时，站内链接仍可访问；为避免写入假域名，暂不生成 canonical 和 sitemap。设置真实地址后自动生成这两项，并在 robots.txt 中引用 sitemap。

导出范围来自已发布、无密码的 WordPress 页面、商品及有内容的商品分类/标签。草稿、管理员、交易页不导出；只下载页面实际引用的公开静态资源。后台新建或修改已发布内容会进入下一次导出。当前主题分类页延续本机模板行为，不新增筛选或搜索功能；搜索查询不属于静态发布功能。

导出自动检查链接、锚点、图片 srcset、图库 data-image、CSS 字体依赖、全页隐私入口、本机 URL 残留及动态入口，并比较导出前后数据库聚合计数。失败时不会替换当前 `dist/`。成功替换前，旧产物保存在忽略提交的 `.local/static-previous-*`，失败暂存目录为 `.local/static-build-*`；这些备份可按需人工清理以节约磁盘。

独立检查（不启动 WordPress，也适用于 EdgeOne 和 GitHub Actions）：

```powershell
node .\scripts\Check-Static.mjs
```

此检查包含常见敏感标记，不能代替人工确认内容授权、公开资料与完整凭据审查。不要把 `key/`、`.local/`、数据库、后台密码或 wp-config.php 提交。WordPress 后台的修改不会自动在线生效，必须重新导出并提交 `dist/`；直接修改 `dist/` 会在下一次导出被替换。

## 2. 提交 GitHub

仓库已经初始化并配置了 origin。先执行 `git remote -v`，在自己的终端确认远程仓库地址正确；推荐使用私有仓库存源码。然后执行：

```powershell
git status --short
node scripts/Check-Repository.mjs
git add README.md AGENTS.md .plan.md .gitignore .github docs scripts theme plugins content edgeone.json dist
git diff --cached --stat
git commit -m "Prepare susmo static catalog for EdgeOne Pages"
git push -u origin HEAD
```

不要使用 GitHub 网页把整个工作目录拖上去，避免绕过忽略规则上传本机私有目录。推送时若需登录，在 Git 的正常登录窗口完成。无需把令牌发到聊天。GitHub Actions 会检查已提交的静态产物；它不会访问你的电脑、数据库或自动重新导出。

## 3. EdgeOne Pages 设置

在 Pages 控制台新建项目，导入该 GitHub 仓库，选择准备发布的分支。授权仓库访问后设置：

| 字段 | 值 |
| --- | --- |
| 框架 | Other / 其他 |
| 根目录 | 仓库根目录（默认） |
| 安装命令 | `node --version` |
| 构建命令 | `node scripts/Check-Static.mjs` |
| 输出目录 | `dist` |

仓库根目录 `edgeone.json` 已声明以上命令与输出目录，不需要 npm install，也不要在云端运行 Export-Static（云端无法访问本机 WordPress）。**只发布 dist，不把整个仓库作为发布目录。** 根目录 404.html 由 Pages 识别，不配置全部路径回退首页的 SPA rewrite。

首次部署后使用控制台提供的 HTTPS 地址验证首页、导航、产品图库、Privacy Policy、页面刷新和不存在路径的 404。再绑定自定义域名，按控制台给出的实际记录配置 DNS、等待 HTTPS 正常。确认最终域名后重新导出 canonical/sitemap 并推送。不要猜测 CNAME 值。

发布前仍需人工检查手机/桌面视觉、真实商品参数和英文公司译名。隐私政策需核对正式 EdgeOne/邮件服务商配置、日志保留及适用地区；本机检查不等于确认服务商的实际日志行为。

## 4. 更新与回滚

日常：本机后台编辑 → `Export-Static.mjs`（使用正式域名）→ 本机预览 → 提交并推送 → 检查 Pages 部署结果。回滚可在 Pages 控制台选择已验证的旧部署，或通过 Git revert 恢复对应产物提交后再部署；不要重置或删除本机数据库。

官方说明：[构建配置](https://edgeone.cloud.tencent.com/pages/document/162936788693114880)、[edgeone.json](https://edgeone.cloud.tencent.com/pages/document/162936771610066944)、[静态 404](https://pages.edgeone.ai/document/custom-404-page)。本轮仅准备本机代码和产物，未推送 GitHub、创建云项目或发布网站。
