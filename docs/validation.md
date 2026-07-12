# 本地验证记录

完成的验证项目：

- `uv sync`：成功解析并安装后端依赖。
- `uv run pytest -q`：7 项测试全部通过。
- `uv run ruff check backend scripts tests`：通过。
- `uv run python -m compileall -q backend scripts tests`：通过。
- `uv run python backend/main.py`：成功启动。
- `/api/health`：返回 48 支球队、69 名球员（61 名现役 + 8 名传奇）、104 场比赛、3 条初始化新闻和 1 个演示用户。
- `/api/worldcup/standings`：返回 12 个小组、48 条积分记录。
- 前端所有 TypeScript/TSX 文件：TypeScript 解析器语法诊断为 0。

当前制作环境无法解析 `registry.npmjs.org`，因此无法在此环境下载前端依赖并执行 `pnpm build`。项目已提供完整 `package.json`、Vite、TypeScript、TailwindCSS、ESLint 配置及源码；在网络正常的 Windows 环境执行 `pnpm install` 后即可构建和启动。
