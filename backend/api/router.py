from fastapi import APIRouter

from backend.api.routes import (
    chat,
    generation,
    health,
    news,
    players,
    rag,
    teams,
    users,
    vision,
    worldcup,
)

api_router = APIRouter(prefix="/api")
api_router.include_router(health.router)
api_router.include_router(chat.router)
api_router.include_router(teams.router)
api_router.include_router(players.router)
api_router.include_router(worldcup.router)
api_router.include_router(news.router)
api_router.include_router(users.router)
api_router.include_router(generation.router)
api_router.include_router(vision.router)
api_router.include_router(rag.router)
