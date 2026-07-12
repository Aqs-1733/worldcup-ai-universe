from __future__ import annotations

import asyncio
import logging
from datetime import datetime, timedelta, timezone
from typing import Any

import httpx
from sqlalchemy import select
from sqlalchemy.orm import Session

from backend.core.config import get_settings
from backend.core.database import SessionLocal
from backend.core.models import Team

logger = logging.getLogger(__name__)

ESPN_SCOREBOARD_URL = "https://site.api.espn.com/apis/site/v2/sports/soccer/fifa.world/scoreboard"
ESPN_STANDINGS_URL = "https://site.web.api.espn.com/apis/v2/sports/soccer/fifa.world/standings"

STAGE_MAP = {
    "round-of-32": "三十二强",
    "round-of-16": "十六强",
    "quarterfinals": "四分之一决赛",
    "semifinals": "半决赛",
    "3rd-place-match": "三四名决赛",
    "final": "决赛",
}

PLACEHOLDER_MAP = {
    "Quarterfinal 1 Winner": "四分之一决赛1胜者",
    "Quarterfinal 2 Winner": "四分之一决赛2胜者",
    "Quarterfinal 3 Winner": "四分之一决赛3胜者",
    "Quarterfinal 4 Winner": "四分之一决赛4胜者",
    "Semifinal 1 Winner": "半决赛1胜者",
    "Semifinal 2 Winner": "半决赛2胜者",
    "Semifinal 1 Loser": "半决赛1负者",
    "Semifinal 2 Loser": "半决赛2负者",
}

_cache: dict[str, Any] = {"fetched_at": None, "rows": [], "error": ""}
_standings_cache: dict[str, Any] = {"fetched_at": None, "rows": [], "error": ""}
_lock = asyncio.Lock()
_standings_lock = asyncio.Lock()


def _match_window() -> str:
    today = datetime.now(timezone.utc).date()
    start = min(today - timedelta(days=14), datetime(2026, 7, 1, tzinfo=timezone.utc).date())
    end = max(today + timedelta(days=14), datetime(2026, 7, 20, tzinfo=timezone.utc).date())
    return f"{start:%Y%m%d}-{end:%Y%m%d}"


def _team_maps(db: Session) -> tuple[dict[str, Team], dict[str, Team]]:
    teams = db.scalars(select(Team)).all()
    by_code = {team.country_code.upper(): team for team in teams}
    by_english = {team.english_name.lower(): team for team in teams}
    # ESPN uses a few display names that differ from FIFA/team seed names.
    aliases = {
        "united states": "usa",
        "bosnia-herzegovina": "bih",
        "bosnia and herzegovina": "bih",
        "cabo verde": "cpv",
        "cape verde": "cpv",
        "congo dr": "cod",
        "côte d'ivoire": "civ",
        "ivory coast": "civ",
        "saudi arabia": "ksa",
    }
    for name, code in aliases.items():
        if code.upper() in by_code:
            by_english[name] = by_code[code.upper()]
    return by_code, by_english


def _team_label(raw: dict, by_code: dict[str, Team], by_english: dict[str, Team]) -> tuple[str, str]:
    team = raw.get("team") or {}
    code = str(team.get("abbreviation") or "").upper()
    display = str(team.get("displayName") or team.get("name") or "")
    if code in by_code:
        item = by_code[code]
        return item.name, item.flag
    if display.lower() in by_english:
        item = by_english[display.lower()]
        return item.name, item.flag
    return PLACEHOLDER_MAP.get(display, display or code or "待定"), ""


def _status(competition: dict) -> tuple[str, int | None]:
    status = competition.get("status") or {}
    kind = status.get("type") or {}
    state = kind.get("state")
    completed = bool(kind.get("completed"))
    if completed or state == "post":
        return "finished", 90
    if state == "in":
        return "live", int(float(status.get("clock") or 0) // 60)
    return "scheduled", None


async def _get_json(url: str, params: dict[str, Any]) -> dict[str, Any]:
    headers = {"User-Agent": "WorldCupAIUniverse/1.0 (+https://localhost)"}
    timeout = httpx.Timeout(9.0, connect=3.0, read=9.0)
    last_error: Exception | None = None
    for trust_env in (False, True):
        try:
            async with httpx.AsyncClient(
                timeout=timeout,
                follow_redirects=True,
                trust_env=trust_env,
                headers=headers,
            ) as client:
                response = await asyncio.wait_for(client.get(url, params=params), timeout=10.0)
                response.raise_for_status()
                return response.json()
        except Exception as exc:
            last_error = exc
    assert last_error is not None
    raise last_error


def _score(value: Any, status: str) -> int | None:
    if status == "scheduled":
        return None
    try:
        return int(value)
    except Exception:
        return None


def _stats(event: dict, competition: dict) -> dict[str, Any]:
    status = competition.get("status") or {}
    kind = status.get("type") or {}
    notes = competition.get("notes") or []
    return {
        "data_source": "espn_scoreboard",
        "source_url": ESPN_SCOREBOARD_URL,
        "source_note": competition.get("altGameNote") or event.get("name") or "",
        "status_detail": kind.get("description") or kind.get("detail") or "",
        "status_short": kind.get("shortDetail") or "",
        "last_reviewed": datetime.now(timezone.utc).isoformat(),
        "notes": notes,
    }


def _parse_event(event: dict, db: Session) -> dict[str, Any] | None:
    competitions = event.get("competitions") or []
    if not competitions:
        return None
    competition = competitions[0]
    competitors = competition.get("competitors") or []
    if len(competitors) < 2:
        return None
    by_code, by_english = _team_maps(db)
    home = next((item for item in competitors if item.get("homeAway") == "home"), competitors[0])
    away = next((item for item in competitors if item.get("homeAway") == "away"), competitors[1])
    home_name, home_flag = _team_label(home, by_code, by_english)
    away_name, away_flag = _team_label(away, by_code, by_english)
    status, minute = _status(competition)
    season = event.get("season") or {}
    stage = STAGE_MAP.get(str(season.get("slug") or ""), competition.get("altGameNote") or "世界杯")
    venue = competition.get("venue") or {}
    address = venue.get("address") or {}
    venue_text = venue.get("fullName") or ""
    if address.get("city"):
        venue_text = f"{venue_text} · {address['city']}" if venue_text else address["city"]
    return {
        "id": int(event.get("id") or competition.get("id")),
        "stage": stage,
        "group_name": "",
        "home_team": home_name,
        "away_team": away_name,
        "home_flag": home_flag,
        "away_flag": away_flag,
        "kickoff": datetime.fromisoformat(str(event["date"]).replace("Z", "+00:00")),
        "venue": venue_text,
        "status": status,
        "home_score": _score(home.get("score"), status),
        "away_score": _score(away.get("score"), status),
        "home_penalties": home.get("shootoutScore"),
        "away_penalties": away.get("shootoutScore"),
        "minute": minute,
        "stats": _stats(event, competition),
    }


async def _fetch_espn_rows(db: Session) -> list[dict[str, Any]]:
    params = {"limit": 200, "dates": _match_window()}
    data = await _get_json(ESPN_SCOREBOARD_URL, params)
    rows = [_parse_event(event, db) for event in data.get("events", [])]
    return sorted([row for row in rows if row], key=lambda row: row["kickoff"])


def _group_label(name: str) -> str:
    if name.lower().startswith("group "):
        return f"{name.split()[-1]}组"
    return name


def _stat(stats: list[dict[str, Any]], kind: str) -> int:
    item = next((row for row in stats if row.get("type") == kind or row.get("name") == kind), None)
    if not item:
        return 0
    try:
        return int(float(item.get("value") or 0))
    except Exception:
        return 0


def _parse_standings(data: dict[str, Any], db: Session) -> list[dict[str, Any]]:
    by_code, by_english = _team_maps(db)
    rows: list[dict[str, Any]] = []
    for group_index, group in enumerate(data.get("children") or []):
        group_name = _group_label(str(group.get("name") or group.get("abbreviation") or ""))
        entries = ((group.get("standings") or {}).get("entries")) or []
        for index, entry in enumerate(entries):
            team_info = entry.get("team") or {}
            name, flag = _team_label({"team": team_info}, by_code, by_english)
            stats = entry.get("stats") or []
            rows.append(
                {
                    "id": group_index * 100 + index + 1,
                    "group_name": group_name,
                    "team": name,
                    "flag": flag,
                    "played": _stat(stats, "gamesplayed"),
                    "won": _stat(stats, "wins"),
                    "drawn": _stat(stats, "ties"),
                    "lost": _stat(stats, "losses"),
                    "goals_for": _stat(stats, "pointsfor"),
                    "goals_against": _stat(stats, "pointsagainst"),
                    "goal_difference": _stat(stats, "pointdifferential"),
                    "points": _stat(stats, "points"),
                }
            )
    return rows


async def live_matches(db: Session, *, force: bool = False) -> list[dict[str, Any]]:
    settings = get_settings()
    ttl = max(15, getattr(settings, "worldcup_live_refresh_seconds", 45))
    fetched_at = _cache.get("fetched_at")
    if not force and _cache.get("rows") and fetched_at:
        return list(_cache["rows"])
    if not force and fetched_at and (datetime.now(timezone.utc) - fetched_at).total_seconds() < ttl:
        return list(_cache["rows"])
    async with _lock:
        fetched_at = _cache.get("fetched_at")
        if not force and fetched_at and (datetime.now(timezone.utc) - fetched_at).total_seconds() < ttl:
            return list(_cache["rows"])
        try:
            rows = await _fetch_espn_rows(db)
            _cache.update({"fetched_at": datetime.now(timezone.utc), "rows": rows, "error": ""})
            return list(rows)
        except Exception as exc:
            logger.warning("ESPN scoreboard refresh failed: %s", exc)
            _cache["error"] = str(exc)[:240]
            return list(_cache.get("rows") or [])


def live_matches_needs_refresh() -> bool:
    settings = get_settings()
    ttl = max(15, getattr(settings, "worldcup_live_refresh_seconds", 45))
    fetched_at = _cache.get("fetched_at")
    return not fetched_at or (datetime.now(timezone.utc) - fetched_at).total_seconds() >= ttl


async def refresh_live_matches_cache() -> None:
    with SessionLocal() as session:
        await live_matches(session, force=True)


async def live_standings(db: Session, *, force: bool = False) -> list[dict[str, Any]]:
    settings = get_settings()
    ttl = max(30, getattr(settings, "worldcup_live_refresh_seconds", 45))
    fetched_at = _standings_cache.get("fetched_at")
    if not force and _standings_cache.get("rows") and fetched_at:
        return list(_standings_cache["rows"])
    if not force and fetched_at and (datetime.now(timezone.utc) - fetched_at).total_seconds() < ttl:
        return list(_standings_cache["rows"])
    async with _standings_lock:
        fetched_at = _standings_cache.get("fetched_at")
        if not force and fetched_at and (datetime.now(timezone.utc) - fetched_at).total_seconds() < ttl:
            return list(_standings_cache["rows"])
        try:
            data = await _get_json(ESPN_STANDINGS_URL, {"season": 2026})
            rows = _parse_standings(data, db)
            _standings_cache.update({"fetched_at": datetime.now(timezone.utc), "rows": rows, "error": ""})
            return list(rows)
        except Exception as exc:
            logger.warning("ESPN standings refresh failed: %s", exc)
            _standings_cache["error"] = str(exc)[:240]
            return list(_standings_cache.get("rows") or [])


def live_standings_needs_refresh() -> bool:
    settings = get_settings()
    ttl = max(30, getattr(settings, "worldcup_live_refresh_seconds", 45))
    fetched_at = _standings_cache.get("fetched_at")
    return not fetched_at or (datetime.now(timezone.utc) - fetched_at).total_seconds() >= ttl


async def refresh_live_standings_cache() -> None:
    with SessionLocal() as session:
        await live_standings(session, force=True)


async def live_bracket(db: Session, *, force: bool = False) -> list[dict[str, Any]]:
    rows = await live_matches(db, force=force)
    knockout_order = {
        "三十二强": 0,
        "十六强": 1,
        "四分之一决赛": 2,
        "半决赛": 3,
        "三四名决赛": 4,
        "决赛": 5,
    }
    return [row for row in rows if row["stage"] in knockout_order]


async def team_live_forms(db: Session) -> dict[str, list[str]]:
    rows = await live_matches(db)
    forms: dict[str, list[str]] = {}
    for row in rows:
        if row["status"] != "finished":
            continue
        home_score = row.get("home_score")
        away_score = row.get("away_score")
        if home_score is None or away_score is None:
            continue
        home_pens = row.get("home_penalties")
        away_pens = row.get("away_penalties")
        if home_score == away_score and home_pens is not None and away_pens is not None:
            home_result = "W" if home_pens > away_pens else "L"
            away_result = "W" if away_pens > home_pens else "L"
        elif home_score > away_score:
            home_result, away_result = "W", "L"
        elif away_score > home_score:
            home_result, away_result = "L", "W"
        else:
            home_result = away_result = "D"
        forms.setdefault(row["home_team"], []).append(home_result)
        forms.setdefault(row["away_team"], []).append(away_result)
    return {team: values[-5:] for team, values in forms.items()}
