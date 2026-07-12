from __future__ import annotations

import base64
import math
import re
import textwrap
from datetime import datetime
from functools import lru_cache
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
import httpx
from sqlalchemy.orm import Session

from backend.core.config import ROOT_DIR, get_settings
from backend.core.models import Team
from backend.services.llm import get_llm_service
from backend.services.text_format import PLAIN_TEXT_SYSTEM_INSTRUCTION, clean_ai_text


class GenerationService:
    IMAGE_MODEL_ALIASES = {
        "doubao-seedream-4-5": "doubao-seedream-4-5-251128",
        "doubao-seedream-4.5": "doubao-seedream-4-5-251128",
        "seedream-4.5": "doubao-seedream-4-5-251128",
    }

    def _image_model(self) -> str:
        model = get_settings().ark_image_model.strip()
        return self.IMAGE_MODEL_ALIASES.get(model, model)

    def _image_generations_url(self) -> str:
        settings = get_settings()
        base = (settings.ark_image_base_url or settings.ark_openai_base_url).rstrip("/")
        if base.endswith("/images/generations"):
            return base
        return f"{base}/images/generations"

    def _save_image_bytes(self, content: bytes, suffix: str = "png") -> str:
        out_dir = ROOT_DIR / "storage" / "generated"
        out_dir.mkdir(parents=True, exist_ok=True)
        filename = f"ai_image_{datetime.now().strftime('%Y%m%d_%H%M%S_%f')}.{suffix}"
        (out_dir / filename).write_bytes(content)
        return f"/static/generated/{filename}"

    async def create_ai_image(self, prompt: str) -> tuple[str, str, str]:
        settings = get_settings()
        if not settings.image_generation_enabled:
            return "", "", "未配置图片生成模型：请在 .env 设置 ARK_IMAGE_MODEL"
        image_model = self._image_model()
        headers = {
            "Authorization": f"Bearer {settings.ark_api_key}",
            "Content-Type": "application/json",
        }
        payload = {
            "model": image_model,
            "prompt": prompt,
            "size": settings.ark_image_size,
        }
        if settings.ark_image_response_format:
            payload["response_format"] = settings.ark_image_response_format
        timeout = httpx.Timeout(80.0, connect=10.0, read=80.0)
        try:
            async with httpx.AsyncClient(timeout=timeout, follow_redirects=True, trust_env=True) as client:
                response = await client.post(self._image_generations_url(), headers=headers, json=payload)
                if response.status_code == 400:
                    payload.pop("response_format", None)
                    response = await client.post(self._image_generations_url(), headers=headers, json=payload)
                response.raise_for_status()
                data = response.json()
                item = (data.get("data") or [{}])[0]
                if item.get("b64_json"):
                    return (
                        self._save_image_bytes(base64.b64decode(item["b64_json"]), "png"),
                        image_model,
                        "",
                    )
                if item.get("url"):
                    image_response = await client.get(item["url"])
                    image_response.raise_for_status()
                    content_type = image_response.headers.get("content-type", "")
                    suffix = "jpg" if "jpeg" in content_type else "png"
                    return self._save_image_bytes(image_response.content, suffix), image_model, ""
        except httpx.HTTPStatusError as exc:
            body = exc.response.text.strip()[:300]
            return (
                "",
                image_model,
                f"图片生成失败：HTTP {exc.response.status_code}。模型：{image_model}。响应：{body or exc.response.reason_phrase}",
            )
        except Exception as exc:
            return "", image_model, f"图片生成失败：{str(exc)[:180]}"
        return "", image_model, "图片生成接口未返回图片"

    def _split_creative_sections(self, text: str) -> dict[str, str]:
        aliases = {
            "朋友圈文案": "social",
            "社媒文案": "social",
            "海报提示词": "poster",
            "海报 Prompt": "poster",
            "海报Prompt": "poster",
            "30秒视频脚本": "script",
            "视频脚本": "script",
            "口号": "slogans",
            "3条口号": "slogans",
        }
        sections: dict[str, list[str]] = {}
        current: str | None = None
        for line in text.splitlines():
            stripped = line.strip()
            matched = None
            for label, key in aliases.items():
                if stripped.startswith(label):
                    matched = key
                    rest = stripped[len(label) :].lstrip("：: -")
                    sections.setdefault(key, [])
                    if rest:
                        sections[key].append(rest)
                    break
            if matched:
                current = matched
            elif current:
                sections.setdefault(current, []).append(line)
        return {key: clean_ai_text("\n".join(lines)) for key, lines in sections.items()}

    def _parse_slogans(self, text: str, fallback: list[str]) -> list[str]:
        lines = []
        for line in text.splitlines():
            slogan = re.sub(r"^\s*(?:[-•]|\d+[.、])\s*", "", line).strip()
            if slogan:
                lines.append(slogan)
        return lines[:3] or fallback

    def _font(self, size: int, bold: bool = False):
        candidates = [
            Path("C:/Windows/Fonts/msyhbd.ttc" if bold else "C:/Windows/Fonts/msyh.ttc"),
            Path(
                "/usr/share/fonts/opentype/noto/NotoSansCJK-Bold.ttc"
                if bold
                else "/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc"
            ),
            Path(
                "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
                if bold
                else "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
            ),
        ]
        for path in candidates:
            if path.exists():
                return ImageFont.truetype(str(path), size=size)
        return ImageFont.load_default()

    def create_poster(self, topic: str, team: Team | None = None) -> str:
        width, height = 1200, 1600
        primary = team.primary_color if team else "#071d31"
        secondary = team.secondary_color if team else "#16d39a"
        img = Image.new("RGB", (width, height), primary)
        draw = ImageDraw.Draw(img)
        # Stadium-like radial light bands
        for i in range(24):
            angle = 2 * math.pi * i / 24
            x = width / 2 + math.cos(angle) * 1000
            y = height * 0.35 + math.sin(angle) * 1000
            draw.polygon([(width / 2, height * 0.35), (x, y), (x + 80, y + 80)], fill=(38, 72, 66))
        # translucent layers rendered on RGBA overlay
        overlay = Image.new("RGBA", img.size, (0, 0, 0, 0))
        od = ImageDraw.Draw(overlay)
        od.ellipse(
            (-220, -240, 780, 760), fill=(255, 255, 255, 22), outline=(255, 255, 255, 70), width=5
        )
        od.ellipse((700, 900, 1500, 1700), fill=(0, 0, 0, 35), outline=(255, 255, 255, 50), width=4)
        od.rounded_rectangle(
            (70, 1050, 1130, 1510),
            radius=52,
            fill=(0, 0, 0, 125),
            outline=(255, 255, 255, 65),
            width=3,
        )
        img = Image.alpha_composite(img.convert("RGBA"), overlay)
        draw = ImageDraw.Draw(img)
        draw.rounded_rectangle((70, 70, 410, 140), radius=28, fill=secondary)
        draw.text((100, 86), "WORLDCUP AI UNIVERSE", font=self._font(30, True), fill=primary)
        flag = team.flag if team else "AI"
        team_name = team.name if team else "世界杯"
        draw.text((78, 230), flag, font=self._font(110, True), fill="white")
        draw.text((78, 375), team_name, font=self._font(96, True), fill="white")
        wrapped = textwrap.wrap(topic, width=11)[:4]
        y = 540
        for line in wrapped:
            draw.text((78, y), line, font=self._font(82, True), fill="white")
            y += 112
        draw.line((78, 1000, 1120, 1000), fill=secondary, width=10)
        draw.text((105, 1110), "AI FAN POSTER", font=self._font(44, True), fill=secondary)
        draw.text((105, 1195), "为热爱计算  为足球呐喊", font=self._font(54, True), fill="white")
        draw.text(
            (105, 1340),
            datetime.now().strftime("%Y.%m.%d"),
            font=self._font(36),
            fill=(220, 230, 240),
        )
        draw.text(
            (105, 1400),
            "Generated by WorldCup AI Universe",
            font=self._font(29),
            fill=(220, 230, 240),
        )
        out_dir = ROOT_DIR / "storage" / "generated"
        out_dir.mkdir(parents=True, exist_ok=True)
        filename = f"poster_{datetime.now().strftime('%Y%m%d_%H%M%S_%f')}.png"
        img.convert("RGB").save(out_dir / filename, quality=94)
        return f"/static/generated/{filename}"

    async def generate(
        self,
        db: Session,
        topic: str,
        team_name: str = "",
        player: str = "",
        tone: str = "热血",
        generate_image: bool = False,
    ) -> dict:
        subject = " / ".join(x for x in [team_name, player, topic] if x)
        social = f"⚽ {subject}\n今晚，把所有期待交给绿茵场。每一次冲刺、每一次对抗，都在写下属于球迷的世界杯记忆。#世界杯 #WorldCupAI"
        prompt = f"横版世界杯宣传海报，主题“{subject}”，{tone}氛围，现代体育视觉，聚光灯球场，动态足球轨迹，国家队配色，国旗纹理，电影级光影，高细节，留出中文标题区域，无品牌水印"
        script = f"[0-3秒] 球场灯光依次亮起，字幕：{subject}\n[3-10秒] 快切训练、奔跑、球迷挥旗画面，旁白：梦想从来不会自动抵达。\n[10-20秒] 数据图表与关键球员剪影叠加，旁白：速度、策略与勇气，在这一刻汇合。\n[20-28秒] 足球飞向球门，字幕：为热爱，拼到最后一秒。\n[28-30秒] WorldCup AI Universe 标识收尾。"
        slogans = ["为热爱计算，为胜利呐喊", "一座球场，连接全世界", "数据读懂比赛，热爱定义足球"]
        mode = "local"
        llm = get_llm_service()
        result = await llm.complete(
            (
                "你是世界杯体育创意总监。用户已经提供了下面这些字段，字段为空就忽略，不要擅自改成其他球队或示例。"
                "输出严格四段，且每段必须用这些标签开头：朋友圈文案：、海报提示词：、30秒视频脚本：、口号：。"
                f"语言有画面感，避免虚构比赛结果。{PLAIN_TEXT_SYSTEM_INSTRUCTION}"
            ),
            f"主题：{topic}\n球队：{team_name}\n球员：{player}\n语气：{tone}",
            temperature=0.75,
        )
        cleaned_result = clean_ai_text(result)
        if cleaned_result:
            sections = self._split_creative_sections(cleaned_result)
            social = sections.get("social") or social
            prompt = sections.get("poster") or prompt
            script = sections.get("script") or script
            slogans = self._parse_slogans(sections.get("slogans", ""), slogans)
            mode = "ark"
        if generate_image:
            poster_url, image_source, image_error = await self.create_ai_image(prompt)
        else:
            poster_url, image_source, image_error = "", "", "未生成图片：未勾选图片生成，不消耗额度。"
        return {
            "topic": topic,
            "social_copy": social,
            "poster_prompt": prompt,
            "video_script": script,
            "slogans": slogans,
            "model_mode": mode,
            "poster_url": poster_url,
            "image_source": image_source,
            "image_error": image_error,
        }


@lru_cache(maxsize=1)
def get_generation_service() -> GenerationService:
    return GenerationService()
