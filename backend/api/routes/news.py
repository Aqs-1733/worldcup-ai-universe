from datetime import datetime, timezone

from fastapi import APIRouter, BackgroundTasks, Depends, HTTPException, Query
from sqlalchemy import desc, or_, select
from sqlalchemy.orm import Session

from backend.core.config import get_settings
from backend.core.database import get_db
from backend.core.models import News
from backend.core.schemas import NewsAnalyzeRequest, NewsDetailOut, NewsOut
from backend.services.news import NewsService

router = APIRouter(prefix="/news", tags=["新闻中心"])
service = NewsService()


async def refresh_news_background():
    from backend.core.database import SessionLocal

    with SessionLocal() as session:
        await service.refresh(session)


@router.get("", response_model=list[NewsOut])
async def list_news(
    background_tasks: BackgroundTasks,
    q: str = "",
    source: str = "",
    category: str = "",
    limit: int = Query(80, ge=1, le=150),
    db: Session = Depends(get_db),
):
    settings = get_settings()
    latest_fetched = db.scalar(
        select(News.fetched_at).where(News.source_url != "").order_by(desc(News.fetched_at)).limit(1)
    )
    needs_refresh = latest_fetched is None
    if latest_fetched is not None:
        if latest_fetched.tzinfo is None:
            latest_fetched = latest_fetched.replace(tzinfo=timezone.utc)
        age_minutes = (datetime.now(timezone.utc) - latest_fetched.astimezone(timezone.utc)).total_seconds() / 60
        needs_refresh = age_minutes >= settings.news_refresh_minutes
    has_cached_news = db.scalar(select(News.id).where(News.source_url != "").limit(1)) is not None
    if needs_refresh and has_cached_news:
        background_tasks.add_task(refresh_news_background)
    elif needs_refresh:
        await service.refresh(db)
    stmt = select(News).where(News.source_url != "")
    if q:
        stmt = stmt.where(or_(News.title.ilike(f"%{q}%"), News.summary.ilike(f"%{q}%")))
    if source:
        stmt = stmt.where(News.source == source)
    fetch_limit = limit if not category else min(300, max(limit * 3, 180))
    rows = db.scalars(stmt.order_by(desc(News.published_at)).limit(fetch_limit)).all()
    if category:
        rows = [
            row
            for row in rows
            if category == row.category or category in service.categories_for_item(row)
        ][:limit]
    return await service.localize_listing(db, rows)


@router.post("/refresh")
async def refresh_news(db: Session = Depends(get_db)):
    return await service.refresh(db)


@router.get("/{news_id}", response_model=NewsDetailOut)
async def news_detail(news_id: int, db: Session = Depends(get_db)):
    item = db.get(News, news_id)
    if not item or not item.source_url:
        raise HTTPException(404, "新闻不存在或没有来源链接")
    detail = await service.detail(item)
    base = service._news_payload(item)
    return {**base, **detail}


@router.post("/analyze")
async def analyze_news(payload: NewsAnalyzeRequest, db: Session = Depends(get_db)):
    result = service.analyze(
        db, payload.title, payload.content, payload.source, payload.published_at
    )
    explanation = await service.explain_analysis(result, payload.title, payload.content)
    return {**result, "ai_explanation": explanation}
