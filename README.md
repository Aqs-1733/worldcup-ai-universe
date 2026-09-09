# WorldCup AI Universe

中文名称：世界杯AI数字球迷生态系统。

这是一个世界杯智能球迷平台，包含 FastAPI 后端、React 前端、SQLAlchemy 数据层、LangGraph 多 Agent 路由、ChromaDB RAG、新闻可信度规则分析、OpenCV 启发式视觉分析、PNG 海报生成和球迷画像推荐。项目默认不依赖外网模型即可启动；配置 ARK 后，部分问答、创作和解释能力会获得大模型增强。

## 给别人直接运行

Windows 用户拿到仓库后，按下面三步即可启动网站：

```powershell
git clone https://github.com/Aqs-1733/worldcup-ai-universe.git
cd worldcup-ai-universe
.\RUN_WEBSITE.bat
```

脚本会自动：

- 从 `.env.example` 复制出本地 `.env`。
- 从 `frontend/.env.example` 复制出本地 `frontend/.env`。
- 安装后端依赖 `uv sync`。
- 安装前端依赖 `pnpm install`。
- 启动 FastAPI 后端和 Vite 前端。
- 打开网站入口 `http://127.0.0.1:5173`。

如果第一次运行正在安装依赖，浏览器可能先显示打不开，等两个终端窗口安装完成后刷新页面即可。

必须先安装：

- Git
- Python 3.12+
- Node.js 20+
- uv
- pnpm

常用测试命令：

```powershell
cd worldcup-ai-universe
uv run pytest -q

cd frontend
pnpm build
```

不配置 API Key 也能打开网站、看页面、跑数据库初始化、基础赛程/球队/球员/本地分析功能。若要启用真实 AI 问答、新闻翻译、AI 生图，把自己的火山方舟配置写入本地 `.env`，不要提交 `.env` 到 GitHub：

```env
ARK_API_KEY=你的火山方舟Key
ARK_OPENAI_BASE_URL=https://ark.cn-beijing.volces.com/api/v3
ARK_MODEL=doubao-seed-2-1-pro-260628
ARK_IMAGE_MODEL=doubao-seedream-4-5-251128
ARK_IMAGE_BASE_URL=https://ark.cn-beijing.volces.com/api/v3
ARK_IMAGE_SIZE=2K
ARK_IMAGE_RESPONSE_FORMAT=b64_json
```

## 真实技术架构

- 后端：Python 3.12、FastAPI、SQLAlchemy、SQLite，PostgreSQL 可通过 SQLAlchemy URL 切换并自行安装驱动。
- AI 编排：LangGraph 负责 Agent 路由；LangChain ChatOpenAI 通过 ARK OpenAI 兼容 Chat Completions 调用。
- RAG：ChromaDB 持久化到 `storage/chroma`，默认使用确定性 hashing embedding，不下载大型模型，不使用随机向量。
- 新闻：内置缓存新闻 + 可选联网刷新；可信度基于来源权重、发布时间、URL、相似报道和高风险表达等规则。
- 视觉：默认 OpenCV 颜色、国旗色带、球场区域和阵型候选点启发式分析；YOLO、CLIP、ARK 视觉均为可选增强。
- 前端：React、Vite、TypeScript、TailwindCSS、ECharts、D3、TanStack Query。

## 环境配置

复制样例文件：

```powershell
Copy-Item .env.example .env
```

`.env` 已在 `.gitignore` 中，不应提交。支持的主要变量：

```env
APP_ENV=development
DATABASE_URL=sqlite:///./storage/worldcup_ai.db
ARK_API_KEY=
ARK_OPENAI_BASE_URL=https://ark.cn-beijing.volces.com/api/v3
ARK_MODEL=doubao-seed-2-1-pro-260628
LANGCHAIN_API_KEY=
LANGCHAIN_PROJECT=WorldCup-AI-Universe
LANGCHAIN_ENDPOINT=https://api.smith.langchain.com
LANGSMITH_API_KEY=
LANGSMITH_PROJECT=WorldCup-AI-Universe
LANGSMITH_ENDPOINT=https://api.smith.langchain.com
```

ARK 使用 OpenAI 兼容 Chat Completions，实际请求路径由 SDK 拼到 `/api/v3/chat/completions`。不配置 `ARK_API_KEY` 时，接口返回 `model_mode: "local"`，并在需要时提供 `fallback_reason`。

## 安装方法

后端：

```powershell
cd D:\Project114514
uv sync
```

前端：

```powershell
cd D:\Project114514\frontend
pnpm install
```

项目级 `frontend/.npmrc` 使用公共 npm registry。若当前网络无法解析 npm registry，而 Windows 系统代理类似 `127.0.0.1:7897` 已开启，可只对本次命令注入代理：

```powershell
$env:HTTP_PROXY="http://127.0.0.1:7897"
$env:HTTPS_PROXY="http://127.0.0.1:7897"
$env:NO_PROXY="127.0.0.1,localhost,::1"
pnpm install
```

pnpm 11 会阻止未知依赖执行 postinstall。本项目仅允许 Vite 必需的 `esbuild` 构建脚本，配置位于 `frontend/pnpm-workspace.yaml`。

## 后端启动

```powershell
cd D:\Project114514
uv run python backend/main.py
```

访问：

- `http://127.0.0.1:8000/api/health`
- `http://127.0.0.1:8000/docs`

## 前端启动

```powershell
cd D:\Project114514\frontend
pnpm dev
```

访问 `http://127.0.0.1:5173`。开发环境通过 Vite proxy 转发 `/api` 和 `/static` 到 `http://127.0.0.1:8000`。如果 8000 被占用，可指定：

```powershell
$env:VITE_BACKEND_ORIGIN="http://127.0.0.1:8002"
pnpm dev
```

也可以运行根目录 `start.bat`，它会检查 `uv`、`pnpm`，并打开前后端两个窗口。若 8000 已被占用，脚本会自动把后端切到 8002，并同步设置前端代理。

## 测试方法

后端离线测试不调用真实 ARK 或新闻网站：

```powershell
uv run pytest -q
uv run ruff check backend tests scripts
uv run python -m compileall backend tests scripts
```

启动后端后执行冒烟测试：

```powershell
$env:PYTHONIOENCODING="utf-8"
uv run python scripts/smoke_test.py
```

PowerShell 5.1 若显示中文乱码，先执行：

```powershell
chcp 65001
$OutputEncoding = [System.Text.Encoding]::UTF8
```

乱码通常是终端显示编码问题，不代表数据库或 JSON 被破坏。

## 接口说明

常用接口：

- `POST /api/chat`
- `GET /api/teams`
- `GET /api/players`
- `GET /api/worldcup/matches`
- `GET /api/worldcup/standings`
- `POST /api/news/refresh`
- `POST /api/news/analyze`
- `POST /api/users`
- `GET /api/users/{id}/daily`
- `POST /api/vision/analyze`
- `POST /api/generation`

详细接口也可在启动后查看 Swagger。

## 功能说明

- AI 足球大脑：按问题路由到 Football、Team、Player、News、Prediction、Vision、Generation Agent。
- Prediction Agent：本地算法先计算概率，ARK 只做解释，不改写概率；ARK 失败时返回本地完整答案。
- 球迷画像：保存支持球队、球员、竞争球队和内容偏好，重复用户名会更新同一画像。
- 世界杯中心：内置 48 支球队、69 名球员、104 场初始化赛程、12 组积分榜和世界杯历史数据。
- 新闻中心：可联网刷新 BBC Sport、ESPN、FIFA、新华社体育、央视体育等来源；网络失败时仍可读取缓存新闻。
- 视觉分析：限制 JPG/PNG/WebP、验证 MIME、限制文件大小，并使用随机运行时文件名。
- AIGC：生成可访问的 PNG 海报 URL、朋友圈文案、宣传图 Prompt、视频脚本和口号。

## 已实现能力

- 离线启动和离线测试。
- SQLite 表：`teams`、`players`、`matches`、`standings`、`news`、`users`、`history`、`recommendations`、`chat_history`。
- 数据初始化幂等。
- RAG collection 持久化和真实来源返回。
- 本地 smoke test 使用 `trust_env=False`，不会误走系统代理。
- FastAPI JSON 使用 UTF-8，文件读写显式使用 UTF-8。
- 、
