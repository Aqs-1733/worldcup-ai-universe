from collections import defaultdict
from fastapi import APIRouter, BackgroundTasks, Depends
from sqlalchemy import select
from sqlalchemy.orm import Session

from backend.core.config import get_settings
from backend.core.database import get_db
from backend.core.models import History
from backend.core.schemas import MatchOut, StandingOut
from backend.services.worldcup_live import (
    live_bracket,
    live_matches,
    live_matches_needs_refresh,
    live_standings,
    live_standings_needs_refresh,
    refresh_live_matches_cache,
    refresh_live_standings_cache,
)

router = APIRouter(prefix="/worldcup", tags=["世界杯中心"])


@router.get("/overview")
def overview(db: Session = Depends(get_db)):
    settings = get_settings()
    return {
        "target_date": settings.world_cup_target_date,
        "tournament": "WorldCup 2026 赛事中心",
        "host": "加拿大 / 墨西哥 / 美国",
        "data_mode": "ESPN Scoreboard 实时赛程/比分 + 联网新闻实时翻译",
        "notice": "比赛数据请求时按短缓存刷新；未完场次只显示来源返回的对阵和状态，不预填胜者。",
    }


@router.get("/matches", response_model=list[MatchOut])
async def matches(
    background_tasks: BackgroundTasks,
    stage: str = "",
    status: str = "",
    force: bool = False,
    db: Session = Depends(get_db),
):
    if not force and live_matches_needs_refresh():
        background_tasks.add_task(refresh_live_matches_cache)
    live_rows = await live_matches(db, force=force)
    if stage:
        live_rows = [row for row in live_rows if row["stage"] == stage or stage in row["stage"]]
    if status:
        live_rows = [row for row in live_rows if row["status"] == status]
    return live_rows


@router.get("/standings", response_model=list[StandingOut])
async def standings(
    background_tasks: BackgroundTasks,
    force: bool = False,
    db: Session = Depends(get_db),
):
    if not force and live_standings_needs_refresh():
        background_tasks.add_task(refresh_live_standings_cache)
    return await live_standings(db, force=force)


@router.get("/bracket")
async def bracket(
    background_tasks: BackgroundTasks,
    force: bool = False,
    db: Session = Depends(get_db),
):
    if not force and live_matches_needs_refresh():
        background_tasks.add_task(refresh_live_matches_cache)
    stages = defaultdict(list)
    for match in await live_bracket(db, force=force):
        stages[match["stage"]].append(MatchOut.model_validate(match).model_dump(mode="json"))
    order = ["三十二强", "十六强", "四分之一决赛", "半决赛", "三四名决赛", "决赛"]
    return [
        {"stage": stage, "matches": stages.get(stage, [])} for stage in order if stages.get(stage)
    ]


@router.get("/history")
def history(db: Session = Depends(get_db)):
    rows = db.scalars(select(History).order_by(History.year.desc())).all()
    return [
        {
            "id": x.id,
            "year": x.year,
            "host": x.host,
            "champion": x.champion,
            "runner_up": x.runner_up,
            "score": x.score,
            "golden_boot": x.golden_boot,
            "highlight": x.highlight,
        }
        for x in rows
    ]
