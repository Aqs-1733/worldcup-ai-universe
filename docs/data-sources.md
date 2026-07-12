# 数据来源与使用边界

项目的数据层分为两类：

1. `data/teams.json`、`data/players.json`、`data/matches.json` 等内置数据，用于保证离线环境可完整运行。球队范围按 2026 世界杯 48 队、12 个小组和 104 场赛制组织；比分、排名、状态分和概率是平台初始化展示值，界面和接口均提供数据说明。
2. 新闻中心通过 RSS/公开网页入口联网刷新，保留来源名称、原文地址、发布时间、抓取时间和可信度分析结果。

正式部署时，应将比赛与名单数据替换为获得授权的实时数据接口，并遵守来源网站的服务条款、版权和抓取规则。

参考入口：

- FIFA World Cup 2026 Teams: https://www.fifa.com/en/tournaments/mens/worldcup/canadamexicousa2026/teams
- FIFA World Cup 2026 Fixtures: https://www.fifa.com/en/tournaments/mens/worldcup/canadamexicousa2026/scores-fixtures
- FIFA World Cup 2026 Standings: https://www.fifa.com/en/tournaments/mens/worldcup/canadamexicousa2026/standings
