# susmo 展示站

英文假发商品与公司信息展示站。本机 WordPress + WooCommerce 目录后台维护内容，公开版本由 EdgeOne Pages 托管静态文件，不提供交易或客户账户。

## 本机更新与检查

在工作区根目录执行，先保证本机 WordPress 已启动：

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\dev.ps1 -Action Start
node .\scripts\Export-Static.mjs
node .\scripts\Preview-Static.mjs
```

静态预览：`http://127.0.0.1:8081/`，Ctrl+C 停止。正式域名确定后，用 `node scripts/Export-Static.mjs --site-url https://实际域名` 生成 canonical 与 sitemap。

## EdgeOne Pages

导入 GitHub 仓库，框架选 Other，根目录用仓库根目录，构建命令为 `node scripts/Check-Static.mjs`，输出目录为 `dist`。配置也已写入 `edgeone.json`。不需要安装依赖，不在云端运行本机导出命令。

`dist/` 需要提交；本机数据库、密钥和运行环境必须留在忽略提交的目录。提交前运行 `node scripts/Check-Repository.mjs` 检查候选文件路径和常见密钥标记。每次后台内容或主题更新后重新导出并提交。GitHub Actions 仅检查现有产物，不重新读取本机内容。

详细步骤、域名设置和回滚见 [发布说明](docs/edgeone-pages.md)；本机维护见 [本机站点说明](docs/local-store.md)。当前已完成本机产物准备，尚未部署公网。多尺寸视觉与键盘交互仍待实际浏览器验收。
