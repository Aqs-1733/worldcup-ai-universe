# 验收命令

```powershell
cd D:\worldcup-ai-universe
php -v
mysql --version
php scripts/install.php
php scripts/sync_worldcup.php
php scripts/sync_news.php
php -S 127.0.0.1:8080 -t public public/index.php
```

若 PHP 未加入 PATH：

```powershell
D:\XAMPP\php\php.exe scripts\install.php
D:\XAMPP\php\php.exe scripts\sync_worldcup.php
D:\XAMPP\php\php.exe scripts\sync_news.php
D:\XAMPP\php\php.exe -S 127.0.0.1:8080 -t public public/index.php
```

打开 `http://127.0.0.1:8080` 后检查：

- 注册和登录可用。
- 用户名重复会提示失败。
- 首次问卷支持保存和跳过。
- 首页能看到赛程、对战图、球队、球员、新闻和图表。
- 新闻详情能看到中文归纳、原文和来源链接。
- 后台能编辑并发布信息。
