from __future__ import annotations

import json
import re

from rapidfuzz import fuzz, process
from sqlalchemy import desc, or_, select
from sqlalchemy.orm import Session

from backend.core.models import Match, News, Player, Team, UserProfile
from backend.services.generation import get_generation_service
from backend.services.llm import get_llm_service
from backend.services.rag import get_rag_service
from backend.services.text_format import PLAIN_TEXT_SYSTEM_INSTRUCTION, clean_ai_text

AGENT_LABELS = {
    "football": "Football Agent",
    "team": "Team Agent",
    "player": "Player Agent",
    "news": "News Agent",
    "prediction": "Prediction Agent",
    "vision": "Vision Agent",
    "generation": "Generation Agent",
}


def _find_team(db: Session, query: str) -> Team | None:
    teams = db.scalars(select(Team)).all()
    for team in teams:
        if team.name in query or team.english_name.lower() in query.lower():
            return team
    match = process.extractOne(
        query, [t.name for t in teams], scorer=fuzz.partial_ratio, score_cutoff=55
    )
    return next((t for t in teams if t.name == match[0]), None) if match else None


def _find_teams(db: Session, query: str) -> list[Team]:
    teams = db.scalars(select(Team)).all()
    found = []
    for team in teams:
        if team.name in query or team.english_name.lower() in query.lower():
            found.append(team)
    return found[:4]


def _find_player(db: Session, query: str) -> Player | None:
    players = db.scalars(select(Player)).all()
    aliases = {
        "姆巴佩": "基利安·姆巴佩",
        "梅西": "利昂内尔·梅西",
        "哈兰德": "埃尔林·哈兰德",
        "贝林厄姆": "裘德·贝林厄姆",
        "维尼修斯": "维尼修斯·儒尼奥尔",
        "C罗": "克里斯蒂亚诺·罗纳尔多",
        "罗纳尔多": "罗纳尔多",
    }
    for alias, name in aliases.items():
        if alias in query:
            p = next((x for x in players if x.name == name), None)
            if p:
                return p
    for p in players:
        if p.name in query or p.english_name.lower() in query.lower():
            return p
    match = process.extractOne(
        query, [p.name for p in players], scorer=fuzz.partial_ratio, score_cutoff=55
    )
    return next((p for p in players if p.name == match[0]), None) if match else None


def _form_score(form: list[str]) -> float:
    value = {"W": 1.0, "D": 0.45, "L": 0.0}
    return sum(value.get(x, 0.4) for x in form[-5:]) / max(1, len(form[-5:]))


async def _explain_prediction_with_ark(question: str, local_answer: str, metadata: dict) -> tuple[str, dict]:
    llm = get_llm_service()
    if "champion_probability" in metadata:
        prompt = (
            f"{metadata.get('team_en', metadata.get('team'))} champion probability is "
            f"{metadata['champion_probability']}%. Recent form: "
            f"{'-'.join(metadata.get('recent_form', []))}. Strengths: attacking talent, "
            "squad depth, fast transitions. Risks: midfield control volatility, "
            "breaking low blocks. Explain in concise Chinese and keep the probability unchanged."
        )
    elif "home_win" in metadata:
        prompt = (
            f"{metadata.get('home_en', metadata.get('home'))} vs "
            f"{metadata.get('away_en', metadata.get('away'))}. Local probabilities: "
            f"home_win={metadata['home_win']}, draw={metadata['draw']}, "
            f"away_win={metadata['away_win']}. Explain in concise Chinese and keep all numbers."
        )
    else:
        prompt = (
            f"Local champion probability ranking: {metadata.get('ranking', [])}. "
            "Explain in concise Chinese that these are display-model values, not official odds."
        )
    result = await llm.complete_with_result(
        f"You explain football probabilities. Do not change numbers. {PLAIN_TEXT_SYSTEM_INSTRUCTION}",
        prompt,
        temperature=0.25,
        timeout_s=20,
        max_tokens=180,
    )
    status = {
        "fallback_used": result.fallback_used,
        "fallback_reason": result.fallback_reason,
    }
    content = clean_ai_text(result.content)
    if content:
        return f"{local_answer}\n\nARK解读：\n{content}", status | {"llm_role": "explanation_only"}
    return local_answer, status | {"llm_role": "not_used"}


class AgentService:
    async def football(self, db: Session, question: str, user: UserProfile | None = None) -> dict:
        sources = get_rag_service().search(question, 5)
        context = "\n".join(x["content"] for x in sources)
        answer = "根据世界杯知识库：\n" + "\n".join(f"• {x['content']}" for x in sources[:3])
        llm = get_llm_service()
        enhanced = await llm.complete(
            f"你是严谨的足球百科与战术分析师。优先使用给定知识库，区分事实、分析和预测；不要编造实时数据。{PLAIN_TEXT_SYSTEM_INSTRUCTION}",
            f"问题：{question}\n知识库：\n{context}\n球迷偏好：{user.fan_persona if user else '未知'}",
        )
        enhanced_answer = clean_ai_text(enhanced)
        if enhanced_answer:
            answer = enhanced_answer
        return {
            "answer": answer,
            "sources": sources,
            "suggested_questions": [
                "越位规则怎么判断？",
                "4-3-3和4-2-3-1有什么区别？",
                "世界杯历史上谁夺冠最多？",
            ],
            "model_mode": "ark" if enhanced_answer else "local",
        }

    async def team(self, db: Session, question: str, user: UserProfile | None = None) -> dict:
        team = _find_team(db, question)
        if not team:
            return await self.football(db, question, user)
        players = db.scalars(
            select(Player).where(Player.team_id == team.id).order_by(desc(Player.rating)).limit(6)
        ).all()
        recent = db.scalars(
            select(Match)
            .where(or_(Match.home_team == team.name, Match.away_team == team.name))
            .order_by(desc(Match.kickoff))
            .limit(5)
        ).all()
        local = (
            f"{team.flag} {team.name}｜世界排名展示值 #{team.world_rank}\n\n"
            f"战术画像：{team.style}\n优势：{'、'.join(team.strengths)}。\n弱点：{'、'.join(team.weaknesses)}。\n"
            f"近期状态：{'-'.join(team.form)}；平台模型夺冠概率：{team.win_probability}%。\n"
            f"重点球员：{'、'.join(p.name for p in players) or '阵容待补充'}。\n"
            f"判断：{team.name}需要把{team.strengths[0]}转化为稳定机会，同时控制{team.weaknesses[0]}。"
        )
        context = {
            "team": team.name,
            "style": team.style,
            "strengths": team.strengths,
            "weaknesses": team.weaknesses,
            "form": team.form,
            "probability": team.win_probability,
            "players": [p.name for p in players],
            "matches": [
                f"{m.home_team} {m.home_score}-{m.away_score} {m.away_team}" for m in recent
            ],
        }
        enhanced = await get_llm_service().complete(
            f"你是国家队战术分析师。使用数据完成结构化分析，概率必须说明是平台模型展示值，不得伪装成官方赔率。{PLAIN_TEXT_SYSTEM_INSTRUCTION}",
            f"用户问题：{question}\n数据：{json.dumps(context, ensure_ascii=False)}",
        )
        enhanced_answer = clean_ai_text(enhanced)
        answer = enhanced_answer or local
        return {
            "answer": answer,
            "sources": [
                {
                    "content": team.history,
                    "metadata": {"type": "team", "name": team.name},
                    "score": 1.0,
                }
            ],
            "suggested_questions": [
                f"{team.name}最强首发怎么排？",
                f"如何限制{team.name}？",
                f"{team.name}下一场对位分析",
            ],
            "model_mode": "ark" if enhanced_answer else "local",
            "metadata": {"team_slug": team.slug},
        }

    async def player(self, db: Session, question: str, user: UserProfile | None = None) -> dict:
        p = _find_player(db, question)
        if not p:
            return await self.football(db, question, user)
        local = (
            f"{p.flag} {p.name}｜{p.position}｜{p.club or '俱乐部未公开'}\n\n"
            f"国家队官方名单数据：{p.world_cup_appearances}次出场、{p.world_cup_goals}球。\n"
            f"来源说明：{p.career or '暂无来源说明'}\n"
            "未在官方来源中出现的状态评分、助攻和技术标签不做本地补写。"
        )
        data = {
            "name": p.name,
            "country": p.country,
            "position": p.position,
            "club": p.club,
            "caps": p.world_cup_appearances,
            "goals": p.world_cup_goals,
            "source": p.career,
            "impact": p.impact,
        }
        enhanced = await get_llm_service().complete(
            f"你是球员资料分析师。只基于提供的官方名单字段回答；缺失的状态、伤病、助攻和技术标签必须说明暂无来源，不得补写。{PLAIN_TEXT_SYSTEM_INSTRUCTION}",
            f"问题：{question}\n球员数据：{json.dumps(data, ensure_ascii=False)}",
        )
        enhanced_answer = clean_ai_text(enhanced)
        answer = enhanced_answer or local
        return {
            "answer": answer,
            "sources": [
                {"content": p.career, "metadata": {"type": "player", "name": p.name}, "score": 1.0}
            ],
            "suggested_questions": [
                f"{p.name}最适合什么战术？",
                f"谁能限制{p.name}？",
                f"{p.name}世界杯影响力如何？",
            ],
            "model_mode": "ark" if enhanced_answer else "local",
            "metadata": {"player_slug": p.slug},
        }

    async def news(self, db: Session, question: str, user: UserProfile | None = None) -> dict:
        terms = [
            x
            for x in re.findall(r"[\u4e00-\u9fffA-Za-z0-9]+", question)
            if len(x) >= 2 and x not in {"新闻", "最新", "消息", "真假", "情况"}
        ]
        stmt = select(News).order_by(desc(News.published_at)).limit(8)
        if terms:
            stmt = (
                select(News)
                .where(or_(*[News.title.ilike(f"%{term}%") for term in terms[:5]]))
                .order_by(desc(News.published_at))
                .limit(8)
            )
        items = db.scalars(stmt).all()
        if not items:
            items = db.scalars(select(News).order_by(desc(News.published_at)).limit(8)).all()
        lines = [
            f"• {n.title}\n  {n.source}｜可信度 {n.credibility_score}% {n.credibility_label}｜{n.published_at.strftime('%m-%d %H:%M')}"
            for n in items
        ]
        answer = "新闻雷达已按来源与可信度整理：\n\n" + (
            "\n".join(lines) if lines else "暂无已采集新闻，可在新闻中心点击联网刷新。"
        )
        return {
            "answer": answer,
            "sources": [
                {
                    "content": n.summary,
                    "metadata": {"type": "news", "name": n.title, "url": n.source_url},
                    "score": n.credibility_score / 100,
                }
                for n in items
            ],
            "suggested_questions": ["分析这条新闻是否可信", "某支球队最新消息", "刷新联网新闻"],
            "model_mode": "local",
        }

    async def prediction(self, db: Session, question: str, user: UserProfile | None = None) -> dict:
        teams = _find_teams(db, question)
        if len(teams) == 1:
            t = teams[0]
            score = t.win_probability
            answer = (
                f"{t.flag} {t.name}夺冠概率（平台展示模型）：{score:.1f}%\n\n"
                f"支撑因素：{'、'.join(t.strengths[:3])}。\n风险因素：{'、'.join(t.weaknesses[:2])}。\n"
                f"近期状态：{'-'.join(t.form)}。该数字来自初始化实力权重与状态修正，用于产品演示，不是官方赔率，也不能保证赛果。"
            )
            meta = {
                "team": t.name,
                "team_en": t.english_name,
                "champion_probability": score,
                "simulation": "seed-strength-model",
                "strengths": t.strengths[:3],
                "risks": t.weaknesses[:2],
                "recent_form": t.form,
            }
        elif len(teams) >= 2:
            a, b = teams[:2]
            a_power = max(0.01, a.win_probability / 20) * (0.65 + 0.7 * _form_score(a.form))
            b_power = max(0.01, b.win_probability / 20) * (0.65 + 0.7 * _form_score(b.form))
            draw = 0.24
            remaining = 1 - draw
            pa = remaining * a_power / (a_power + b_power)
            pb = remaining - pa
            answer = (
                f"{a.flag}{a.name} vs {b.flag}{b.name}（平台赛前模型）\n\n"
                f"{a.name}胜 {pa * 100:.1f}%｜平局 {draw * 100:.1f}%｜{b.name}胜 {pb * 100:.1f}%\n"
                f"关键对位：{a.strengths[0]} 对 {b.weaknesses[0]}；{b.strengths[0]} 对 {a.weaknesses[0]}。\n"
                "模型综合球队种子权重和近5场状态，仅供分析展示。"
            )
            meta = {
                "home": a.name,
                "home_en": a.english_name,
                "away": b.name,
                "away_en": b.english_name,
                "home_win": round(pa, 3),
                "draw": draw,
                "away_win": round(pb, 3),
                "home_strengths": a.strengths[:2],
                "away_strengths": b.strengths[:2],
                "home_risks": a.weaknesses[:2],
                "away_risks": b.weaknesses[:2],
            }
        else:
            top = db.scalars(select(Team).order_by(desc(Team.win_probability)).limit(6)).all()
            answer = (
                "夺冠概率榜（平台展示模型）：\n"
                + "\n".join(
                    f"{i + 1}. {t.flag}{t.name} {t.win_probability}%" for i, t in enumerate(top)
                )
                + "\n\n请选择球队进行详细预测。"
            )
            meta = {"ranking": [{"team": t.name, "value": t.win_probability} for t in top]}
        answer, llm_meta = await _explain_prediction_with_ark(question, answer, meta)
        meta = {**meta, **llm_meta}
        return {
            "answer": answer,
            "sources": [],
            "suggested_questions": ["某场比赛胜率", "某支球队夺冠概率", "生成八强概率榜"],
            "model_mode": "local" if meta["fallback_used"] else "ark",
            "metadata": meta,
        }

    async def vision(self, db: Session, question: str, user: UserProfile | None = None) -> dict:
        return {
            "answer": "视觉分析需要图片输入。请进入“视觉分析”页面上传球衣照片、国旗图片或比赛截图；系统会运行 OpenCV 分析，并在已配置时叠加 YOLO、CLIP 与 ARK 视觉模型结果。",
            "sources": [],
            "suggested_questions": [
                "如何拍摄更容易识别球衣？",
                "阵型截图需要包含什么？",
                "进入视觉分析页面",
            ],
            "model_mode": "local",
            "metadata": {"navigate": "/vision"},
        }

    async def generation(self, db: Session, question: str, user: UserProfile | None = None) -> dict:
        team = _find_team(db, question)
        result = await get_generation_service().generate(
            db, question, team.name if team else "", generate_image=False
        )
        answer = f"已完成创作。\n\n朋友圈文案：\n{result['social_copy']}\n\n海报 Prompt：\n{result['poster_prompt']}\n\n30秒脚本：\n{result['video_script']}"
        return {
            "answer": answer,
            "sources": [],
            "suggested_questions": ["改成克制高级的文案", "生成赛后庆祝脚本", "做一张横版宣传图"],
            "model_mode": result["model_mode"],
            "metadata": {
                "poster_url": result["poster_url"],
                "slogans": result["slogans"],
                "image_source": result.get("image_source", ""),
                "image_error": result.get("image_error", ""),
            },
        }
