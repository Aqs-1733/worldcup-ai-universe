from fastapi import APIRouter, Query

from backend.services.rag import get_rag_service

router = APIRouter(prefix="/rag", tags=["RAG知识库"])


@router.get("/search")
def search(q: str = Query(min_length=2), k: int = Query(5, ge=1, le=12)):
    return {"query": q, "results": get_rag_service().search(q, k)}


@router.post("/rebuild")
def rebuild():
    count = get_rag_service().build(force=True)
    return {"status": "rebuilt", "documents": count}
