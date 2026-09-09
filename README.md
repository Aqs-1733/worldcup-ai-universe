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
php scripts/sync_worldcup.php
php scripts/sync_news.php
php -S 127.0.0.1:8080 -t public public/index.php
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

初始化只建立表、基础标签、来源和管理员，不伪造世界杯赛果。数据来自：

- FIFA 官方球队、赛程、积分页面。
- ESPN World Cup scoreboard API。
- Wikipedia 2026 FIFA World Cup squads 页面。
- BBC Sport、ESPN、The Guardian、Sky Sports、新华社体育等 RSS 新闻源。

同步命令：

```powershell
php scripts/sync_worldcup.php
php scripts/sync_news.php
```

如果网络或来源失败，脚本会记录错误，不会填本地假数据。外文翻译依赖 `ARK_API_KEY` 和 `ARK_MODEL`，没配置时保留原文并标记待翻译。

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
