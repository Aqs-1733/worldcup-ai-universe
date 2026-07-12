from __future__ import annotations

import base64
import io
import logging
from datetime import datetime
from functools import lru_cache
from pathlib import Path
from typing import Any

import cv2
import numpy as np
from PIL import Image

from backend.core.config import ROOT_DIR, get_settings
from backend.services.llm import get_llm_service

logger = logging.getLogger(__name__)

TEAM_COLOR_PROFILES = {
    "法国": [(18, 40, 95), (210, 225, 245), (170, 35, 55)],
    "阿根廷": [(105, 185, 225), (235, 240, 240)],
    "巴西": [(30, 215, 245), (35, 145, 45), (35, 85, 160)],
    "西班牙": [(180, 35, 45), (235, 185, 25)],
    "德国": [(235, 235, 235), (25, 25, 25)],
    "葡萄牙": [(160, 25, 45), (25, 110, 60)],
    "荷兰": [(235, 105, 25), (250, 250, 250)],
    "日本": [(30, 60, 135), (235, 235, 235)],
    "英格兰": [(235, 235, 235), (175, 30, 45)],
    "意大利": [(20, 100, 185), (240, 240, 240)],
    "墨西哥": [(15, 125, 75), (235, 235, 235), (175, 30, 45)],
}

FLAG_PATTERNS = {
    "法国": ["blue", "white", "red"],
    "意大利": ["green", "white", "red"],
    "德国": ["black", "red", "yellow"],
    "荷兰": ["red", "white", "blue"],
    "阿根廷": ["lightblue", "white", "lightblue"],
}


def _rgb_distance(a: tuple[int, int, int], b: tuple[int, int, int]) -> float:
    return float(np.linalg.norm(np.array(a, dtype=float) - np.array(b, dtype=float)))


def _color_name(rgb: tuple[int, int, int]) -> str:
    palette = {
        "black": (20, 20, 20),
        "white": (235, 235, 235),
        "red": (190, 35, 45),
        "yellow": (235, 200, 30),
        "green": (30, 135, 65),
        "blue": (30, 70, 145),
        "lightblue": (115, 190, 225),
        "orange": (235, 110, 25),
    }
    return min(palette, key=lambda name: _rgb_distance(rgb, palette[name]))


class VisionService:
    def __init__(self) -> None:
        self.settings = get_settings()
        self._yolo = None
        self._clip = None
        self._clip_processor = None

    def _decode(self, content: bytes) -> tuple[np.ndarray, Image.Image]:
        if len(content) > self.settings.max_upload_mb * 1024 * 1024:
            raise ValueError(f"图片超过 {self.settings.max_upload_mb}MB 限制")
        pil = Image.open(io.BytesIO(content)).convert("RGB")
        if pil.width < 64 or pil.height < 64:
            raise ValueError("图片尺寸过小，至少需要 64×64")
        array = cv2.cvtColor(np.array(pil), cv2.COLOR_RGB2BGR)
        return array, pil

    def _dominant_colors(
        self, bgr: np.ndarray, k: int = 5
    ) -> list[tuple[tuple[int, int, int], float]]:
        h, w = bgr.shape[:2]
        crop = bgr[int(h * 0.12) : int(h * 0.88), int(w * 0.12) : int(w * 0.88)]
        small = cv2.resize(crop, (120, 120), interpolation=cv2.INTER_AREA)
        rgb = cv2.cvtColor(small, cv2.COLOR_BGR2RGB).reshape((-1, 3)).astype(np.float32)
        criteria = (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 25, 0.5)
        _, labels, centers = cv2.kmeans(rgb, k, None, criteria, 4, cv2.KMEANS_PP_CENTERS)
        counts = np.bincount(labels.flatten(), minlength=k)
        order = np.argsort(counts)[::-1]
        return [(tuple(int(x) for x in centers[i]), float(counts[i] / len(labels))) for i in order]

    def _identify_team(
        self, colors: list[tuple[tuple[int, int, int], float]]
    ) -> tuple[str | None, float]:
        observed = [c for c, ratio in colors if ratio > 0.06]
        scores: dict[str, float] = {}
        for team, refs in TEAM_COLOR_PROFILES.items():
            distances = []
            for ref in refs:
                distances.append(min(_rgb_distance(ref, obs) for obs in observed))
            scores[team] = max(0.0, 1.0 - (sum(distances) / len(distances)) / 220.0)
        if not scores:
            return None, 0.0
        team = max(scores, key=scores.get)
        confidence = scores[team]
        return (team if confidence >= 0.42 else None), confidence

    def _identify_flag(self, bgr: np.ndarray) -> tuple[str | None, float]:
        rgb = cv2.cvtColor(cv2.resize(bgr, (180, 120)), cv2.COLOR_BGR2RGB)
        vertical = []
        for i in range(3):
            strip = rgb[:, i * 60 : (i + 1) * 60]
            vertical.append(_color_name(tuple(np.median(strip.reshape(-1, 3), axis=0).astype(int))))
        horizontal = []
        for i in range(3):
            strip = rgb[i * 40 : (i + 1) * 40, :]
            horizontal.append(
                _color_name(tuple(np.median(strip.reshape(-1, 3), axis=0).astype(int)))
            )
        best_team, best_score = None, 0.0
        for team, pattern in FLAG_PATTERNS.items():
            for observed in [vertical, horizontal]:
                score = sum(a == b for a, b in zip(observed, pattern)) / 3
                if score > best_score:
                    best_team, best_score = team, score
        return (best_team if best_score >= 0.66 else None), best_score

    def _formation(
        self, bgr: np.ndarray
    ) -> tuple[str | None, list[tuple[int, int]], dict[str, Any]]:
        h, w = bgr.shape[:2]
        hsv = cv2.cvtColor(bgr, cv2.COLOR_BGR2HSV)
        green = cv2.inRange(hsv, np.array([30, 35, 25]), np.array([95, 255, 255]))
        field_ratio = float(np.count_nonzero(green) / green.size)
        if field_ratio < 0.18:
            return (
                None,
                [],
                {"field_ratio": round(field_ratio, 3), "reason": "未检测到足够大的绿色比赛区域"},
            )
        # Detect compact, saturated or bright regions that may be player markers/bodies.
        saturation = hsv[:, :, 1]
        value = hsv[:, :, 2]
        candidates = (
            ((saturation > 100) & (value > 70)) | ((saturation < 75) & (value > 185))
        ).astype(np.uint8) * 255
        candidates = cv2.bitwise_and(candidates, green)
        candidates = cv2.morphologyEx(candidates, cv2.MORPH_OPEN, np.ones((3, 3), np.uint8))
        contours, _ = cv2.findContours(candidates, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        points = []
        for contour in contours:
            area = cv2.contourArea(contour)
            x, y, cw, ch = cv2.boundingRect(contour)
            if (
                10 <= area <= max(1800, h * w * 0.006)
                and 2 <= cw <= w * 0.1
                and 2 <= ch <= h * 0.16
            ):
                points.append((x + cw // 2, y + ch // 2))
        # Deduplicate close points
        dedup = []
        for p in sorted(points, key=lambda x: x[1]):
            if all(
                (p[0] - q[0]) ** 2 + (p[1] - q[1]) ** 2 > (min(h, w) * 0.025) ** 2 for q in dedup
            ):
                dedup.append(p)
        points = dedup[:30]
        if len(points) < 7:
            return (
                "阵型识别置信度不足",
                points,
                {"field_ratio": round(field_ratio, 3), "player_candidates": len(points)},
            )
        ys = np.array([[p[1]] for p in points], dtype=np.float32)
        rows = min(4, max(3, len(points) // 4))
        _, labels, centers = cv2.kmeans(
            ys,
            rows,
            None,
            (cv2.TERM_CRITERIA_EPS + cv2.TERM_CRITERIA_MAX_ITER, 30, 1.0),
            10,
            cv2.KMEANS_PP_CENTERS,
        )
        order = np.argsort(centers.flatten())
        counts = [int(np.sum(labels.flatten() == idx)) for idx in order]
        # Remove likely goalkeeper row and normalize plausible outfield counts.
        if counts and counts[0] <= 2:
            counts = counts[1:]
        counts = [max(1, min(5, c)) for c in counts]
        formation = "-".join(map(str, counts))
        common = {"4-3-3", "4-2-3-1", "3-4-3", "3-5-2", "4-4-2", "4-1-4-1", "5-3-2"}
        if formation not in common:
            formation = f"检测行分布 {formation}"
        return (
            formation,
            points,
            {
                "field_ratio": round(field_ratio, 3),
                "player_candidates": len(points),
                "row_counts": counts,
            },
        )

    def _yolo_detect(self, path: Path) -> list[dict]:
        if not self.settings.yolo_model_path:
            return []
        try:
            if self._yolo is None:
                from ultralytics import YOLO

                self._yolo = YOLO(self.settings.yolo_model_path)
            result = self._yolo(str(path), verbose=False)[0]
            detections = []
            for box in result.boxes:
                cls = int(box.cls[0])
                conf = float(box.conf[0])
                xyxy = [round(float(x), 1) for x in box.xyxy[0]]
                detections.append(
                    {
                        "label": result.names.get(cls, str(cls)),
                        "confidence": round(conf, 3),
                        "box": xyxy,
                    }
                )
            return detections
        except Exception as exc:
            logger.warning("YOLO adapter unavailable: %s", exc)
            return []

    def _clip_classify(self, pil: Image.Image) -> dict | None:
        try:
            from transformers import CLIPModel, CLIPProcessor

            if self._clip is None:
                self._clip = CLIPModel.from_pretrained(self.settings.clip_model_name)
                self._clip_processor = CLIPProcessor.from_pretrained(self.settings.clip_model_name)
            labels = [
                f"a football player wearing {name} national team jersey"
                for name in TEAM_COLOR_PROFILES
            ]
            inputs = self._clip_processor(
                text=labels, images=pil, return_tensors="pt", padding=True
            )
            outputs = self._clip(**inputs)
            probs = outputs.logits_per_image.softmax(dim=1)[0]
            idx = int(probs.argmax())
            return {
                "team": list(TEAM_COLOR_PROFILES)[idx],
                "confidence": round(float(probs[idx]), 3),
            }
        except Exception as exc:
            logger.info("CLIP optional adapter not active: %s", exc)
            return None

    def _annotate(
        self, bgr: np.ndarray, points: list[tuple[int, int]], formation: str | None
    ) -> str:
        canvas = bgr.copy()
        for idx, (x, y) in enumerate(points):
            cv2.circle(canvas, (x, y), 10, (0, 255, 255), 2)
            cv2.putText(
                canvas,
                str(idx + 1),
                (x + 8, y - 8),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.45,
                (0, 255, 255),
                1,
                cv2.LINE_AA,
            )
        if formation:
            cv2.rectangle(canvas, (15, 15), (min(canvas.shape[1] - 15, 470), 70), (10, 20, 30), -1)
            cv2.putText(
                canvas,
                f"Formation: {formation}",
                (28, 52),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.8,
                (255, 255, 255),
                2,
                cv2.LINE_AA,
            )
        out_dir = ROOT_DIR / "storage" / "analysis"
        out_dir.mkdir(parents=True, exist_ok=True)
        filename = f"analysis_{datetime.now().strftime('%Y%m%d_%H%M%S_%f')}.jpg"
        cv2.imwrite(str(out_dir / filename), canvas, [cv2.IMWRITE_JPEG_QUALITY, 92])
        return f"/static/analysis/{filename}"

    async def analyze(self, content: bytes, filename: str, analysis_type: str = "auto") -> dict:
        bgr, pil = self._decode(content)
        suffix = Path(filename).suffix.lower() if Path(filename).suffix else ".jpg"
        upload_dir = ROOT_DIR / "storage" / "uploads"
        upload_dir.mkdir(parents=True, exist_ok=True)
        stored = upload_dir / f"{datetime.now().strftime('%Y%m%d_%H%M%S_%f')}{suffix}"
        pil.save(stored)
        colors = self._dominant_colors(bgr)
        team, team_conf = self._identify_team(colors)
        flag, flag_conf = self._identify_flag(bgr)
        formation, points, formation_meta = self._formation(bgr)
        clip_result = (
            self._clip_classify(pil)
            if analysis_type in {"auto", "jersey", "clip"}
            and self.settings.vision_model.lower() == "clip"
            else None
        )
        if clip_result and clip_result["confidence"] > team_conf:
            team, team_conf = clip_result["team"], clip_result["confidence"]
        yolo = self._yolo_detect(stored)
        observations = [
            f"主色：{', '.join(f'{rgb} {round(ratio * 100)}%' for rgb, ratio in colors[:4])}"
        ]
        if team:
            observations.append(f"球衣配色最接近{team}国家队")
        if flag:
            observations.append(f"检测到与{flag}国旗相符的色带结构")
        if formation:
            observations.append(f"比赛区域阵型结果：{formation}")
        if yolo:
            observations.append(f"YOLO检测到 {len(yolo)} 个目标")
        tactical = {
            "strengths": [],
            "risks": [],
            "details": formation_meta,
            "yolo_detections": yolo[:30],
            "clip_result": clip_result,
        }
        if formation and "4-3-3" in formation:
            tactical["strengths"] = ["边路宽度充足", "前场压迫可形成三人第一线"]
            tactical["risks"] = ["单后腰两侧空间需要保护", "边后卫同时前插时容易被反击"]
        elif formation and "4-2-3-1" in formation:
            tactical["strengths"] = ["双后腰保护中路", "前腰可连接边锋与中锋"]
            tactical["risks"] = ["中锋可能孤立", "双后腰过深会削弱前场人数"]
        elif points:
            tactical["strengths"] = ["已识别球员候选点，可用于纵向层次观察"]
            tactical["risks"] = ["截图视角、遮挡和队服相似会影响自动阵型判断"]
        else:
            tactical["risks"] = ["缺少清晰全场视角，无法可靠判断阵型"]
        annotated = self._annotate(bgr, points, formation)
        confidence = max(
            team_conf, flag_conf, min(0.92, 0.45 + len(points) * 0.025 if points else 0.35)
        )
        mode = "opencv"
        llm_text = None
        if get_llm_service().enabled:
            buffer = io.BytesIO()
            pil.thumbnail((1400, 1400))
            pil.save(buffer, format="JPEG", quality=88)
            data_url = "data:image/jpeg;base64," + base64.b64encode(buffer.getvalue()).decode()
            llm_text = await get_llm_service().analyze_image(
                data_url,
                "识别可能的国家队球衣或国旗；若为比赛截图，判断阵型、空间结构、优势和风险。请明确不确定性。",
            )
            if llm_text:
                mode = "opencv+ark-vision"
                observations.append("视觉大模型补充：" + llm_text[:600])
        return {
            "analysis_type": analysis_type,
            "detected_team": team,
            "detected_flag": flag,
            "formation": formation,
            "confidence": round(float(confidence), 3),
            "observations": observations,
            "tactical_analysis": tactical,
            "model_mode": mode,
            "annotated_image_url": annotated,
        }


@lru_cache(maxsize=1)
def get_vision_service() -> VisionService:
    return VisionService()
