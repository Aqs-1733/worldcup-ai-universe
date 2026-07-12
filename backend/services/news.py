from __future__ import annotations

import asyncio
import hashlib
import json
import logging
import re
from datetime import datetime, timezone
from email.utils import parsedate_to_datetime
from urllib.parse import urljoin

import feedparser
import httpx
from bs4 import BeautifulSoup
from rapidfuzz.fuzz import token_set_ratio
from sqlalchemy import delete, desc, select
from sqlalchemy.orm import Session

from backend.core.models import News
from backend.services.llm import get_llm_service

logger = logging.getLogger(__name__)

SOURCES = [
    {
        "name": "BBC Sport",
        "url": "https://feeds.bbci.co.uk/sport/football/rss.xml",
        "type": "rss",
        "weight": 0.96,
    },
    {
        "name": "ESPN",
        "url": "https://www.espn.com/espn/rss/soccer/news",
        "type": "rss",
        "weight": 0.92,
    },
    {
        "name": "The Guardian Football",
        "url": "https://www.theguardian.com/football/rss",
        "type": "rss",
        "weight": 0.93,
    },
    {
        "name": "Sky Sports Football",
        "url": "https://www.skysports.com/rss/12040",
        "type": "rss",
        "weight": 0.9,
    },
    {
        "name": "FIFA",
        "url": "https://www.fifa.com/en/tournaments/mens/worldcup/canadamexicousa2026/articles",
        "type": "html",
        "weight": 0.99,
    },
    {
        "name": "FIFA Media Releases",
        "url": "https://inside.fifa.com/organisation/media/all-media-releases",
        "type": "html",
        "weight": 0.99,
    },
    {
        "name": "AP Soccer",
        "url": "https://apnews.com/hub/soccer?output=rss",
        "type": "rss",
        "weight": 0.91,
    },
    {
        "name": "CBS Sports Soccer",
        "url": "https://www.cbssports.com/rss/headlines/soccer/",
        "type": "rss",
        "weight": 0.88,
    },
    {
        "name": "Google News World Cup",
        "url": "https://news.google.com/rss/search?q=2026%20World%20Cup%20football%20OR%20soccer&hl=en-US&gl=US&ceid=US:en",
        "type": "rss",
        "weight": 0.86,
        "limit": 35,
    },
    {
        "name": "Google News 中文足球",
        "url": "https://news.google.com/rss/search?q=%E4%B8%96%E7%95%8C%E6%9D%AF%20%E8%B6%B3%E7%90%83%20OR%202026%E4%B8%96%E7%95%8C%E6%9D%AF&hl=zh-CN&gl=CN&ceid=CN:zh-Hans",
        "type": "rss",
        "weight": 0.86,
        "limit": 35,
    },
    {
        "name": "央视体育",
        "url": "https://sports.cctv.com/football/",
        "type": "html",
        "weight": 0.94,
    },
    {"name": "新华社体育", "url": "https://sports.news.cn/", "type": "html", "weight": 0.97},
]

SOURCE_WEIGHT = {item["name"].lower(): item["weight"] for item in SOURCES}
DEFAULT_CATEGORIES = ["足球"]
TEAM_NAMES = [
    "法国",
    "阿根廷",
    "西班牙",
    "英格兰",
    "巴西",
    "德国",
    "葡萄牙",
    "荷兰",
    "意大利",
    "日本",
    "韩国",
    "美国",
    "墨西哥",
    "加拿大",
    "摩洛哥",
    "乌拉圭",
    "France",
    "Argentina",
    "Spain",
    "England",
    "Brazil",
    "Germany",
    "Portugal",
    "Japan",
]
PLAYER_NAMES = [
    "姆巴佩",
    "梅西",
    "哈兰德",
    "贝林厄姆",
    "维尼修斯",
    "亚马尔",
    "凯恩",
    "罗德里",
    "Mbappe",
    "Messi",
    "Haaland",
    "Bellingham",
    "Vinicius",
    "Yamal",
    "Kane",
    "Rodri",
]


def classify_categories(text: str) -> list[str]:
    lowered = text.lower()
    rules = [
        (
            "比赛内容",
            [
                "lineup",
                "fixture",
                "match",
                "vs",
                "score",
                "result",
                "draw",
                "qualifier",
                "qualified",
                "round of",
                "quarter",
                "semi-final",
                "semifinal",
                "final",
                "赛程",
                "比赛",
                "对阵",
                "首发",
                "比分",
                "战报",
                "抽签",
                "晋级",
                "出线",
                "淘汰赛",
                "八强",
                "四强",
                "决赛",
            ],
        ),
        (
            "战术分析",
            [
                "tactic",
                "analysis",
                "formation",
                "pressing",
                "counterattack",
                "possession",
                "战术",
                "阵型",
                "压迫",
                "控球",
                "反击",
                "打法",
                "分析",
            ],
        ),
        (
            "球队动态",
            [
                "injury",
                "training",
                "squad",
                "roster",
                "coach",
                "manager",
                "camp",
                "fitness",
                "伤病",
                "训练",
                "复出",
                "名单",
                "阵容",
                "主帅",
                "教练",
                "备战",
                "恢复",
            ],
        ),
        (
            "球迷内容",
            [
                "fan",
                "supporter",
                "crowd",
                "ticket",
                "stadium",
                "球迷",
                "看台",
                "主场",
                "客场",
                "门票",
                "观赛",
                "助威",
            ],
        ),
        (
            "娱乐内容",
            [
                "celebrity",
                "music",
                "entertainment",
                "festival",
                "city",
                "social media",
                "娱乐",
                "花边",
                "音乐",
                "社媒",
                "明星",
                "城市活动",
            ],
        ),
        (
            "二创内容",
            [
                "meme",
                "poster",
                "video",
                "tiktok",
                "shorts",
                "clip",
                "二创",
                "海报",
                "短视频",
                "梗图",
                "剪辑",
                "表情包",
            ],
        ),
        (
            "技术",
            [
                "technology",
                "var",
                "ai",
                "camera",
                "broadcast",
                "streaming",
                "data",
                "技术",
                "人工智能",
                "视频裁判",
                "转播",
                "数据",
                "平台",
            ],
        ),
        ("世界杯", ["world cup", "fifa", "世界杯", "世预赛", "美加墨"]),
        ("足球", ["football", "soccer", "futbol", "足球", "国家队", "俱乐部"]),
    ]
    categories = [
        category for category, keywords in rules if any(keyword in lowered for keyword in keywords)
    ]
    return list(dict.fromkeys(categories or DEFAULT_CATEGORIES))


def classify_category(text: str) -> str:
    return classify_categories(text)[0]


def _clean(text: str) -> str:
    return re.sub(r"\s+", " ", BeautifulSoup(text or "", "html.parser").get_text(" ")).strip()


async def _fetch_url(url: str, *, timeout_s: float = 12.0) -> httpx.Response:
    headers = {"User-Agent": "WorldCupAIUniverse/1.0 (+educational project)"}
    timeout = httpx.Timeout(timeout_s, connect=min(8.0, timeout_s))
    last_error: Exception | None = None
    # Try direct DNS first, then respect proxy/VPN env vars. This helps when a local
    # proxy breaks DNS while still keeping a second path for blocked sources.
    for trust_env in (False, True):
        try:
            async with httpx.AsyncClient(
                headers=headers,
                timeout=timeout,
                follow_redirects=True,
                trust_env=trust_env,
            ) as client:
                response = await client.get(url)
                response.raise_for_status()
                return response
        except Exception as exc:
            last_error = exc
    assert last_error is not None
    raise last_error


def _date(value) -> datetime:
    if not value:
        return datetime.now(timezone.utc)
    try:
        parsed = parsedate_to_datetime(value)
        return parsed if parsed.tzinfo else parsed.replace(tzinfo=timezone.utc)
    except Exception:
        return datetime.now(timezone.utc)


def related_entities(text: str) -> tuple[list[str], list[str]]:
    teams = [name for name in TEAM_NAMES if name.lower() in text.lower()]
    players = [name for name in PLAYER_NAMES if name.lower() in text.lower()]
    return list(dict.fromkeys(teams))[:8], list(dict.fromkeys(players))[:8]


class NewsService:
    def __init__(self):
        self._refresh_lock = asyncio.Lock()

    async def _fetch_source(self, source: dict) -> list[dict]:
        response = await _fetch_url(source["url"])
        if source["type"] == "rss":
            feed = feedparser.parse(response.content)
            rows = []
            for item in feed.entries[: int(source.get("limit", 24))]:
                title = _clean(item.get("title", ""))
                summary = _clean(item.get("summary", item.get("description", "")))
                if not title:
                    continue
                item_source = item.get("source", {})
                source_title = ""
                if isinstance(item_source, dict):
                    source_title = _clean(str(item_source.get("title", "")))
                source_name = (
                    f"{source['name']} / {source_title}" if source_title else source["name"]
                )
                rows.append(
                    {
                        "title": title,
                        "summary": summary[:900],
                        "source": source_name,
                        "source_url": item.get("link", ""),
                        "image_url": "",
                        "published_at": _date(item.get("published", item.get("updated"))),
                    }
                )
            return rows
        soup = BeautifulSoup(response.text, "html.parser")
        rows = []
        seen: set[str] = set()
        for link in soup.select("a[href]"):
            title = _clean(link.get_text(" "))
            if len(title) < 16 or len(title) > 180 or title in seen:
                continue
            href = urljoin(source["url"], link.get("href", ""))
            if not href.startswith("http"):
                continue
            football_hint = any(
                word in title.lower()
                for word in ["world cup", "football", "soccer", "世界杯", "足球", "国家队", "fifa"]
            )
            if not football_hint:
                continue
            seen.add(title)
            rows.append(
                {
                    "title": title,
                    "summary": "",
                    "source": source["name"],
                    "source_url": href,
                    "image_url": "",
                    "published_at": datetime.now(timezone.utc),
                }
            )
            if len(rows) >= 15:
                break
        return rows

    def _score(
        self, source: str, published_at: datetime, cross_sources: int, has_url: bool = True
    ) -> tuple[float, str, list[str]]:
        source_key = source.lower().split(" / ", 1)[0]
        source_score = SOURCE_WEIGHT.get(source_key, 0.55)
        age_hours = max(
            0.0,
            (datetime.now(timezone.utc) - published_at.astimezone(timezone.utc)).total_seconds()
            / 3600,
        )
        freshness = max(0.35, 1.0 - min(age_hours, 720) / 1000)
        cross = min(1.0, cross_sources / 3)
        score = (
            source_score * 0.55 + freshness * 0.18 + cross * 0.22 + (0.05 if has_url else 0)
        ) * 100
        score = round(min(99.0, max(35.0, score)), 1)
        if score >= 85:
            label = "高可信"
        elif score >= 68:
            label = "基本可信"
        elif score >= 50:
            label = "需要核验"
        else:
            label = "疑似假新闻"
        notes = [
            f"来源基础权重 {round(source_score * 100)}%",
            f"发现 {cross_sources} 个相近来源",
            f"发布时间距今约 {round(age_hours)} 小时",
        ]
        return score, label, notes

    def analyze(
        self,
        db: Session,
        title: str,
        content: str,
        source: str,
        published_at: datetime | None = None,
    ) -> dict:
        published_at = published_at or datetime.now(timezone.utc)
        if published_at.tzinfo is None:
            published_at = published_at.replace(tzinfo=timezone.utc)
        candidates = db.scalars(select(News).order_by(desc(News.published_at)).limit(200)).all()
        similar = []
        for item in candidates:
            ratio = token_set_ratio(title, item.title)
            if ratio >= 66 and item.source.lower() != source.lower():
                similar.append({"source": item.source, "title": item.title, "similarity": ratio})
        score, label, notes = self._score(
            source, published_at, len({x["source"] for x in similar}), bool(source)
        )
        suspicious_words = ["震惊", "内部绝密", "百分百夺冠", "官方未公布但", "立刻转发"]
        hits = [word for word in suspicious_words if word in title + content]
        if hits:
            score = max(25.0, score - 18)
            label = "需要核验" if score >= 50 else "疑似假新闻"
            notes.append("标题或正文含高风险煽动表达：" + "、".join(hits))
        return {
            "credibility_score": round(score, 1),
            "credibility_label": label,
            "cross_sources": similar[:6],
            "notes": notes,
            "judgement": "真实新闻"
            if score >= 68
            else ("疑似假新闻" if score < 50 else "信息不足，建议继续核验"),
        }

    async def refresh(self, db: Session) -> dict:
        if self._refresh_lock.locked():
            return {
                "skipped": "refresh_in_progress",
                "collected": 0,
                "inserted": 0,
                "duplicates": 0,
                "failures": [],
                "sources": len(SOURCES),
            }
        async with self._refresh_lock:
            db.execute(delete(News).where(News.source_url == ""))
            db.execute(delete(News).where(News.source.like("WorldCup AI%")))
            db.commit()
            results = await asyncio.gather(
                *(self._fetch_source(source) for source in SOURCES), return_exceptions=True
            )
            inserted = 0
            duplicates = 0
            failures = []
            collected: list[dict] = []
            for source, result in zip(SOURCES, results):
                if isinstance(result, Exception):
                    error = str(result).strip() or result.__class__.__name__
                    failures.append({"source": source["name"], "error": error[:200]})
                    continue
                collected.extend(result)
            new_items: list[News] = []
            for row in collected:
                content_hash = hashlib.sha256(
                    f"{row['title']}|{row['source_url']}".encode("utf-8")
                ).hexdigest()
                if db.scalar(select(News.id).where(News.content_hash == content_hash)):
                    duplicates += 1
                    continue
                teams, players = related_entities(row["title"] + " " + row.get("summary", ""))
                categories = classify_categories(row["title"] + " " + row.get("summary", ""))
                verification = self.analyze(
                    db, row["title"], row.get("summary", ""), row["source"], row["published_at"]
                )
                verification = {**verification, "categories": categories}
                item = News(
                    **row,
                    category=categories[0],
                    related_teams=teams,
                    related_players=players,
                    credibility_score=verification["credibility_score"],
                    credibility_label=verification["credibility_label"],
                    verification=verification,
                    content_hash=content_hash,
                )
                db.add(item)
                new_items.append(item)
                inserted += 1
            db.commit()
            if new_items:
                await self.localize_listing(db, new_items)
            return {
                "collected": len(collected),
                "inserted": inserted,
                "duplicates": duplicates,
                "failures": failures,
                "sources": len(SOURCES),
                "refreshed_at": datetime.now(timezone.utc).isoformat(),
            }

    @staticmethod
    def _looks_chinese(text: str) -> bool:
        chars = [char for char in text if char.strip()]
        if not chars:
            return False
        cjk = sum(1 for char in chars if "\u4e00" <= char <= "\u9fff")
        return cjk / max(1, len(chars)) > 0.18

    @staticmethod
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

    def categories_for_item(self, item: News) -> list[str]:
        verification = item.verification or {}
        raw_categories = verification.get("categories")
        if isinstance(raw_categories, list):
            categories = [str(category).strip() for category in raw_categories if str(category).strip()]
        else:
            categories = classify_categories(f"{item.title} {item.summary}")
        if item.category and item.category not in categories:
            categories.insert(0, item.category)
        return list(dict.fromkeys(categories or [item.category or "足球"]))

    def _news_payload(self, item: News) -> dict:
        verification = item.verification or {}
        categories = self.categories_for_item(item)
        return {
            "id": item.id,
            "title": item.title,
            "summary": item.summary,
            "original_title": str(verification.get("original_title", "")),
            "original_summary": str(verification.get("original_summary", "")),
            "language": str(verification.get("language", "zh" if self._looks_chinese(item.title + item.summary) else "non-zh")),
            "source": item.source,
            "source_url": item.source_url,
            "image_url": item.image_url,
            "published_at": item.published_at,
            "category": item.category,
            "categories": categories,
            "related_teams": item.related_teams,
            "related_players": item.related_players,
            "credibility_score": item.credibility_score,
            "credibility_label": item.credibility_label,
            "verification": verification,
        }

    async def _translate_listing_batch(self, items: list[News]) -> dict[int, dict[str, str]]:
        if not items:
            return {}
        payload = [
            {"id": item.id, "title": item.title, "summary": item.summary[:900]}
            for item in items
        ]
        raw = await get_llm_service().complete(
            "你是世界杯新闻中文编辑。把英文或其他语言新闻标题翻译并归纳成简体中文标题，摘要翻译并压缩为一到两句中文。不得添加原文没有的事实。只返回严格 JSON 数组，每项包含 id、title、summary。",
            json.dumps(payload, ensure_ascii=False),
            temperature=0.05,
            timeout_s=45,
            max_tokens=2600,
        )
        translated: dict[int, dict[str, str]] = {}
        for row in self._extract_json_array(raw):
            try:
                item_id = int(row.get("id"))
            except Exception:
                continue
            title = str(row.get("title", "")).strip()
            summary = str(row.get("summary", "")).strip()
            if title and self._looks_chinese(title):
                translated[item_id] = {"title": title, "summary": summary}
        return translated

    async def localize_listing(self, db: Session, items: list[News]) -> list[dict]:
        candidates = []
        for item in items:
            verification = item.verification or {}
            if verification.get("original_title"):
                continue
            if not self._looks_chinese(f"{item.title}\n{item.summary}"):
                candidates.append(item)

        for index in range(0, len(candidates), 8):
            batch = candidates[index : index + 8]
            translations = await self._translate_listing_batch(batch)
            for item in batch:
                translated = translations.get(item.id)
                if not translated:
                    continue
                original_title = item.title
                original_summary = item.summary
                item.title = translated["title"]
                item.summary = translated["summary"] or original_summary
                item.verification = {
                    **(item.verification or {}),
                    "original_title": original_title,
                    "original_summary": original_summary,
                    "language": "translated_to_zh",
                    "localized_at": datetime.now(timezone.utc).isoformat(),
                }
        if candidates:
            db.commit()
        return [self._news_payload(item) for item in items]

    @staticmethod
    def _article_text(html: str) -> str:
        soup = BeautifulSoup(html, "html.parser")
        for tag in soup(["script", "style", "noscript", "svg", "nav", "footer", "header", "aside"]):
            tag.decompose()
        selectors = [
            "article p",
            "main p",
            "[itemprop='articleBody'] p",
            "[data-component='text-block'] p",
            "p",
        ]
        paragraphs: list[str] = []
        seen: set[str] = set()
        reject = ("cookie", "subscribe", "newsletter", "sign up", "advertisement")
        for selector in selectors:
            for node in soup.select(selector):
                text = _clean(node.get_text(" "))
                lowered = text.lower()
                if len(text) < 35 or any(word in lowered for word in reject) or text in seen:
                    continue
                seen.add(text)
                paragraphs.append(text)
                if len(paragraphs) >= 28:
                    break
            if paragraphs:
                break
        return "\n\n".join(paragraphs)[:7000]

    @staticmethod
    def _chunks(text: str, size: int = 1600) -> list[str]:
        parts = [part.strip() for part in re.split(r"\n{2,}", text) if part.strip()]
        chunks: list[str] = []
        current = ""
        for part in parts:
            if len(current) + len(part) + 2 <= size:
                current = f"{current}\n\n{part}".strip()
                continue
            if current:
                chunks.append(current)
            current = part
        if current:
            chunks.append(current)
        return chunks[:5]

    async def _translate_text(self, text: str, *, timeout_s: float = 30.0, max_tokens: int = 900) -> str:
        if not text.strip():
            return ""
        llm = get_llm_service()
        translated = await llm.complete(
            "你是体育新闻翻译。把用户给出的原文准确翻译成简体中文，只输出译文，不解释、不补写。",
            text[:7000],
            temperature=0.05,
            timeout_s=timeout_s,
            max_tokens=max_tokens,
        )
        return (translated or "").strip()

    async def detail(self, item: News) -> dict:
        verification = item.verification or {}
        original_title = str(verification.get("original_title") or item.title)
        original_summary = str(verification.get("original_summary") or item.summary)
        content = ""
        if item.source_url:
            response = await _fetch_url(item.source_url, timeout_s=20.0)
            content = self._article_text(response.text)
        language = "zh" if self._looks_chinese(content or f"{original_title}\n{original_summary}") else "non-zh"
        translated = {
            "title": item.title if original_title != item.title else "",
            "summary": item.summary if original_summary != item.summary else "",
            "content": "",
        }
        if language != "zh" and (item.title or item.summary or content):
            if not translated["title"]:
                translated["title"] = await self._translate_text(original_title, timeout_s=18, max_tokens=180)
            if not translated["summary"]:
                translated["summary"] = await self._translate_text(
                    original_summary, timeout_s=22, max_tokens=360
                )
            content_chunks = self._chunks(content)
            translated_parts = [
                part
                for part in [
                    await self._translate_text(chunk, timeout_s=35, max_tokens=950)
                    for chunk in content_chunks
                ]
                if part
            ]
            translated["content"] = "\n\n".join(translated_parts)
        return {
            "content": content,
            "original_title": original_title,
            "original_summary": original_summary,
            "translated_title": translated["title"],
            "translated_summary": translated["summary"],
            "translated_content": translated["content"],
            "language": language,
        }

    async def explain_analysis(self, analysis: dict, title: str, content: str) -> str | None:
        llm = get_llm_service()
        return await llm.complete(
            "你是体育新闻事实核查员。基于给定的规则评分和证据，用简洁中文解释结论，不得虚构额外来源。",
            f"标题：{title}\n正文：{content}\n规则分析：{analysis}",
            temperature=0.15,
            timeout_s=8,
            max_tokens=220,
        )
