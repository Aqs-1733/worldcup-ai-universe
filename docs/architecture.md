# 技术架构

## 总体分层

浏览器端由 React、TypeScript、Vite 和 TailwindCSS 构建。ECharts 负责概率、能力雷达和统计图，D3.js 负责淘汰赛晋级图。前端通过 `/api` 与 FastAPI 通信。

后端分为 API、业务服务、AI编排、数据层四部分。SQLAlchemy 同时支持 SQLite 和 PostgreSQL；默认数据库保存在 `storage/worldcup_ai.db`。

AI足球大脑使用 LangGraph 编排七个 Agent：Football、Team、Player、News、Prediction、Vision 和 Generation。路由节点先识别用户意图，再把问题交给专门 Agent。每个 Agent 都能访问结构化数据库；知识型问题同时检索 ChromaDB RAG。

RAG 默认使用项目内实现的稳定哈希向量，避免首次启动下载大型模型。配置 `ARK_EMBEDDING_MODEL` 后自动切换为 OpenAI兼容 Embedding 接口。

视觉模块默认使用 OpenCV 完成主色聚类、国旗色带分析、绿色球场检测、球员候选点定位和阵型行聚类。按 README 安装 `ultralytics transformers torch` 并配置模型路径后，可加入 YOLO 与 CLIP；配置 ARK 后可加入视觉大模型解释。

新闻模块并发访问已配置的体育来源，保存标题、摘要、原文地址和发布时间。真实性检测使用来源权重、发布时间、多源标题相似度和高风险措辞生成可信度评分。
