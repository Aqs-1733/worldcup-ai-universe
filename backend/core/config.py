from __future__ import annotations

from functools import lru_cache
from pathlib import Path
from typing import List

from pydantic import Field, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict

ROOT_DIR = Path(__file__).resolve().parents[2]


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=str(ROOT_DIR / ".env"),
        env_file_encoding="utf-8",
        extra="ignore",
        case_sensitive=False,
        enable_decoding=False,
    )

    app_env: str = "development"
    app_host: str = "127.0.0.1"
    app_port: int = 8000
    database_url: str = "sqlite:///./storage/worldcup_ai.db"
    cors_origins: List[str] = Field(
        default_factory=lambda: ["http://localhost:5173", "http://127.0.0.1:5173"]
    )

    ark_api_key: str = ""
    ark_openai_base_url: str = "https://ark.cn-beijing.volces.com/api/v3"
    ark_model: str = "doubao-seed-2-1-pro-260628"
    ark_embedding_model: str = ""
    ark_image_model: str = ""
    ark_image_base_url: str = ""
    ark_image_size: str = "2K"
    ark_image_response_format: str = "b64_json"

    langchain_api_key: str = ""
    langchain_endpoint: str = "https://api.smith.langchain.com"
    langsmith_api_key: str = ""
    langsmith_endpoint: str = ""
    langsmith_project: str = ""
    langchain_tracing_v2: bool = False
    langchain_project: str = "WorldCup-AI-Universe"

    news_refresh_minutes: int = 5
    worldcup_live_refresh_seconds: int = 45
    world_cup_target_date: str = "2026-07-19T12:00:00-07:00"
    vision_model: str = ""
    yolo_model_path: str = ""
    clip_model_name: str = "openai/clip-vit-base-patch32"
    max_upload_mb: int = 12

    @field_validator("cors_origins", mode="before")
    @classmethod
    def parse_cors(cls, value):
        if isinstance(value, str):
            return [item.strip() for item in value.split(",") if item.strip()]
        return value

    @property
    def sqlite_path(self) -> Path | None:
        prefix = "sqlite:///"
        if not self.database_url.startswith(prefix):
            return None
        raw = self.database_url.removeprefix(prefix)
        path = Path(raw)
        return path if path.is_absolute() else ROOT_DIR / path

    @property
    def llm_enabled(self) -> bool:
        return bool(self.ark_api_key and self.ark_openai_base_url and self.ark_model)

    @property
    def image_generation_enabled(self) -> bool:
        return bool(self.ark_api_key and self.ark_image_model)


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    settings = Settings()
    if settings.sqlite_path:
        settings.sqlite_path.parent.mkdir(parents=True, exist_ok=True)
    (ROOT_DIR / "storage" / "uploads").mkdir(parents=True, exist_ok=True)
    (ROOT_DIR / "storage" / "chroma").mkdir(parents=True, exist_ok=True)
    return settings
