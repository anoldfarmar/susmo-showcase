# susmo 本机展示站

当前站点使用英文，展示假发产品和公司信息，不提供支付、订单或客户账户。已有环境无需重新安装。

## 访问

- 首页：<http://127.0.0.1:8080/>
- Wigs：<http://127.0.0.1:8080/shop/>
- About Us：<http://127.0.0.1:8080/about-us/>
- Hair Guide：<http://127.0.0.1:8080/hair-guide/>
- FAQ：<http://127.0.0.1:8080/faq/>
- Contact Us：<http://127.0.0.1:8080/contact/>
- 管理后台：<http://127.0.0.1:8080/wp-admin/>

管理员凭据仍在 `.local/site-access.json`，不粘贴到聊天。仅保留网站管理员登录，不开放客户注册。

## 内容维护

- 在 WordPress Pages 中编辑 About Us、Contact Us、Hair Guide、FAQ 等英文正文。
- 在 `Settings → susmo Company` 维护公司英文名称、英文地址和企业邮箱，供公司资料区和页脚读取。正文中的叙述、FAQ 邮箱等独立内容修改时也应保持一致。
- 在 WooCommerce Products 中维护商品图片和描述；其交易功能已由 susmo Catalog 插件禁止。当前只发布一款有素材支持的假发，旧示例商品改为草稿。
- 公司英文译名是 Shenzhen Dunlin E-Commerce Co., Ltd.，原始中文名称和中英地址对照保存在 `.plan.md`。没有确认的材质、帽围、尺寸和可选颜色通过邮箱沟通，不编造参数。
- 首版首页由 `theme/front-page.php` 组织，公共导航/页脚和商品展示也在 `theme/`；全站 CSS 为 `theme/style.css`。

`theme/` 与 `plugins/susmo-catalog/` 都通过 Junction 映射到本机运行目录，修改源码后刷新页面即可。不要修改 WordPress、父主题或 WooCommerce 源码，不需要 npm 构建。

## 日常操作

启动网站：

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File '.\scripts\dev.ps1' -Action Start
```

停止网站：

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File '.\scripts\dev.ps1' -Action Stop
```

在已安装基础上，重新应用品牌映射、素材和日志设置（已有内容版本不会被重置）：

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File '.\scripts\dev.ps1' -Action Brand
```

首次建立基础站使用 `-Action Site`，现已接入目录模式和 susmo 内容，不再创建旧购物车演示商品。使用现有站点验证了脚本，未通过删除数据库测试空库重装。

## 检查

```powershell
node '.\scripts\Check-Site.mjs'
```

该检查验证英文页面、公开公司信息、客户表单和价格/购买入口缺失，测试旧交易 URL 和匿名写入被拒绝，并比较运行前后的数据库聚合计数。不会提交个人资料、创建购物车、客户或订单。结果在 `.local/catalog-verification.json`。

保留历史测试会话及默认评论，未执行删除。访客会话处理器不新建或持久化购物会话；旧记录不等于新增客户资料收集。

本次本机 HTTP、PHP 语法、接口拒绝和数据数量检查通过。Computer Use 未能可靠识别 Chrome 当前 URL，因此尚未完成浏览器截图、多尺寸布局和菜单/图库键盘交互实测；页面已加入相应响应式样式和交互代码。

## 资源与日志

- 原图和 Logo 在 `素材库/`，原文件未改动；`scripts/Prepare-Assets.php` 生成 `theme/assets/images/` 中的 WebP 展示资源，五张图片总量小于 1MB。
- 产品图片同时导入 WordPress 媒体库，支持后台维护。不要因不同视角图片虚构不同商品。
- `.local/backups/before-susmo-content.json` 保存应用品牌前的页面与旧示例商品状态；日志配置也有独立备份。
- Apache 访问日志仅记录时间、请求方法、路径、状态和大小，去除 IP、查询参数和 User-Agent；历史日志未清空。WordPress/PHP 错误日志位于 `.local/logs/`，勿公开凭据或敏感记录。
- 正式发布只发布主题、插件和所需内容，不发布 Junction、密钥、`.local/` 整个目录或本机管理员凭据。AWS 上线仍需单独验证备份恢复、HTTPS 与环境配置。
