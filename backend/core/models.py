from __future__ import annotations

from datetime import datetime, timezone
from typing import Any

from sqlalchemy import (
    Boolean,
    DateTime,
    Float,
    ForeignKey,
    Integer,
    JSON,
    String,
    Text,
    UniqueConstraint,
)
from sqlalchemy.orm import Mapped, mapped_column, relationship

from backend.core.database import Base


def utcnow() -> datetime:
    return datetime.now(timezone.utc)


class Team(Base):
    __tablename__ = "teams"
    id: Mapped[int] = mapped_column(primary_key=True)
    slug: Mapped[str] = mapped_column(String(80), unique=True, index=True)
    name: Mapped[str] = mapped_column(String(80), index=True)
    english_name: Mapped[str] = mapped_column(String(120))
    country_code: Mapped[str] = mapped_column(String(8), index=True)
    flag: Mapped[str] = mapped_column(String(16))
    confederation: Mapped[str] = mapped_column(String(32))
    world_rank: Mapped[int] = mapped_column(Integer)
    coach: Mapped[str] = mapped_column(String(100))
    stadium: Mapped[str] = mapped_column(String(140), default="")
    titles: Mapped[int] = mapped_column(Integer, default=0)
    appearances: Mapped[int] = mapped_column(Integer, default=0)
    best_result: Mapped[str] = mapped_column(String(120), default="")
    history: Mapped[str] = mapped_column(Text, default="")
    style: Mapped[str] = mapped_column(Text, default="")
    strengths: Mapped[list[str]] = mapped_column(JSON, default=list)
    weaknesses: Mapped[list[str]] = mapped_column(JSON, default=list)
    form: Mapped[list[str]] = mapped_column(JSON, default=list)
    win_probability: Mapped[float] = mapped_column(Float, default=0.0)
    primary_color: Mapped[str] = mapped_column(String(16), default="#0f172a")
    secondary_color: Mapped[str] = mapped_column(String(16), default="#f8fafc")
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)
    players: Mapped[list["Player"]] = relationship(back_populates="team")


class Player(Base):
    __tablename__ = "players"
    id: Mapped[int] = mapped_column(primary_key=True)
    slug: Mapped[str] = mapped_column(String(100), unique=True, index=True)
    name: Mapped[str] = mapped_column(String(100), index=True)
    english_name: Mapped[str] = mapped_column(String(140))
    team_id: Mapped[int | None] = mapped_column(ForeignKey("teams.id"), nullable=True)
    country: Mapped[str] = mapped_column(String(80))
    flag: Mapped[str] = mapped_column(String(16))
    position: Mapped[str] = mapped_column(String(40), index=True)
    club: Mapped[str] = mapped_column(String(120))
    number: Mapped[int | None] = mapped_column(Integer, nullable=True)
    age: Mapped[int | None] = mapped_column(Integer, nullable=True)
    is_legend: Mapped[bool] = mapped_column(Boolean, default=False)
    career: Mapped[str] = mapped_column(Text, default="")
    world_cup_appearances: Mapped[int] = mapped_column(Integer, default=0)
    world_cup_goals: Mapped[int] = mapped_column(Integer, default=0)
    world_cup_assists: Mapped[int] = mapped_column(Integer, default=0)
    rating: Mapped[float] = mapped_column(Float, default=0.0)
    form_score: Mapped[float] = mapped_column(Float, default=0.0)
    strengths: Mapped[list[str]] = mapped_column(JSON, default=list)
    weaknesses: Mapped[list[str]] = mapped_column(JSON, default=list)
    impact: Mapped[str] = mapped_column(Text, default="")
    avatar_url: Mapped[str] = mapped_column(String(500), default="")
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)
    team: Mapped[Team | None] = relationship(back_populates="players")


class Match(Base):
    __tablename__ = "matches"
    id: Mapped[int] = mapped_column(primary_key=True)
    stage: Mapped[str] = mapped_column(String(80), index=True)
    group_name: Mapped[str] = mapped_column(String(20), default="")
    home_team: Mapped[str] = mapped_column(String(80), index=True)
    away_team: Mapped[str] = mapped_column(String(80), index=True)
    home_flag: Mapped[str] = mapped_column(String(16), default="")
    away_flag: Mapped[str] = mapped_column(String(16), default="")
    kickoff: Mapped[datetime] = mapped_column(DateTime(timezone=True), index=True)
    venue: Mapped[str] = mapped_column(String(140), default="")
    status: Mapped[str] = mapped_column(String(24), default="scheduled", index=True)
    home_score: Mapped[int | None] = mapped_column(Integer, nullable=True)
    away_score: Mapped[int | None] = mapped_column(Integer, nullable=True)
    home_penalties: Mapped[int | None] = mapped_column(Integer, nullable=True)
    away_penalties: Mapped[int | None] = mapped_column(Integer, nullable=True)
    minute: Mapped[int | None] = mapped_column(Integer, nullable=True)
    stats: Mapped[dict[str, Any]] = mapped_column(JSON, default=dict)


class News(Base):
    __tablename__ = "news"
    id: Mapped[int] = mapped_column(primary_key=True)
    title: Mapped[str] = mapped_column(String(500), index=True)
    summary: Mapped[str] = mapped_column(Text, default="")
    source: Mapped[str] = mapped_column(String(120), index=True)
    source_url: Mapped[str] = mapped_column(String(1000), default="")
    image_url: Mapped[str] = mapped_column(String(1000), default="")
    published_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), index=True)
    fetched_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)
    category: Mapped[str] = mapped_column(String(80), default="综合")
    related_teams: Mapped[list[str]] = mapped_column(JSON, default=list)
    related_players: Mapped[list[str]] = mapped_column(JSON, default=list)
    credibility_score: Mapped[float] = mapped_column(Float, default=0.0)
    credibility_label: Mapped[str] = mapped_column(String(40), default="待验证")
    verification: Mapped[dict[str, Any]] = mapped_column(JSON, default=dict)
    content_hash: Mapped[str] = mapped_column(String(64), unique=True, index=True)


class UserProfile(Base):
    __tablename__ = "users"
    id: Mapped[int] = mapped_column(primary_key=True)
    username: Mapped[str] = mapped_column(String(80), unique=True, index=True)
    password_hash: Mapped[str] = mapped_column(String(260), default="")
    favorite_teams: Mapped[list[str]] = mapped_column(JSON, default=list)
    favorite_players: Mapped[list[str]] = mapped_column(JSON, default=list)
    dislike_teams: Mapped[list[str]] = mapped_column(JSON, default=list)
    favorite_content_type: Mapped[str] = mapped_column(String(80), default="综合")
    fan_persona: Mapped[str] = mapped_column(Text, default="")
    onboarding_completed: Mapped[bool] = mapped_column(Boolean, default=False)
    created_time: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)
    updated_time: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), default=utcnow, onupdate=utcnow
    )
    recommendations: Mapped[list["Recommendation"]] = relationship(
        back_populates="user", cascade="all, delete-orphan"
    )


class History(Base):
    __tablename__ = "history"
    id: Mapped[int] = mapped_column(primary_key=True)
    year: Mapped[int] = mapped_column(Integer, index=True)
    host: Mapped[str] = mapped_column(String(120))
    champion: Mapped[str] = mapped_column(String(80))
    runner_up: Mapped[str] = mapped_column(String(80))
    score: Mapped[str] = mapped_column(String(40))
    golden_boot: Mapped[str] = mapped_column(String(120), default="")
    highlight: Mapped[str] = mapped_column(Text, default="")


class Recommendation(Base):
    __tablename__ = "recommendations"
    id: Mapped[int] = mapped_column(primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"), index=True)
    title: Mapped[str] = mapped_column(String(200))
    content: Mapped[str] = mapped_column(Text)
    category: Mapped[str] = mapped_column(String(80), default="日报")
    priority: Mapped[int] = mapped_column(Integer, default=50)
    related_entity: Mapped[str] = mapped_column(String(160), default="")
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)
    expires_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True), nullable=True)
    user: Mapped[UserProfile] = relationship(back_populates="recommendations")


class ChatHistory(Base):
    __tablename__ = "chat_history"
    id: Mapped[int] = mapped_column(primary_key=True)
    session_id: Mapped[str] = mapped_column(String(100), index=True)
    user_id: Mapped[int | None] = mapped_column(ForeignKey("users.id"), nullable=True)
    role: Mapped[str] = mapped_column(String(20))
    content: Mapped[str] = mapped_column(Text)
    agent: Mapped[str] = mapped_column(String(60), default="")
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)


class Standing(Base):
    __tablename__ = "standings"
    __table_args__ = (UniqueConstraint("group_name", "team", name="uq_group_team"),)
    id: Mapped[int] = mapped_column(primary_key=True)
    group_name: Mapped[str] = mapped_column(String(20), index=True)
    team: Mapped[str] = mapped_column(String(80))
    flag: Mapped[str] = mapped_column(String(16), default="")
    played: Mapped[int] = mapped_column(Integer, default=0)
    won: Mapped[int] = mapped_column(Integer, default=0)
    drawn: Mapped[int] = mapped_column(Integer, default=0)
    lost: Mapped[int] = mapped_column(Integer, default=0)
    goals_for: Mapped[int] = mapped_column(Integer, default=0)
    goals_against: Mapped[int] = mapped_column(Integer, default=0)
    goal_difference: Mapped[int] = mapped_column(Integer, default=0)
    points: Mapped[int] = mapped_column(Integer, default=0)
