from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from backend.core.database import get_db
from backend.core.models import ChatHistory
from backend.core.schemas import ChatMessage, ChatResponse
from backend.services.graph import get_football_graph

router = APIRouter(prefix="/chat", tags=["AI足球大脑"])


@router.post("", response_model=ChatResponse)
async def chat(payload: ChatMessage, db: Session = Depends(get_db)):
    db.add(
        ChatHistory(
            session_id=payload.session_id,
            user_id=payload.user_id,
            role="user",
            content=payload.message,
        )
    )
    db.commit()
    result = await get_football_graph().invoke(payload.message, payload.session_id, payload.user_id)
    db.add(
        ChatHistory(
            session_id=payload.session_id,
            user_id=payload.user_id,
            role="assistant",
            content=result["answer"],
            agent=result["agent"],
        )
    )
    db.commit()
    return result
