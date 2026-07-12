from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from backend.core.database import get_db
from backend.core.schemas import GenerationRequest, GenerationResponse
from backend.services.generation import get_generation_service

router = APIRouter(prefix="/generation", tags=["AIGC创作"])


@router.post("", response_model=GenerationResponse)
async def generate(payload: GenerationRequest, db: Session = Depends(get_db)):
    return await get_generation_service().generate(
        db,
        payload.topic,
        payload.team,
        payload.player,
        payload.tone,
        generate_image=payload.generate_image,
    )
