from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy import or_, select
from sqlalchemy.orm import Session

from backend.core.database import get_db
from backend.core.models import Player, Team
from backend.core.schemas import PlayerOut, TeamOut
from backend.services.worldcup_live import team_live_forms

router = APIRouter(prefix="/teams", tags=["球队中心"])


@router.get("", response_model=list[TeamOut])
async def list_teams(q: str = "", confederation: str = "", db: Session = Depends(get_db)):
    stmt = select(Team)
    if q:
        stmt = stmt.where(or_(Team.name.ilike(f"%{q}%"), Team.english_name.ilike(f"%{q}%")))
    if confederation:
        stmt = stmt.where(Team.confederation == confederation)
    teams = db.scalars(stmt.order_by(Team.world_rank)).all()
    live_forms = await team_live_forms(db)
    for team in teams:
        if team.name in live_forms:
            team.form = live_forms[team.name]
    return teams


@router.get("/{slug}")
async def team_detail(slug: str, db: Session = Depends(get_db)):
    team = db.scalar(select(Team).where(or_(Team.slug == slug, Team.name == slug)))
    if not team:
        raise HTTPException(404, "球队不存在")
    live_forms = await team_live_forms(db)
    if team.name in live_forms:
        team.form = live_forms[team.name]
    players = db.scalars(
        select(Player).where(Player.team_id == team.id).order_by(Player.position, Player.number)
    ).all()
    return {
        "team": TeamOut.model_validate(team),
        "squad": [PlayerOut.model_validate(p) for p in players],
    }
