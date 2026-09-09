# worldcup-ai-universe

PHP + MySQL 版 2026 世界杯球迷信息系统。项目保留原来的世界杯首页、赛程、球队、球员、新闻、球迷偏好、AI 创作、生图入口、视觉入口和后台管理，但技术栈已切换为老师要求的 PHP + MySQL，不使用 SQLite。

## 功能

- 账号密码登录、注册、用户名和邮箱唯一校验。
- 用户首次登录问卷：支持球队多选、支持球员多选、屏蔽球队、推送偏好，也支持跳过；填过或跳过后不再强制弹出。
- 首页赛事中控台：赛程、晋级关系、积分图表、球队、球员、新闻、项目团队。
- 球队和球员页面：中文名 / 原名并列，球队和球员显示国旗，球员按知名度、出场和进球排序。
- 新闻中心：从真实 RSS 来源抓取，按比赛内容、战术分析、球队动态、球迷内容、娱乐内容、二创内容等多标签分类，保留来源链接和原文版本；配置 ARK 后自动中文翻译和归纳。
- 世界杯中心：比赛、积分榜和对战图从 MySQL 读取，未同步或未开始的比赛不预测比分。
- AI 创作：支持用户自定义语气、自主输入球队，勾选后才调用图片生成，节省图片额度。
- 视觉分析入口：支持图片上传记录，后续可接视觉模型。
- 后台管理：团队成员、球队、球员、赛程、新闻、发布内容、留言管理。
- MySQL 表数量超过 10 张，见 `database/schema.sql`。

## 环境要求

- PHP 8.1+，需要启用 `pdo_mysql`、`mbstring`、`curl`、`simplexml`、`dom`。
- MySQL 8.0+。
- Git。

本机如果 `php -v` 或 `mysql --version` 不能执行，先安装 PHP 和 MySQL，或者用 phpStudy / XAMPP / Laragon 这类集成环境。

## 启动

```powershell
cd D:\worldcup-ai-universe
Copy-Item .env.example .env
```

编辑 `.env`，填好 MySQL 账号密码。然后建库和初始化：

```powershell
php scripts/install.php
php scripts/import_collected_data.php
php scripts/fill_player_name_transliterations.php
php scripts/sync_news.php
php -S 127.0.0.1:8080 -t public public/index.php
```

如果你本机 PHP 在 XAMPP 里，但没有加入 PATH，用：

```powershell
D:\XAMPP\php\php.exe scripts\install.php
D:\XAMPP\php\php.exe scripts\import_collected_data.php
D:\XAMPP\php\php.exe scripts\fill_player_name_transliterations.php
D:\XAMPP\php\php.exe scripts\sync_news.php
D:\XAMPP\php\php.exe -S 127.0.0.1:8080 -t public public/index.php
```

打开：

```text
http://127.0.0.1:8080
```

默认管理员来自 `.env`：

```text
用户名：admin
密码：ChangeMe2026!
```

正式演示前建议把 `.env` 里的 `ADMIN_PASSWORD` 改掉，再运行 `php scripts/install.php`。

## ARK 配置

不要把真实密钥提交到 GitHub。只在本地 `.env` 填：

```env
ARK_API_KEY=你的火山方舟Key
ARK_OPENAI_BASE_URL=https://ark.cn-beijing.volces.com/api/v3
ARK_MODEL=你的文本模型ID
ARK_IMAGE_BASE_URL=https://ark.cn-beijing.volces.com/api/v3
ARK_IMAGE_MODEL=你的Seedream 4.5模型ID或Endpoint ID
ARK_IMAGE_SIZE=1024x1024
ARK_IMAGE_RESPONSE_FORMAT=url
```

图片生成请求路径是：

```text
POST https://ark.cn-beijing.volces.com/api/v3/images/generations
```

只有在 AI 创作页勾选“同时生成图片”时才会调用图片生成，避免浪费额度。

## 真实数据同步

初始化只建立表、基础标签、来源和管理员，不伪造世界杯赛果。老师验收用的完整数据导入脚本是：

```powershell
php scripts/import_collected_data.php
```

如果使用 XAMPP：

```powershell
D:\XAMPP\php\php.exe scripts\import_collected_data.php
```

导入脚本会读取 `storage/imports/worldcup2026_complete_collection_kit`，把采集包里的 CSV/JSON 原始记录、球队、球员、赛程、赛果、阵容、事件、统计和校验结果全部写入 MySQL。当前完整导入结果：

- 48 支球队。
- 1248 名最终注册球员，每队 26 人。
- 104 场比赛，阶段为小组赛 72 场、32 强 16 场、16 强 8 场、四分之一决赛 4 场、半决赛 2 场、季军赛 1 场、决赛 1 场。
- 116341 条原始 CSV/JSON 记录入库。
- 9 项自动校验全部通过，包含 Pochih/FIFA 与 Alamyy 赛果交叉检查 `0 mismatch`。
- 全部球员都保留英文/原始姓名；源数据缺中文名时，可运行 `scripts/fill_player_name_transliterations.php` 用中文音译补齐展示名。

核心数据来源：

- FIFA 官方球队、赛程、积分页面。
- Pochih WorldCup2026 赛程和官方名单快照。
- Mominullptr FIFA World Cup 2026 Dataset。
- Alamyy Worldcup26 比赛赛果和来源链接。
- EbEmad FIFA-Data-Wc-2026 球员补充字段。
- BBC Sport、ESPN、The Guardian、Sky Sports、新华社体育等 RSS 新闻源。

新闻仍可联网刷新：

```powershell
php scripts/sync_news.php
```

如果网络或来源失败，脚本会记录错误，不会填本地假数据。外文新闻翻译依赖 `ARK_API_KEY` 和 `ARK_MODEL`，没配置时保留原文并标记待翻译。

`scripts/sync_worldcup.php` 保留为在线同步入口；课程验收建议优先使用 `scripts/import_collected_data.php`，因为它会导入采集包并写入数据质量检查表。

如果火山方舟域名在本机 DNS 下解析失败，可以在 `.env` 里配置：

```env
ARK_DNS_FALLBACK_IP=方舟域名解析IP
NO_PROXY=127.0.0.1,localhost,::1,ark.cn-beijing.volces.com
```

程序会对 `ark.cn-beijing.volces.com` 使用 DNS fallback，同时保留 HTTPS 主机名校验。

## GitHub 提交

```powershell
cd D:\worldcup-ai-universe
git status
git add .
git commit -m "Rebuild worldcup system with PHP MySQL"
git push origin codex/php-mysql-rebuild
```

如果要推到主分支：

```powershell
git checkout main
git merge codex/php-mysql-rebuild
git push origin main
```

`.env` 不会提交，密钥和数据库密码不要发到仓库。
