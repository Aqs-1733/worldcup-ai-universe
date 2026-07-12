from __future__ import annotations

import logging
import sys
from contextlib import asynccontextmanager
from pathlib import Path

if __package__ in {None, ""}:
    sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import uvicorn
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import ORJSONResponse
from fastapi.staticfiles import StaticFiles
from sqlalchemy import inspect, text

from backend.api.router import api_router
from backend.core.config import ROOT_DIR, get_settings
from backend.core.database import Base, SessionLocal, engine
from backend.core.seed import seed_database
from backend.services.rag import get_rag_service

logging.basicConfig(
    level=logging.INFO, format="%(asctime)s | %(levelname)s | %(name)s | %(message)s"
)
logger = logging.getLogger("worldcup-ai")
settings = get_settings()


def ensure_runtime_schema() -> None:
    inspector = inspect(engine)
    if "users" in inspector.get_table_names():
        columns = {column["name"] for column in inspector.get_columns("users")}
        if "onboarding_completed" not in columns:
            with engine.begin() as connection:
                connection.execute(
                    text("ALTER TABLE users ADD COLUMN onboarding_completed BOOLEAN DEFAULT 0")
                )
        if "password_hash" not in columns:
            with engine.begin() as connection:
                connection.execute(text("ALTER TABLE users ADD COLUMN password_hash VARCHAR(260) DEFAULT ''"))


@asynccontextmanager
async def lifespan(app: FastAPI):
    Base.metadata.create_all(bind=engine)
    ensure_runtime_schema()
    with SessionLocal() as db:
        seed_database(db)
    try:
        docs = get_rag_service().build()
        logger.info("RAG knowledge base ready with %s documents", docs)
    except Exception as exc:
        logger.exception("RAG initialization failed; other features remain available: %s", exc)
    yield


app = FastAPI(
    title="WorldCup AI Universe API",
    description="世界杯AI数字球迷生态系统：LangGraph 多Agent、RAG、新闻核验、视觉分析与AIGC。",
    version="1.0.0",
    lifespan=lifespan,
    default_response_class=ORJSONResponse,
)
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)
app.include_router(api_router)

for directory in ["uploads", "generated", "analysis"]:
    path = ROOT_DIR / "storage" / directory
    path.mkdir(parents=True, exist_ok=True)
app.mount("/static", StaticFiles(directory=str(ROOT_DIR / "storage")), name="static")


@app.get("/")
def root():
    return {"name": "WorldCup AI Universe", "docs": "/docs", "health": "/api/health"}


if __name__ == "__main__":
    uvicorn.run(
        "backend.main:app",
        host=settings.app_host,
        port=settings.app_port,
        reload=settings.app_env == "development",
    )
