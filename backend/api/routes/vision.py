import logging
from pathlib import Path

from fastapi import APIRouter, File, Form, HTTPException, UploadFile

from backend.core.schemas import VisionResponse
from backend.services.vision import get_vision_service

router = APIRouter(prefix="/vision", tags=["计算机视觉"])
logger = logging.getLogger(__name__)
ALLOWED_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}
ALLOWED_CONTENT_TYPES = {"image/jpeg", "image/png", "image/webp"}


@router.post("/analyze", response_model=VisionResponse)
async def analyze_image(file: UploadFile = File(...), analysis_type: str = Form("auto")):
    suffix = Path(file.filename or "").suffix.lower()
    if suffix not in ALLOWED_EXTENSIONS or file.content_type not in ALLOWED_CONTENT_TYPES:
        raise HTTPException(400, "只支持 JPG、PNG 或 WebP 图片文件")
    try:
        content = await file.read()
        return await get_vision_service().analyze(
            content, file.filename or "upload.jpg", analysis_type
        )
    except ValueError as exc:
        raise HTTPException(400, str(exc)) from exc
    except Exception as exc:
        logger.exception("Vision analysis failed")
        raise HTTPException(500, "视觉分析失败，请确认图片可被正常打开后重试") from exc
