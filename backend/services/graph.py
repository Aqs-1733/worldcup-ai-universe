from __future__ import annotations

from functools import lru_cache
from typing import Any, TypedDict

from langgraph.graph import END, StateGraph
from sqlalchemy import select

from backend.core.database import SessionLocal
from backend.core.models import UserProfile
from backend.services.agents import AGENT_LABELS, AgentService


class FootballState(TypedDict, total=False):
    question: str
    session_id: str
    user_id: int | None
    route: str
    route_reason: str
    answer: str
    sources: list[dict[str, Any]]
    suggested_questions: list[str]
    model_mode: str
    metadata: dict[str, Any]


class FootballGraph:
    def __init__(self) -> None:
        self.agents = AgentService()
        builder = StateGraph(FootballState)
        builder.add_node("router", self.route)
        for name in AGENT_LABELS:
            builder.add_node(name, self._node(name))
        builder.set_entry_point("router")
        builder.add_conditional_edges(
            "router", lambda s: s["route"], {name: name for name in AGENT_LABELS}
        )
        for name in AGENT_LABELS:
            builder.add_edge(name, END)
        self.app = builder.compile()

    def route(self, state: FootballState) -> FootballState:
        q = state["question"].lower()
        rules = [
            ("vision", ["图片", "截图", "球衣识别", "国旗识别", "阵型识别", "视觉"]),
            (
                "generation",
                [
                    "生成",
                    "海报",
                    "朋友圈",
                    "文案",
                    "视频脚本",
                    "宣传图",
                    "prompt",
                    "绘图",
                    "画图",
                    "图片生成",
                    "生成图片",
                    "aigc",
                    "创作",
                ],
            ),
            ("news", ["新闻", "消息", "官宣", "真假", "辟谣", "最新动态", "报道"]),
            ("prediction", ["概率", "预测", "胜率", "夺冠", "比分", "谁会赢", "晋级"]),
            (
                "player",
                [
                    "球员",
                    "状态",
                    "表现",
                    "进球",
                    "助攻",
                    "姆巴佩",
                    "梅西",
                    "哈兰德",
                    "贝林厄姆",
                    "维尼修斯",
                    "亚马尔",
                    "凯恩",
                    "C罗",
                    "罗德里",
                ],
            ),
            (
                "team",
                [
                    "球队",
                    "国家队",
                    "阵容",
                    "战术",
                    "克制",
                    "法国",
                    "阿根廷",
                    "西班牙",
                    "英格兰",
                    "巴西",
                    "德国",
                    "葡萄牙",
                    "荷兰",
                    "日本",
                    "韩国",
                    "摩洛哥",
                ],
            ),
        ]
        route = "football"
        hits = []
        for candidate, words in rules:
            found = [word for word in words if word.lower() in q]
            if found:
                route = candidate
                hits = found
                break
        state["route"] = route
        state["route_reason"] = "命中关键词：" + "、".join(hits[:4]) if hits else "通用足球知识问题"
        return state

    def _node(self, name: str):
        async def run(state: FootballState) -> FootballState:
            with SessionLocal() as db:
                user = (
                    db.scalar(select(UserProfile).where(UserProfile.id == state.get("user_id")))
                    if state.get("user_id")
                    else None
                )
                result = await getattr(self.agents, name)(db, state["question"], user)
            state.update(result)
            state["metadata"] = {
                **state.get("metadata", {}),
                **result.get("metadata", {}),
                "route": name,
            }
            return state

        return run

    async def invoke(self, question: str, session_id: str, user_id: int | None = None) -> dict:
        state = await self.app.ainvoke(
            {"question": question, "session_id": session_id, "user_id": user_id}
        )
        return {
            "answer": state.get("answer", "").strip(),
            "agent": AGENT_LABELS[state["route"]],
            "route_reason": state.get("route_reason", ""),
            "sources": state.get("sources", []),
            "suggested_questions": state.get("suggested_questions", []),
            "model_mode": state.get("model_mode", "local"),
            "metadata": state.get("metadata", {}),
        }


@lru_cache(maxsize=1)
def get_football_graph() -> FootballGraph:
    return FootballGraph()
