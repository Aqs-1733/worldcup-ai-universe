from __future__ import annotations

import asyncio
import logging
import os
from dataclasses import dataclass
from functools import lru_cache

import httpx
from langchain_core.messages import HumanMessage, SystemMessage
from langchain_openai import ChatOpenAI

from backend.core.config import get_settings

logger = logging.getLogger(__name__)


@dataclass(frozen=True)
class LLMResult:
    content: str | None
    model_mode: str
    fallback_used: bool
    fallback_reason: str | None


class LLMService:
    def __init__(self) -> None:
        self.settings = get_settings()
        self._client: ChatOpenAI | None = None
        if self.settings.langchain_api_key and not os.getenv("LANGCHAIN_API_KEY"):
            os.environ["LANGCHAIN_API_KEY"] = self.settings.langchain_api_key
        if self.settings.langsmith_api_key and not os.getenv("LANGSMITH_API_KEY"):
            os.environ["LANGSMITH_API_KEY"] = self.settings.langsmith_api_key
        tracing_project = self.settings.langchain_project or self.settings.langsmith_project
        if tracing_project:
            os.environ.setdefault("LANGCHAIN_PROJECT", tracing_project)
            os.environ.setdefault("LANGSMITH_PROJECT", tracing_project)
        tracing_endpoint = self.settings.langchain_endpoint or self.settings.langsmith_endpoint
        if tracing_endpoint:
            os.environ.setdefault("LANGCHAIN_ENDPOINT", tracing_endpoint)
            os.environ.setdefault("LANGSMITH_ENDPOINT", tracing_endpoint)
        os.environ.setdefault(
            "LANGCHAIN_TRACING_V2", "true" if self.settings.langchain_tracing_v2 else "false"
        )

    @property
    def enabled(self) -> bool:
        return self.settings.llm_enabled

    @property
    def client(self) -> ChatOpenAI | None:
        if not self.enabled:
            return None
        if self._client is None:
            self._client = ChatOpenAI(
                api_key=self.settings.ark_api_key,
                base_url=self.settings.ark_openai_base_url,
                model=self.settings.ark_model,
                temperature=0.35,
                max_retries=1,
                timeout=20,
            )
        return self._client

    def _safe_error(self, exc: Exception) -> str:
        text = str(exc) or exc.__class__.__name__
        for secret in [self.settings.ark_api_key, self.settings.langchain_api_key]:
            if secret:
                text = text.replace(secret, "***")
        return text[:300]

    def _classify_error(self, exc: Exception) -> str:
        text = self._safe_error(exc).lower()
        status_code = getattr(getattr(exc, "response", None), "status_code", None)
        if isinstance(exc, TimeoutError) or "timeout" in text or "timed out" in text:
            return "ARK 请求超时"
        if status_code in {401, 403} or "401" in text or "403" in text:
            return "ARK 鉴权失败或无权限"
        if status_code == 404 or "404" in text:
            return "ARK 模型或接口地址不存在"
        if status_code == 429 or "429" in text:
            return "ARK 请求限流"
        return "ARK 网络或服务异常"

    def _chat_completions_url(self) -> str:
        base = self.settings.ark_openai_base_url.rstrip("/")
        if base.endswith("/chat/completions"):
            return base
        return f"{base}/chat/completions"

    async def _chat_completions(
        self,
        system_prompt: str,
        user_prompt: str,
        *,
        temperature: float,
        timeout_s: float,
        max_tokens: int,
    ) -> str:
        timeout = httpx.Timeout(timeout_s, connect=min(5.0, timeout_s), read=timeout_s)
        payload = {
            "model": self.settings.ark_model,
            "temperature": temperature,
            "max_tokens": max_tokens,
            "thinking": {"type": "disabled"},
            "messages": [
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": user_prompt},
            ],
        }
        headers = {
            "Authorization": f"Bearer {self.settings.ark_api_key}",
            "Content-Type": "application/json",
        }
        async with httpx.AsyncClient(timeout=timeout, follow_redirects=True) as client:
            response = await client.post(self._chat_completions_url(), headers=headers, json=payload)
            response.raise_for_status()
        data = response.json()
        choices = data.get("choices") or []
        if not choices:
            return ""
        message = choices[0].get("message") or {}
        content = message.get("content") or message.get("reasoning_content", "")
        if isinstance(content, list):
            return "\n".join(
                str(item.get("text", "")) if isinstance(item, dict) else str(item)
                for item in content
            ).strip()
        return str(content).strip()

    def unavailable_result(self) -> LLMResult:
        return LLMResult(
            content=None,
            model_mode="local",
            fallback_used=True,
            fallback_reason="ARK 未配置",
        )

    async def complete_with_result(
        self,
        system_prompt: str,
        user_prompt: str,
        *,
        temperature: float = 0.35,
        timeout_s: float = 25.0,
        max_tokens: int = 360,
    ) -> LLMResult:
        if not self.enabled:
            return self.unavailable_result()
        try:
            response = await asyncio.wait_for(
                self._chat_completions(
                    system_prompt,
                    user_prompt,
                    temperature=temperature,
                    timeout_s=timeout_s,
                    max_tokens=max_tokens,
                ),
                timeout=timeout_s,
            )
            content = response.strip()
            if not content:
                return LLMResult(
                    content=None,
                    model_mode="local",
                    fallback_used=True,
                    fallback_reason="ARK 返回空内容",
                )
            return LLMResult(
                content=content,
                model_mode="ark",
                fallback_used=False,
                fallback_reason=None,
            )
        except Exception as exc:
            reason = self._classify_error(exc)
            logger.warning("%s, falling back to local mode: %s", reason, self._safe_error(exc))
            return LLMResult(
                content=None,
                model_mode="local",
                fallback_used=True,
                fallback_reason=reason,
            )

    async def complete(
        self,
        system_prompt: str,
        user_prompt: str,
        *,
        temperature: float = 0.35,
        timeout_s: float = 25.0,
        max_tokens: int = 360,
    ) -> str | None:
        result = await self.complete_with_result(
            system_prompt,
            user_prompt,
            temperature=temperature,
            timeout_s=timeout_s,
            max_tokens=max_tokens,
        )
        return result.content

    async def analyze_image(self, image_data_url: str, prompt: str) -> str | None:
        if not self.client:
            return None
        try:
            message = HumanMessage(
                content=[
                    {"type": "text", "text": prompt},
                    {"type": "image_url", "image_url": {"url": image_data_url}},
                ]
            )
            response = await asyncio.wait_for(
                self.client.ainvoke(
                    [
                        SystemMessage(
                            content="你是世界杯足球视觉分析专家。只描述图中有依据的内容，对不确定项明确说明。"
                        ),
                        message,
                    ]
                ),
                timeout=20,
            )
            return str(response.content).strip()
        except Exception as exc:
            logger.warning("Vision LLM request failed: %s", self._safe_error(exc))
            return None


@lru_cache(maxsize=1)
def get_llm_service() -> LLMService:
    return LLMService()
