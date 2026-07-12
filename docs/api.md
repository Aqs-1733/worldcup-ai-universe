# API 说明

启动后访问 `http://127.0.0.1:8000/docs` 查看完整 Swagger 文档。

| 模块 | 方法 | 路径 | 说明 |
|---|---|---|---|
| 系统 | GET | `/api/health` | 健康状态和数据量 |
| AI足球大脑 | POST | `/api/chat` | LangGraph 多Agent聊天 |
| 球队 | GET | `/api/teams` | 球队列表与搜索 |
| 球队 | GET | `/api/teams/{slug}` | 球队详情和阵容 |
| 球员 | GET | `/api/players` | 球员列表与筛选 |
| 世界杯 | GET | `/api/worldcup/matches` | 赛程与比分 |
| 世界杯 | GET | `/api/worldcup/standings` | 小组积分榜 |
| 世界杯 | GET | `/api/worldcup/bracket` | 淘汰赛结构 |
| 新闻 | POST | `/api/news/refresh` | 联网采集新闻 |
| 新闻 | POST | `/api/news/analyze` | 新闻真实性检测 |
| 用户 | POST | `/api/users` | 创建或更新球迷画像 |
| 推荐 | GET | `/api/users/{id}/daily` | 生成个性化日报 |
| RAG | GET | `/api/rag/search?q=...` | 知识库检索 |
| 视觉 | POST | `/api/vision/analyze` | 上传图片分析 |
| AIGC | POST | `/api/generation` | 生成海报、文案和脚本 |
