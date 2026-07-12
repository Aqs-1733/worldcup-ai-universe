from __future__ import annotations

from datetime import datetime
from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator


class ORMModel(BaseModel):
    model_config = ConfigDict(from_attributes=True)


class TeamOut(ORMModel):
    id: int
    slug: str
    name: str
    english_name: str
    country_code: str
    flag: str
    confederation: str
    world_rank: int
    coach: str
    stadium: str
    titles: int
    appearances: int
    best_result: str
    history: str
    style: str
    strengths: list[str]
    weaknesses: list[str]
    form: list[str]
    win_probability: float
    primary_color: str
    secondary_color: str


class PlayerOut(ORMModel):
    id: int
    slug: str
    name: str
    english_name: str
    team_id: int | None
    country: str
    flag: str
    position: str
    club: str
    number: int | None
    age: int | None
    is_legend: bool
    career: str
    world_cup_appearances: int
    world_cup_goals: int
    world_cup_assists: int
    rating: float
    form_score: float
    strengths: list[str]
    weaknesses: list[str]
    impact: str
    avatar_url: str


class MatchOut(ORMModel):
    id: int
    stage: str
    group_name: str
    home_team: str
    away_team: str
    home_flag: str
    away_flag: str
    kickoff: datetime
    venue: str
    status: str
    home_score: int | None
    away_score: int | None
    home_penalties: int | None
    away_penalties: int | None
    minute: int | None
    stats: dict[str, Any]


class NewsOut(ORMModel):
    id: int
    title: str
    summary: str
    original_title: str = ""
    original_summary: str = ""
    language: str = "unknown"
    source: str
    source_url: str
    image_url: str
    published_at: datetime
    category: str
    categories: list[str] = Field(default_factory=list)
    related_teams: list[str]
    related_players: list[str]
    credibility_score: float
    credibility_label: str
    verification: dict[str, Any]


class NewsDetailOut(NewsOut):
    content: str = ""
    translated_title: str = ""
    translated_summary: str = ""
    translated_content: str = ""
    language: str = "unknown"


class StandingOut(ORMModel):
    id: int
    group_name: str
    team: str
    flag: str
    played: int
    won: int
    drawn: int
    lost: int
    goals_for: int
    goals_against: int
    goal_difference: int
    points: int


class UserProfileCreate(BaseModel):
    username: str = Field(min_length=2, max_length=80)
    favorite_teams: list[str] = Field(default_factory=list)
    favorite_players: list[str] = Field(default_factory=list)
    dislike_teams: list[str] = Field(default_factory=list)
    favorite_content_type: str = "综合"
    onboarding_completed: bool = True

    @field_validator("favorite_teams", "favorite_players", "dislike_teams")
    @classmethod
    def clean_list(cls, value: list[str]) -> list[str]:
        return list(dict.fromkeys(item.strip() for item in value if item.strip()))[:20]


class UserProfileOut(ORMModel):
    id: int
    username: str
    favorite_teams: list[str]
    favorite_players: list[str]
    dislike_teams: list[str]
    favorite_content_type: str
    fan_persona: str
    onboarding_completed: bool
    created_time: datetime
    updated_time: datetime


class LoginRequest(BaseModel):
    username: str = Field(min_length=2, max_length=80)
    password: str = Field(min_length=6, max_length=128)


class RegisterRequest(LoginRequest):
    pass


class RecommendationOut(ORMModel):
    id: int
    user_id: int
    title: str
    content: str
    category: str
    priority: int
    related_entity: str
    created_at: datetime
    expires_at: datetime | None


class ChatMessage(BaseModel):
    message: str = Field(min_length=1, max_length=4000)
    session_id: str = Field(default="web-session", min_length=1, max_length=100)
    user_id: int | None = None


class ChatResponse(BaseModel):
    answer: str
    agent: str
    route_reason: str
    sources: list[dict[str, Any]] = Field(default_factory=list)
    suggested_questions: list[str] = Field(default_factory=list)
    model_mode: Literal["ark", "local"] = "local"
    metadata: dict[str, Any] = Field(default_factory=dict)


class GenerationRequest(BaseModel):
    topic: str = Field(min_length=2, max_length=300)
    team: str = ""
    player: str = ""
    tone: str = "热血"
    generate_image: bool = False


class GenerationResponse(BaseModel):
    topic: str
    social_copy: str
    poster_prompt: str
    video_script: str
    slogans: list[str]
    model_mode: str
    poster_url: str
    image_error: str = ""
    image_source: str = ""


class NewsAnalyzeRequest(BaseModel):
    title: str = Field(min_length=3, max_length=500)
    content: str = Field(default="", max_length=8000)
    source: str = Field(default="未知来源", max_length=120)
    published_at: datetime | None = None


class VisionResponse(BaseModel):
    analysis_type: str
    detected_team: str | None = None
    detected_flag: str | None = None
    formation: str | None = None
    confidence: float
    observations: list[str]
    tactical_analysis: dict[str, Any]
    model_mode: str
    annotated_image_url: str | None = None
