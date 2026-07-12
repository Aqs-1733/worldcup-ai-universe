from __future__ import annotations

import re

PLAIN_TEXT_SYSTEM_INSTRUCTION = (
    "只输出可直接展示给普通用户阅读的中文文本。不要输出 Markdown 代码块、Mermaid、HTML 标签、"
    "JSON 源码或表格源码；需要分点时使用普通短句和项目符号，换行用普通换行。"
)


def clean_ai_text(text: str | None) -> str:
    if not text:
        return ""
    cleaned = re.sub(r"```mermaid[\s\S]*?```", "\n", text, flags=re.IGNORECASE)
    cleaned = re.sub(r"```[a-zA-Z0-9_-]*\s*", "", cleaned)
    cleaned = cleaned.replace("```", "")
    cleaned = re.sub(r"<br\s*/?>", "\n", cleaned, flags=re.IGNORECASE)
    cleaned = re.sub(r"&nbsp;", " ", cleaned, flags=re.IGNORECASE)
    cleaned = re.sub(r"</?[^>\n]+>", "", cleaned)
    cleaned = re.sub(r"(?m)^\s{0,3}#{1,6}\s*", "", cleaned)
    cleaned = cleaned.replace("**", "")
    cleaned = re.sub(r"\n{3,}", "\n\n", cleaned)
    return cleaned.strip()
