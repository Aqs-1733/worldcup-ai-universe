from fastapi import APIRouter, Depends
from sqlalchemy import func, select
from sqlalchemy.orm import Session

from backend.core.config import get_settings
from backend.core.database import get_db
from backend.core.models import Match, News, Player, Team, UserProfile

router = APIRouter(tags=["system"])


@router.get("/health")
def health(db: Session = Depends(get_db)):
    settings = get_settings()
    return {
        "status": "ok",
        "name": "WorldCup AI Universe",
        "llm_mode": "ark" if settings.llm_enabled else "local",
        "image_generation": "enabled" if settings.image_generation_enabled else "not_configured",
        "image_model_configured": settings.image_generation_enabled,
        "database": "sqlite" if settings.database_url.startswith("sqlite") else "postgresql",
        "counts": {
            "teams": db.scalar(select(func.count()).select_from(Team)),
            "players": db.scalar(select(func.count()).select_from(Player)),
            "matches": db.scalar(select(func.count()).select_from(Match)),
            "news": db.scalar(select(func.count()).select_from(News)),
            "users": db.scalar(select(func.count()).select_from(UserProfile)),
        },
        "data_notice": "球员来自FIFA官方名单同步；新闻来自外部来源抓取并保留来源URL。未抓到的数据不使用本地兜底填充。",
    }
