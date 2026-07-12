from __future__ import annotations

import json
import re
from datetime import datetime, timezone

from sqlalchemy import select
from sqlalchemy.orm import Session

from backend.core.models import Player
from backend.services.llm import get_llm_service
from backend.services.player_sync import common_chinese_name


def has_cjk(text: str) -> bool:
    return any("\u4e00" <= char <= "\u9fff" for char in text)


def _extract_json_array(raw: str | None) -> list[dict]:
    if not raw:
        return []
    text = re.sub(r"```(?:json)?|```", "", raw).strip()
    start = text.find("[")
    end = text.rfind("]")
    if start >= 0 and end > start:
        text = text[start : end + 1]
    try:
        data = json.loads(text)
    except Exception:
        return []
    return data if isinstance(data, list) else []


async def _translate_batch(players: list[Player]) -> dict[int, str]:
    payload = [
        {
            "id": player.id,
            "name": player.english_name or player.name,
            "country": player.country,
            "position": player.position,
        }
        for player in players
    ]
    raw = await get_llm_service().complete(
        "你是体育资料中文编辑。把球员英文名转写为常见简体中文译名；若没有广泛固定译名，给出自然、简洁的中文音译。不要改变人物身份，不要加入俱乐部或国家。只返回严格 JSON 数组，每项包含 id 和 name。",
        json.dumps(payload, ensure_ascii=False),
        temperature=0.05,
        timeout_s=60,
        max_tokens=5200,
    )
    result: dict[int, str] = {}
    for row in _extract_json_array(raw):
        try:
            player_id = int(row.get("id"))
        except Exception:
            continue
        name = str(row.get("name", "")).strip()
        if name and has_cjk(name):
            result[player_id] = name
    return result


async def translate_missing_player_names(db: Session, limit: int = 2000) -> dict:
    players = db.scalars(select(Player).order_by(Player.is_legend, Player.id)).all()
    candidates: list[Player] = []
    preset = 0
    for player in players:
        if has_cjk(player.name):
            continue
        english = player.english_name or player.name
        known = common_chinese_name(english)
        if known != english and has_cjk(known):
            player.name = known
            preset += 1
            continue
        candidates.append(player)
        if len(candidates) >= limit:
            break

    translated = 0
    failed = 0
    for index in range(0, len(candidates), 80):
        batch = candidates[index : index + 80]
        translated_names = await _translate_batch(batch)
        for player in batch:
            name = translated_names.get(player.id)
            if name:
                player.name = name
                translated += 1
            else:
                failed += 1
    db.commit()
    return {
        "source": "ARK name transliteration",
        "preset": preset,
        "translated": translated,
        "failed": failed,
        "checked": len(players),
        "updated_at": datetime.now(timezone.utc).isoformat(),
    }
