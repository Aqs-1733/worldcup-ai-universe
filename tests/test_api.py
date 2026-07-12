from __future__ import annotations

import gc
import io
import os
import time
from pathlib import Path

os.environ["ARK_API_KEY"] = ""
os.environ["APP_ENV"] = "test"
os.environ["DATABASE_URL"] = "sqlite:///./storage/test_worldcup_ai.db"

import pytest
from fastapi.testclient import TestClient
from PIL import Image
from sqlalchemy.orm import close_all_sessions

from backend.core.database import engine
from backend.services.graph import get_football_graph
from backend.services.llm import LLMResult
from backend.main import app

TEST_DB = Path("storage/test_worldcup_ai.db")
ARTIFACT_DIRS = [Path("storage/uploads"), Path("storage/analysis"), Path("storage/generated")]


def _snapshot_artifacts() -> set[Path]:
    files: set[Path] = set()
    for directory in ARTIFACT_DIRS:
        if directory.exists():
            files.update(path.resolve() for path in directory.iterdir() if path.is_file())
    return files


def _cleanup_new_artifacts(before: set[Path]) -> None:
    for directory in ARTIFACT_DIRS:
        if not directory.exists():
            continue
        for path in directory.iterdir():
            if path.is_file() and path.name != ".gitkeep" and path.resolve() not in before:
                path.unlink(missing_ok=True)


def _remove_test_db() -> None:
    """释放 SQLite 连接，并兼容 Windows 文件句柄延迟关闭。"""
    close_all_sessions()
    engine.dispose()
    gc.collect()

    for attempt in range(10):
        try:
            TEST_DB.unlink(missing_ok=True)
            return
        except PermissionError:
            if attempt == 9:
                raise
            time.sleep(0.2)


@pytest.fixture(scope="module")
def client():
    artifacts_before = _snapshot_artifacts()
    _remove_test_db()

    try:
        with TestClient(app) as test_client:
            yield test_client
    finally:
        _remove_test_db()
        _cleanup_new_artifacts(artifacts_before)


@pytest.fixture(scope="module")
def profile(client: TestClient) -> dict:
    register = client.post(
        "/api/users/register",
        json={"username": "测试球迷", "password": "test-password"},
    )
    assert register.status_code == 201
    response = client.post(
        "/api/users",
        json={
            "username": "测试球迷",
            "favorite_teams": ["法国", "日本"],
            "favorite_players": ["基利安·姆巴佩"],
            "dislike_teams": ["巴西"],
            "favorite_content_type": "战术分析",
        },
    )
    assert response.status_code == 200
    return response.json()


def test_health_and_seed_data(client: TestClient):
    response = client.get("/api/health")
    assert response.status_code == 200

    counts = response.json()["counts"]

    assert counts["teams"] >= 48
    assert counts["players"] >= 69
    assert counts["matches"] == 104


def test_team_query(client: TestClient):
    response = client.get("/api/teams", params={"q": "法国"})

    assert response.status_code == 200
    assert response.json()[0]["name"] == "法国"


def test_player_query(client: TestClient):
    response = client.get("/api/players", params={"q": "姆巴佩"})

    assert response.status_code == 200
    assert any("姆巴佩" in item["name"] for item in response.json())


def test_user_profile_and_recommendation(
    client: TestClient,
    profile: dict,
):
    assert "法国" in profile["fan_persona"]

    response = client.get(
        f"/api/users/{profile['id']}/daily",
        params={"force": True},
    )

    assert response.status_code == 200
    assert len(response.json()) >= 4


def test_ai_chat_routing(
    client: TestClient,
    profile: dict,
):
    response = client.post(
        "/api/chat",
        json={
            "message": "法国夺冠概率",
            "session_id": "pytest",
            "user_id": profile["id"],
        },
    )

    assert response.status_code == 200

    body = response.json()

    assert body["agent"] == "Prediction Agent"
    assert body["model_mode"] == "local"
    assert "概率" in body["answer"]
    assert "法国" in body["answer"]
    assert body["metadata"]["fallback_used"] is True
    assert body["metadata"]["fallback_reason"]


def test_agent_route_matrix():
    graph = get_football_graph()
    cases = [
        ("普通足球知识：什么是越位", "football"),
        ("法国队战术特点", "team"),
        ("姆巴佩近期状态", "player"),
        ("世界杯新闻", "news"),
        ("法国夺冠概率", "prediction"),
        ("上传比赛截图", "vision"),
        ("生成法国夺冠海报", "generation"),
    ]

    for question, route in cases:
        state = graph.route({"question": question})
        assert state["route"] == route


def test_prediction_agent_uses_ark_explanation_when_available(
    client: TestClient,
    monkeypatch: pytest.MonkeyPatch,
):
    class FakeLLM:
        async def complete_with_result(self, *args, **kwargs):
            return LLMResult(
                content="法国的解释来自模型，但概率数值以本地平台模型为准。",
                model_mode="ark",
                fallback_used=False,
                fallback_reason=None,
            )

    monkeypatch.setattr("backend.services.agents.get_llm_service", lambda: FakeLLM())

    response = client.post(
        "/api/chat",
        json={"message": "法国夺冠概率", "session_id": "pytest-ark"},
    )

    assert response.status_code == 200

    body = response.json()

    assert body["agent"] == "Prediction Agent"
    assert body["model_mode"] == "ark"
    assert "ARK解读" in body["answer"]
    assert body["metadata"]["fallback_used"] is False
    assert body["metadata"]["fallback_reason"] is None


def test_news_credibility_analysis(client: TestClient):
    response = client.post(
        "/api/news/analyze",
        json={
            "title": "震惊：法国已经百分百夺冠，立刻转发",
            "content": "没有来源的内部绝密消息",
            "source": "未知来源",
        },
    )

    assert response.status_code == 200
    assert response.json()["credibility_score"] < 70


def test_vision_upload(client: TestClient):
    image = Image.new(
        "RGB",
        (640, 420),
        (35, 120, 55),
    )

    buffer = io.BytesIO()
    image.save(buffer, format="PNG")

    response = client.post(
        "/api/vision/analyze",
        files={
            "file": (
                "field.png",
                buffer.getvalue(),
                "image/png",
            )
        },
        data={
            "analysis_type": "formation",
        },
    )

    assert response.status_code == 200
    assert "confidence" in response.json()


def test_vision_rejects_non_image_file(client: TestClient):
    response = client.post(
        "/api/vision/analyze",
        files={"file": ("not-image.txt", b"not an image", "text/plain")},
        data={"analysis_type": "auto"},
    )

    assert response.status_code == 400
    assert "JPG" in response.json()["detail"]
