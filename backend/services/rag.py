from __future__ import annotations

import hashlib
import json
import logging
import math
import re
from functools import lru_cache

import numpy as np
from chromadb.config import Settings as ChromaSettings
from langchain_chroma import Chroma
from langchain_core.documents import Document
from langchain_core.embeddings import Embeddings
from langchain_openai import OpenAIEmbeddings

from backend.core.config import ROOT_DIR, get_settings

logger = logging.getLogger(__name__)


class HashingEmbeddings(Embeddings):
    """Offline deterministic embeddings using signed feature hashing.

    This keeps the knowledge base fully functional without downloading a large model.
    If ARK_EMBEDDING_MODEL is configured, the service switches to the compatible API.
    """

    def __init__(self, dimensions: int = 384) -> None:
        self.dimensions = dimensions

    def _tokens(self, text: str) -> list[str]:
        normalized = re.sub(r"\s+", " ", text.lower().strip())
        words = re.findall(r"[a-z0-9]+", normalized)
        chinese = re.findall(r"[\u4e00-\u9fff]", normalized)
        bigrams = ["".join(chinese[i : i + 2]) for i in range(max(0, len(chinese) - 1))]
        return words + chinese + bigrams

    def _embed(self, text: str) -> list[float]:
        vector = np.zeros(self.dimensions, dtype=np.float32)
        for token in self._tokens(text):
            digest = hashlib.blake2b(token.encode("utf-8"), digest_size=8).digest()
            value = int.from_bytes(digest, "big")
            index = value % self.dimensions
            sign = 1.0 if (value >> 8) & 1 else -1.0
            vector[index] += sign * (1.0 + math.log1p(len(token)))
        norm = np.linalg.norm(vector)
        if norm:
            vector /= norm
        return vector.tolist()

    def embed_documents(self, texts: list[str]) -> list[list[float]]:
        return [self._embed(text) for text in texts]

    def embed_query(self, text: str) -> list[float]:
        return self._embed(text)


class RAGService:
    def __init__(self) -> None:
        self.settings = get_settings()
        self.persist_directory = ROOT_DIR / "storage" / "chroma"
        self.collection_name = "worldcup_knowledge"
        self._store: Chroma | None = None

    def _embedding(self) -> Embeddings:
        if self.settings.llm_enabled and self.settings.ark_embedding_model:
            return OpenAIEmbeddings(
                api_key=self.settings.ark_api_key,
                base_url=self.settings.ark_openai_base_url,
                model=self.settings.ark_embedding_model,
            )
        return HashingEmbeddings()

    def _documents(self) -> list[Document]:
        data_dir = ROOT_DIR / "data"
        docs: list[Document] = []
        for filename in ["teams.json", "players.json", "legends.json", "history.json"]:
            rows = json.loads((data_dir / filename).read_text(encoding="utf-8"))
            for row in rows:
                if filename == "teams.json":
                    content = (
                        f"球队：{row['name']}（{row['english_name']}）。洲际足联：{row['confederation']}。"
                        f"世界排名展示值：{row['world_rank']}。世界杯冠军次数：{row['titles']}。"
                        f"历史：{row['history']} 战术风格：{row['style']}。"
                        f"优势：{'、'.join(row['strengths'])}。弱点：{'、'.join(row['weaknesses'])}。"
                    )
                    metadata = {
                        "type": "team",
                        "name": row["name"],
                        "slug": row["slug"],
                        "source": filename,
                    }
                elif filename in {"players.json", "legends.json"}:
                    content = (
                        f"球员：{row['name']}（{row['english_name']}），国家：{row['country']}，"
                        f"位置：{row['position']}，俱乐部：{row['club']}。职业履历：{row['career']} "
                        f"世界杯出场：{row['world_cup_appearances']}，进球：{row['world_cup_goals']}，"
                        f"助攻：{row['world_cup_assists']}。优势：{'、'.join(row['strengths'])}。"
                        f"影响：{row['impact']}"
                    )
                    metadata = {
                        "type": "legend" if filename == "legends.json" else "player",
                        "name": row["name"],
                        "slug": row["slug"],
                        "source": filename,
                    }
                else:
                    content = (
                        f"{row['year']}年世界杯，东道主：{row['host']}，冠军：{row['champion']}，"
                        f"亚军：{row['runner_up']}，决赛比分：{row['score']}，金靴：{row['golden_boot']}。"
                        f"经典记忆：{row['highlight']}"
                    )
                    metadata = {"type": "history", "name": str(row["year"]), "source": filename}
                docs.append(Document(page_content=content, metadata=metadata))
        rule_docs = [
            (
                "越位规则",
                "进攻球员接队友传球瞬间，若比球和倒数第二名防守球员更接近球门线，并参与进攻，可能构成越位。处于本方半场、球门球、界外球或角球直接接球不判越位。",
            ),
            (
                "4-3-3阵型",
                "4-3-3通常提供良好边路宽度与前场压迫结构。单后腰需要承担较大覆盖责任，边后卫前插后身后空间是常见风险。",
            ),
            (
                "4-2-3-1阵型",
                "4-2-3-1通过双后腰保护防线，前腰连接中锋与边锋。优势是攻守层次清晰，风险是中锋孤立或双后腰站位过深。",
            ),
            (
                "高位压迫",
                "高位压迫通过前场协同封锁出球方向，在靠近对方球门区域夺回球权。它依赖跑动同步和身后保护，失败后容易暴露大空间。",
            ),
            (
                "低位防守",
                "低位防守压缩禁区前空间，迫使对手在外围传导或传中。反击出口与二点球保护决定其攻守转换质量。",
            ),
            (
                "预期进球xG",
                "预期进球用射门位置、角度、身体部位和进攻方式等特征估计一次射门转化为进球的概率。它适合衡量机会质量，不等于比赛必然结果。",
            ),
        ]
        docs.extend(
            Document(
                page_content=text, metadata={"type": "rule", "name": name, "source": "内置足球知识"}
            )
            for name, text in rule_docs
        )
        return docs

    def build(self, force: bool = False) -> int:
        docs = self._documents()
        marker = self.persist_directory / ".built"
        if force and self.persist_directory.exists():
            import shutil

            shutil.rmtree(self.persist_directory)
            self.persist_directory.mkdir(parents=True, exist_ok=True)
        self.persist_directory.mkdir(parents=True, exist_ok=True)
        if not marker.exists():
            self._store = Chroma.from_documents(
                documents=docs,
                embedding=self._embedding(),
                collection_name=self.collection_name,
                persist_directory=str(self.persist_directory),
                client_settings=ChromaSettings(anonymized_telemetry=False),
                collection_metadata={"hnsw:space": "cosine"},
            )
            marker.write_text(str(len(docs)), encoding="utf-8")
        else:
            self._store = Chroma(
                collection_name=self.collection_name,
                embedding_function=self._embedding(),
                persist_directory=str(self.persist_directory),
                client_settings=ChromaSettings(anonymized_telemetry=False),
            )
        return len(docs)

    @property
    def store(self) -> Chroma:
        if self._store is None:
            self.build()
        assert self._store is not None
        return self._store

    def search(self, query: str, k: int = 5) -> list[dict]:
        try:
            results = self.store.similarity_search_with_relevance_scores(query, k=k)
            return [
                {
                    "content": doc.page_content,
                    "metadata": doc.metadata,
                    "score": round(float(score), 4),
                }
                for doc, score in results
            ]
        except Exception as exc:
            logger.warning("Chroma search failed, rebuilding once: %s", exc)
            self.build(force=True)
            results = self.store.similarity_search_with_relevance_scores(query, k=k)
            return [
                {
                    "content": doc.page_content,
                    "metadata": doc.metadata,
                    "score": round(float(score), 4),
                }
                for doc, score in results
            ]


@lru_cache(maxsize=1)
def get_rag_service() -> RAGService:
    return RAGService()
