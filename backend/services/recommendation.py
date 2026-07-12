from __future__ import annotations

from datetime import datetime, timedelta, timezone

from sqlalchemy import desc, or_, select
from sqlalchemy.orm import Session

from backend.core.models import Match, News, Player, Recommendation, Team, UserProfile
from backend.services.llm import get_llm_service


def build_persona(profile: UserProfile) -> str:
    teams = " + ".join(profile.favorite_teams) or "开放型"
    players = "、".join(profile.favorite_players[:3]) or "多球星"
    dislikes = (
        "，重点关注对手 " + "、".join(profile.dislike_teams[:2]) if profile.dislike_teams else ""
    )
    style_map = {
        "战术分析": "战术研究型",
        "球星动态": "球星追踪型",
        "球队动态": "球队追踪型",
        "比赛内容": "比赛关注型",
        "球迷内容": "社区互动型",
        "娱乐内容": "场外故事型",
        "二创内容": "内容创作型",
        "比赛预测": "数据预测型",
        "短视频": "内容创作型",
        "综合": "全能型",
    }
    return f"{teams} {style_map.get(profile.favorite_content_type, '全能型')}球迷：关注{players}{dislikes}，偏好{profile.favorite_content_type}内容。"


class RecommendationService:
    async def generate_daily(
        self, db: Session, user: UserProfile, force: bool = False
    ) -> list[Recommendation]:
        today = datetime.now(timezone.utc).date()
        existing = db.scalars(
            select(Recommendation)
            .where(Recommendation.user_id == user.id)
            .order_by(desc(Recommendation.created_at))
            .limit(20)
        ).all()
        if not force and existing and existing[0].created_at.date() == today:
            return existing
        if force:
            db.query(Recommendation).filter(Recommendation.user_id == user.id).delete()

        cards: list[Recommendation] = []
        for team_name in user.favorite_teams[:4]:
            team = db.scalar(select(Team).where(Team.name == team_name))
            if team:
                form_text = "".join(team.form)
                cards.append(
                    Recommendation(
                        user_id=user.id,
                        title=f"{team.flag} {team.name} 今日战术观察",
                        content=f"近期状态序列 {form_text}。核心优势：{'、'.join(team.strengths[:2])}；主要风险：{'、'.join(team.weaknesses[:1])}。平台展示夺冠概率为 {team.win_probability}%。",
                        category="支持球队",
                        priority=95,
                        related_entity=team.name,
                        expires_at=datetime.now(timezone.utc) + timedelta(days=1),
                    )
                )
        for player_name in user.favorite_players[:4]:
            player = db.scalar(select(Player).where(Player.name == player_name))
            if player:
                cards.append(
                    Recommendation(
                        user_id=user.id,
                        title=f"{player.flag} {player.name} 官方名单资料",
                        content=f"俱乐部：{player.club or '未公开'}。国家队出场 {player.world_cup_appearances}，进球 {player.world_cup_goals}。{player.career}",
                        category="支持球员",
                        priority=92,
                        related_entity=player.name,
                        expires_at=datetime.now(timezone.utc) + timedelta(days=1),
                    )
                )
        names = user.favorite_teams + user.dislike_teams
        upcoming = db.scalars(
            select(Match)
            .where(
                Match.status == "scheduled",
                or_(Match.home_team.in_(names), Match.away_team.in_(names))
                if names
                else Match.id < 0,
            )
            .order_by(Match.kickoff)
            .limit(5)
        ).all()
        for match in upcoming:
            cards.append(
                Recommendation(
                    user_id=user.id,
                    title=f"比赛提醒：{match.home_flag} {match.home_team} vs {match.away_flag} {match.away_team}",
                    content=f"{match.stage}将在 {match.kickoff.astimezone(timezone.utc).strftime('%m月%d日 %H:%M UTC')} 于{match.venue}进行。建议赛前关注双方阵容和中场对位。",
                    category="比赛提醒",
                    priority=88,
                    related_entity=f"{match.home_team} vs {match.away_team}",
                    expires_at=match.kickoff + timedelta(hours=4),
                )
            )
        latest_news = db.scalars(select(News).order_by(desc(News.published_at)).limit(30)).all()
        blocked = set(user.dislike_teams)
        preferred = set(user.favorite_teams)
        added_news = 0
        for item in latest_news:
            related = set(item.related_teams or [])
            if blocked and related & blocked:
                continue
            category_bonus = 8 if user.favorite_content_type and user.favorite_content_type in item.category else 0
            team_bonus = 10 if preferred and related & preferred else 0
            cards.append(
                Recommendation(
                    user_id=user.id,
                    title=item.title,
                    content=f"来源：{item.source}｜可信度 {item.credibility_score}%（{item.credibility_label}）。{item.summary[:180]}",
                    category="新闻",
                    priority=65 + category_bonus + team_bonus,
                    related_entity=item.source,
                    expires_at=datetime.now(timezone.utc) + timedelta(days=2),
                )
            )
            added_news += 1
            if added_news >= 10:
                break
        if not cards:
            cards.append(
                Recommendation(
                    user_id=user.id,
                    title="完善你的球迷画像",
                    content="选择支持球队、喜爱球员和内容偏好后生成专属世界杯日报。",
                    category="引导",
                    priority=100,
                    related_entity="画像",
                )
            )
        db.add_all(cards)
        user.fan_persona = build_persona(user)
        db.commit()
        for card in cards:
            db.refresh(card)

        llm = get_llm_service()
        if llm.enabled and cards:
            digest = await llm.complete(
                "你是世界杯球迷日报主编。根据卡片生成120字以内开场白，语气热情，必须标明预测和展示数据不等于官方结果。",
                f"球迷人格：{user.fan_persona}\n卡片："
                + "\n".join(f"- {x.title}: {x.content}" for x in cards[:8]),
            )
            if digest:
                intro = Recommendation(
                    user_id=user.id,
                    title="AI主编今日导语",
                    content=digest,
                    category="日报导语",
                    priority=110,
                    related_entity="AI日报",
                    expires_at=datetime.now(timezone.utc) + timedelta(days=1),
                )
                db.add(intro)
                db.commit()
                db.refresh(intro)
                cards.insert(0, intro)
        return cards
