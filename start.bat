@echo off
setlocal
cd /d "%~dp0"
chcp 65001 >nul
title WorldCup AI Universe Launcher

where uv >nul 2>nul
if errorlevel 1 (
  echo [ERROR] uv is not installed or not in PATH.
  echo Install uv first: https://docs.astral.sh/uv/getting-started/installation/
  pause
  exit /b 1
)

where pnpm >nul 2>nul
if errorlevel 1 (
  echo [ERROR] pnpm is not installed or not in PATH.
  echo Install pnpm first: corepack enable ^&^& corepack prepare pnpm@11.7.0 --activate
  pause
  exit /b 1
)

if not exist .env (
  if exist .env.example (
    copy .env.example .env >nul
    echo Created .env from .env.example. Fill API keys only if you need online model features.
  ) else (
    echo [WARN] .env.example was not found; backend will use built-in defaults.
  )
)

if not exist frontend\.env (
  if exist frontend\.env.example (
    copy frontend\.env.example frontend\.env >nul
    echo Created frontend\.env from frontend\.env.example.
  )
)

set BACKEND_PORT=
for %%P in (8000 8001 8002 8003 8004 8005 8006 8007 8008 8009 8010) do (
  if not defined BACKEND_PORT (
    powershell -NoProfile -Command "if (Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort %%P -State Listen -ErrorAction SilentlyContinue) { exit 1 }"
    if not errorlevel 1 set BACKEND_PORT=%%P
  )
)
if not defined BACKEND_PORT (
  echo [ERROR] Ports 8000-8010 are all in use.
  pause
  exit /b 1
)
if not "%BACKEND_PORT%"=="8000" echo [WARN] 127.0.0.1:8000 is in use. Backend will start on %BACKEND_PORT%.

start "WorldCup AI Backend" cmd /k "cd /d "%~dp0" && set "APP_ENV=production" && set "APP_PORT=%BACKEND_PORT%" && uv sync && uv run uvicorn backend.main:app --host 127.0.0.1 --port %BACKEND_PORT%"
start "WorldCup AI Frontend" cmd /k "cd /d "%~dp0frontend" && set "VITE_BACKEND_ORIGIN=http://127.0.0.1:%BACKEND_PORT%" && pnpm install && pnpm dev"
echo Backend: http://127.0.0.1:%BACKEND_PORT%/docs
echo Frontend: http://127.0.0.1:5173
echo Close the two opened terminal windows to stop the services.
echo The browser will open now. If dependencies are still installing, refresh the page after a minute.
start "" "http://127.0.0.1:5173"
endlocal
