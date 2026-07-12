from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy import or_, select
from sqlalchemy.orm import Session

from backend.core.database import get_db
from backend.core.models import Player
from backend.core.schemas import PlayerOut
from backend.services.player_translation import translate_missing_player_names
from backend.services.player_sync import get_player_sync_service, player_fame_rank

router = APIRouter(prefix="/players", tags=["球员中心"])


@router.get("", response_model=list[PlayerOut])
def list_players(
    q: str = "",
    country: str = "",
    position: str = "",
    legends: bool | None = None,
    db: Session = Depends(get_db),
):
    stmt = select(Player)
    if q:
        stmt = stmt.where(
            or_(
                Player.name.ilike(f"%{q}%"),
                Player.english_name.ilike(f"%{q}%"),
                Player.club.ilike(f"%{q}%"),
            )
        )
    if country:
        stmt = stmt.where(Player.country == country)
    if position:
        stmt = stmt.where(Player.position == position)
    if legends is not None:
        stmt = stmt.where(Player.is_legend == legends)
    rows = db.scalars(stmt).all()
    return sorted(
        rows,
        key=lambda player: (
            player.is_legend,
            player_fame_rank(player.name, player.english_name),
            -player.world_cup_goals,
            -player.world_cup_appearances,
            player.country,
            player.name,
        ),
    )


@router.post("/refresh/official")
async def refresh_official_players(db: Session = Depends(get_db)):
    result = await get_player_sync_service().refresh_from_fifa(db)
    translation = await translate_missing_player_names(db)
    return {**result, "translation": translation}


@router.post("/translate-names")
async def translate_names(db: Session = Depends(get_db)):
    return await translate_missing_player_names(db)


@router.get("/{slug}", response_model=PlayerOut)
def player_detail(slug: str, db: Session = Depends(get_db)):
    player = db.scalar(select(Player).where(or_(Player.slug == slug, Player.name == slug)))
    if not player:
        raise HTTPException(404, "球员不存在")
    return player
