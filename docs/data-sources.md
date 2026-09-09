# 数据来源

本项目要求真实数据优先，来源字段会保存在 MySQL 中。

## 赛事

- FIFA Teams：`FIFA_TEAMS_URL`
- FIFA Scores & Fixtures：`FIFA_FIXTURES_URL`
- FIFA Standings：`FIFA_STANDINGS_URL`
- ESPN Scoreboard：`ESPN_SCOREBOARD_URL`
- ESPN Standings：`ESPN_STANDINGS_URL`

## 球员

- Wikipedia 2026 FIFA World Cup squads：`WIKIPEDIA_SQUADS_URL`

球员同步会保存原名，配置 ARK 后再写入中文译名。找不到的字段不补假值。

## 新闻

新闻源在 `news_sources` 表中维护，默认包含 BBC Sport、ESPN、The Guardian、Sky Sports 和新华社体育 RSS。新闻支持多标签分类，标题、摘要和正文翻译依赖 ARK 文本模型。
