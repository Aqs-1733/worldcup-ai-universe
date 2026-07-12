from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy import select
from sqlalchemy.orm import Session

from backend.core.database import get_db
from backend.core.models import UserProfile
from backend.core.schemas import (
    LoginRequest,
    RecommendationOut,
    RegisterRequest,
    UserProfileCreate,
    UserProfileOut,
)
from backend.services.auth import hash_password, verify_password
from backend.services.recommendation import RecommendationService, build_persona

router = APIRouter(prefix="/users", tags=["球迷画像"])
recommendation_service = RecommendationService()


@router.get("", response_model=list[UserProfileOut])
def list_users(db: Session = Depends(get_db)):
    return db.scalars(select(UserProfile).order_by(UserProfile.id)).all()


@router.post("", response_model=UserProfileOut)
def create_or_update_user(payload: UserProfileCreate, db: Session = Depends(get_db)):
    user = db.scalar(select(UserProfile).where(UserProfile.username == payload.username))
    if user is None:
        raise HTTPException(404, "用户不存在，请先注册登录")
    user.favorite_teams = payload.favorite_teams
    user.favorite_players = payload.favorite_players
    user.dislike_teams = payload.dislike_teams
    user.favorite_content_type = payload.favorite_content_type
    user.onboarding_completed = payload.onboarding_completed
    user.fan_persona = build_persona(user)
    db.commit()
    db.refresh(user)
    return user


@router.post("/login", response_model=UserProfileOut)
def login(payload: LoginRequest, db: Session = Depends(get_db)):
    username = payload.username.strip()
    user = db.scalar(select(UserProfile).where(UserProfile.username == username))
    if user is None or not user.password_hash or not verify_password(payload.password, user.password_hash):
        raise HTTPException(401, "用户名或密码错误")
    return user


@router.post("/register", response_model=UserProfileOut, status_code=201)
def register(payload: RegisterRequest, db: Session = Depends(get_db)):
    username = payload.username.strip()
    user = db.scalar(select(UserProfile).where(UserProfile.username == username))
    if user and user.password_hash:
        raise HTTPException(409, "用户名已被注册")
    if user is None:
        user = UserProfile(username=username, onboarding_completed=False)
        db.add(user)
    user.password_hash = hash_password(payload.password)
    db.commit()
    db.refresh(user)
    return user


@router.get("/{user_id}", response_model=UserProfileOut)
def get_user(user_id: int, db: Session = Depends(get_db)):
    user = db.get(UserProfile, user_id)
    if not user:
        raise HTTPException(404, "用户不存在")
    return user


@router.get("/{user_id}/daily", response_model=list[RecommendationOut])
async def daily(user_id: int, force: bool = False, db: Session = Depends(get_db)):
    user = db.get(UserProfile, user_id)
    if not user:
        raise HTTPException(404, "用户不存在")
    return await recommendation_service.generate_daily(db, user, force=force)
