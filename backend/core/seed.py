from __future__ import annotations

import json
from datetime import datetime

from sqlalchemy import delete, func, select
from sqlalchemy.orm import Session

from backend.core.config import ROOT_DIR
from backend.core.models import History, Match, News, Player, Standing, Team, UserProfile

DATA_DIR = ROOT_DIR / "data"


def load_json(filename: str):
    with (DATA_DIR / filename).open("r", encoding="utf-8") as file:
        return json.load(file)


def parse_datetime(value: str) -> datetime:
    return datetime.fromisoformat(value.replace("Z", "+00:00"))


def seed_teams(db: Session) -> dict[str, Team]:
    if db.scalar(select(func.count()).select_from(Team)) == 0:
        db.add_all([Team(**row) for row in load_json("teams.json")])
        db.commit()
    return {team.slug: team for team in db.scalars(select(Team)).all()}


def seed_players(db: Session, teams: dict[str, Team]) -> None:
    if db.scalar(select(func.count()).select_from(Player)) == 0:
        rows = load_json("players.json") + load_json("legends.json")
        for row in rows:
            payload = dict(row)
            team_slug = payload.pop("team_slug", None)
            payload["team_id"] = teams[team_slug].id if team_slug in teams else None
            payload.setdefault("number", None)
            payload.setdefault("age", None)
            payload.setdefault("avatar_url", "")
            payload.setdefault("is_legend", team_slug is None)
            db.add(Player(**payload))
        db.commit()

    remove_generated_roster_slots(db)


def remove_generated_roster_slots(db: Session) -> None:
    db.execute(delete(Player).where(Player.slug.like("%-squad-%")))
    db.execute(delete(Player).where(Player.name.like("%阵容席位%")))
    db.commit()


def seed_matches(db: Session) -> None:
    if db.scalar(select(func.count()).select_from(Match)):
        return
    for row in load_json("matches.json"):
        payload = dict(row)
        payload["kickoff"] = parse_datetime(payload["kickoff"])
        db.add(Match(**payload))
    db.commit()


def seed_history(db: Session) -> None:
    if db.scalar(select(func.count()).select_from(History)):
        return
    db.add_all([History(**row) for row in load_json("history.json")])
    db.commit()


def seed_news(db: Session) -> None:
    remove_local_news(db)


def remove_local_news(db: Session) -> None:
    db.execute(delete(News).where(News.source_url == ""))
    db.execute(delete(News).where(News.source.like("WorldCup AI%")))
    db.commit()


def recalculate_standings(db: Session) -> None:
    db.query(Standing).delete()
    groups: dict[tuple[str, str], dict] = {}
    matches = db.scalars(
        select(Match).where(Match.stage == "小组赛", Match.status == "finished")
    ).all()
    for match in matches:
        if match.home_score is None or match.away_score is None:
            continue
        for team, flag in [(match.home_team, match.home_flag), (match.away_team, match.away_flag)]:
            groups.setdefault(
                (match.group_name, team),
                {
                    "group_name": match.group_name,
                    "team": team,
                    "flag": flag,
                    "played": 0,
                    "won": 0,
                    "drawn": 0,
                    "lost": 0,
                    "goals_for": 0,
                    "goals_against": 0,
                    "goal_difference": 0,
                    "points": 0,
                },
            )
        home = groups[(match.group_name, match.home_team)]
        away = groups[(match.group_name, match.away_team)]
        home["played"] += 1
        away["played"] += 1
        home["goals_for"] += match.home_score
        home["goals_against"] += match.away_score
        away["goals_for"] += match.away_score
        away["goals_against"] += match.home_score
        if match.home_score > match.away_score:
            home["won"] += 1
            home["points"] += 3
            away["lost"] += 1
        elif match.home_score < match.away_score:
            away["won"] += 1
            away["points"] += 3
            home["lost"] += 1
        else:
            home["drawn"] += 1
            away["drawn"] += 1
            home["points"] += 1
            away["points"] += 1
    for row in groups.values():
        row["goal_difference"] = row["goals_for"] - row["goals_against"]
        db.add(Standing(**row))
    db.commit()


def seed_demo_user(db: Session) -> None:
    if db.scalar(select(UserProfile).where(UserProfile.username == "演示球迷")):
        return
    db.add(
        UserProfile(
            username="演示球迷",
            favorite_teams=[],
            favorite_players=[],
            dislike_teams=[],
            favorite_content_type="综合",
            fan_persona="开放型全能球迷：选择支持球队和球员后生成个人日报。",
        )
    )
    db.commit()


def seed_database(db: Session) -> None:
    teams = seed_teams(db)
    seed_players(db, teams)
    seed_matches(db)
    seed_history(db)
    seed_news(db)
    recalculate_standings(db)
    seed_demo_user(db)
