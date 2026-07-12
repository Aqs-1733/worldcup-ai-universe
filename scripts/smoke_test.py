from __future__ import annotations

from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import argparse

import httpx


def main() -> int:
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    parser = argparse.ArgumentParser(description="WorldCup AI Universe API smoke test")
    parser.add_argument("--base-url", default="http://127.0.0.1:8000")
    args = parser.parse_args()
    checks = [
        ("健康检查", "GET", "/api/health", None),
        ("球队查询", "GET", "/api/teams?q=法国", None),
        ("球员查询", "GET", "/api/players?q=姆巴佩", None),
        ("AI聊天", "POST", "/api/chat", {"message": "法国夺冠概率", "session_id": "smoke-test"}),
        (
            "新闻分析",
            "POST",
            "/api/news/analyze",
            {
                "title": "法国队已百分百夺冠，立刻转发",
                "content": "未经证实的消息",
                "source": "未知来源",
            },
        ),
    ]
    failed = 0
    timeout = httpx.Timeout(30.0, connect=5.0, read=30.0, write=10.0, pool=5.0)
    with httpx.Client(base_url=args.base_url, timeout=timeout, trust_env=False) as client:
        for name, method, path, payload in checks:
            try:
                response = client.request(method, path, json=payload)
                ok = response.status_code < 400
                print(f"[{'PASS' if ok else 'FAIL'}] {name}: HTTP {response.status_code}")
                if not ok:
                    print(response.text[:500])
                    failed += 1
            except Exception as exc:
                print(f"[FAIL] {name}: {exc}")
                failed += 1
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
