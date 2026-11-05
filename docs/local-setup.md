# Windows 本机环境搭建

当前项目已改为商品与公司信息展示站，需求以根目录 `.plan.md` 为准。本文件只说明环境首次搭建；已有环境无需重新安装。网站内容维护和当前改造状态见 `local-store.md`。

本阶段只搭建 PHP/Apache/MariaDB 环境，后续再安装 WordPress、WooCommerce 和主题。无需 Docker、WSL2、Composer、npm install 或全局 PATH 修改。

工作目录：`D:\Study\rh\AWS-Stand-alone`。所有便携程序、数据库、日志和临时文件均保存在 `.local/`，Microsoft 共享运行库按其安装程序安装在系统目录。内部需要 PowerShell 7.2 或更新版本：当前 Codex 自带 7.6.5，`scripts/dev.ps1` 会自动查找，不需要另外安装或修改全局 PATH。若以后 Codex 清理或移动了自带运行时，入口会提示需要恢复可用的 PowerShell 7.2+。

## 1. 创建下载目录

打开普通 Windows PowerShell 或 Codex 项目终端，无需以管理员身份打开项目终端：

```powershell
Set-Location -LiteralPath 'D:\Study\rh\AWS-Stand-alone'
New-Item -ItemType Directory -Path '.\.local\downloads' -Force
Get-PSDrive C, D | Select-Object Name, @{Name='FreeGB'; Expression={[math]::Round($_.Free / 1GB, 2)}}
```

项目盘至少预留 3GB 后再运行脚本；3～5GB 是初期规划余量，不是固定总占用，商品图片、数据库和备份会增长。

## 2. 更新 Microsoft VC++ x64 运行库

2026-11-05 检查到本机运行库为 14.44；Apache Lounge 当前 VS18 包要求 14.51.36247 或更新版本，且支持 VS17 编译的 PHP 模块。按照 [Apache Lounge 下载页](https://www.apachelounge.com/download/) 的要求选择运行库。以后若发布方提高要求，同步更新脚本的版本检查。

下载 [Microsoft VC++ x64 运行库](https://aka.ms/vc14/vc_redist.x64.exe)，保存为：

```text
D:\Study\rh\AWS-Stand-alone\.local\downloads\vc_redist.x64.exe
```

运行以下命令，会出现 Windows 管理员权限提示；只在这一步安装系统共享运行库。不要直接在管理员终端运行整个项目。

```powershell
$taskInstaller = (Resolve-Path -LiteralPath '.\.local\downloads\vc_redist.x64.exe').Path
$taskInstallProcess = Start-Process -FilePath $taskInstaller -ArgumentList '/install', '/passive', '/norestart' -Verb RunAs -Wait -PassThru
if ($taskInstallProcess.ExitCode -notin @(0, 3010)) { throw "VC++ runtime installation failed: $($taskInstallProcess.ExitCode)" }
```

若退出码为 3010，先保存工作并重启电脑，再继续后续步骤。若下载页所需版本已经安装，可跳过安装命令。

## 3. 下载三个便携 ZIP 包

用浏览器下载，直接保存到项目的 `.local/downloads/`，不要在 C 盘保留多份副本。下面三个 ZIP 不需要手工解压，脚本会处理。

| 下载内容 | 选择规则 | 保存名称 |
| --- | --- | --- |
| [PHP 官方下载](https://www.php.net/downloads.php?os=windows) / [8.4 TS x64 ZIP](https://www.php.net/~windows/releases/latest/php-8.4-Win32-vs17-x64-latest.zip) | PHP 8.4 的最新维护版本，VS17 x64 **Thread Safe**；不要 NTS、源码、SDK、Debug Pack | `.local/downloads/php.zip` |
| [Apache Lounge](https://www.apachelounge.com/download/) | Apache 2.4 最新维护版本，Win64；当前为 VS18，使用发布页要求的 VC++ 运行库 | `.local/downloads/apache.zip` |
| [MariaDB 官方下载](https://mariadb.org/download/) | Server 11.4 最新维护版本 → Windows → x86_64 → ZIP；不要 MSI、源码、Connector | `.local/downloads/mariadb.zip` |

Apache 项目本身不发行 Windows 二进制包，Apache Lounge 是 [Apache 官方 Windows 文档](https://httpd.apache.org/docs/2.4/platform/windows.html) 列出的下载来源之一，不应把它描述为 Apache 官方发行包。

脚本也接受符合上述版本和架构的原始 ZIP 文件名，无需改名。每类存在多个候选时，才需要将选用的那个包命名为表中的短名称。MSI 不能通过改后缀变成 ZIP。

若 MariaDB 首页默认选中了 13.x，可直接进入 [11.4.13 Windows x64 文件列表](https://dlm.mariadb.com/browse/mariadb_server/11.4.13/winx64-packages/)，选择 `mariadb-11.4.13-winx64.zip`。不要选择 `debugsymbols.zip` 或 `.msi`。

下载完成后，对照发布方下载页显示的 SHA256 校验值核对；尤其 `latest.zip` 是会更新的入口，校验值应匹配下载时的实际版本。下列命令只计算本地值，不自动证明其可信：

```powershell
Get-ChildItem -LiteralPath '.\.local\downloads' -Filter '*.zip' -File | Get-FileHash -Algorithm SHA256
```

只使用这些可信来源，若 TLS/证书错误，不关闭证书校验，不改成 HTTP 下载。当前代理环境下 PowerShell 下载请求未成功，因此这里使用浏览器下载，不提供未经验证的一键联网下载命令。

## 4. 配置环境

回到项目的普通 PowerShell 终端执行：

```powershell
Set-Location -LiteralPath 'D:\Study\rh\AWS-Stand-alone'
powershell -NoProfile -ExecutionPolicy Bypass -File '.\scripts\dev.ps1' -Action Setup
```

`-ExecutionPolicy Bypass` 只作用于本次 PowerShell 子进程，不修改机器或用户的永久策略；组织强制策略仍然有效。执行的是当前工作区内可阅读的脚本。

脚本将：

1. 检查 Windows x64、VC++ 运行库、磁盘余量和 ZIP 文件。
2. 解压程序到 `.local/runtime/`，验证 PHP 8.4、MariaDB 11.4、Apache 2.4 及 PHP 扩展。
3. 配置 Apache `127.0.0.1:8080` 和 MariaDB `127.0.0.1:3307`。
4. 创建本机随机数据库 root 密码，保存到被 Git 忽略的 `.local/`，不打印密码。
5. 初始化新的数据库目录，并保留已有数据库；不注册 Windows 服务，不设置开机自启。
6. 创建 `/health.php` 和实际版本/包哈希清单 `.local/runtime.json`。

根账号只用于环境管理。安装 WordPress 时另建应用数据库和最小权限账号，不直接使用 root。环境脚本本身不安装网站内容。

## 5. 启动与验收

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File '.\scripts\dev.ps1' -Action Start
powershell -NoProfile -ExecutionPolicy Bypass -File '.\scripts\dev.ps1' -Action Check
```

启动命令会在后台运行本项目的 Apache 和 MariaDB，并执行一次检查。独立的 `Check` 命令可用于后续重新验收。

检查成功应包括：

- PHP 8.4 版本信息。
- Apache `Syntax OK`。
- 数据库版本和 `connection_ok = 1`（实际认证查询）。
- `required extensions: OK`。

浏览器打开 [本机环境首页](http://127.0.0.1:8080) 和 [PHP 健康检查](http://127.0.0.1:8080/health.php)。初次搭建后看到环境就绪页面是正常结果；已安装网站时首页显示现有站点，后续按当前展示站计划推进。

如果 Windows 弹出网络访问提示，本环境仅监听本机回环地址，无需为它开放公网或局域网防火墙入口。

## 6. 日常启停与排错

启动：

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File '.\scripts\dev.ps1' -Action Start
```

停止（先停止网站，再正常关闭数据库，保留数据文件）：

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File '.\scripts\dev.ps1' -Action Stop
```

如果端口被其他程序占用，脚本会停止继续启动，不自动结束其他程序。若一项服务启动失败，另一项可能仍在运行，可用上述 Stop 命令关闭本项目已启动的进程。

日志位于 `.local/logs/`。将错误消息提供给后续排错任务时，不粘贴 `.local/secrets.json` 或 `.local/config/root-client.cnf`。数据库初始化失败时保留现场，不删除 `.local/data/` 后盲目重试。

配置成功后重跑 Setup 只提示已有配置，不更新或重置数据库。升级运行包、改端口或更改凭据需要单独的迁移任务，不通过重复 Setup 完成。

环境与网站验收通过后，ZIP 下载包可以手动删除以释放空间。保留 `.local/runtime.json` 中的版本和哈希记录；不要删除 `.local/data/`、凭据文件或后续 `.local/www/wp-content/uploads/`。

## 7. 当前验证边界

脚本已通过 PowerShell 静态解析、解压布局及相关保护检查；Windows PowerShell 入口已验证能找到已有 PowerShell 7，并正确报告未满足的前置条件。尚未用真实 PHP/Apache/MariaDB 安装包完成运行验收；真实运行验收以第 5 节的输出为准，完成后再更新 `.plan.md` 的 P1 状态。

参考：[PHP Windows 安装说明](https://www.php.net/manual/en/install.windows.manual.php)、[PHP Apache TS 模块说明](https://www.php.net/manual/en/install.windows.apache2.php)、[MariaDB ZIP 安装](https://mariadb.com/docs/server/server-management/install-and-upgrade-mariadb/installing-mariadb/binary-packages/installing-mariadb-windows-zip-packages)、[MariaDB Windows 数据目录初始化](https://mariadb.com/docs/server/server-management/install-and-upgrade-mariadb/installing-mariadb/installing-system-tables-mariadb-install-db/mariadb-install-db-exe)。
