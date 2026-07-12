from __future__ import annotations

import re
import unicodedata
from dataclasses import dataclass
from datetime import date, datetime
from functools import lru_cache
from pathlib import Path

import httpx
from pypdf import PdfReader
from sqlalchemy import delete, select
from sqlalchemy.orm import Session

from backend.core.config import ROOT_DIR
from backend.core.models import Player, Team

FIFA_SQUAD_LIST_URL = "https://fdp.fifa.org/assetspublic/ce281/pdf/SquadLists-English.pdf"
POSITION_MAP = {"GK": "门将", "DF": "后卫", "MF": "中场", "FW": "前锋"}

COMMON_CHINESE_NAMES: list[tuple[str, str]] = [
    ("lionel messi", "利昂内尔·梅西"),
    ("messi", "利昂内尔·梅西"),
    ("cristiano ronaldo", "克里斯蒂亚诺·罗纳尔多"),
    ("kylian mbappe", "基利安·姆巴佩"),
    ("erling haaland", "埃尔林·哈兰德"),
    ("haaland", "埃尔林·哈兰德"),
    ("jude bellingham", "裘德·贝林厄姆"),
    ("lamine yamal", "拉明·亚马尔"),
    ("harry kane", "哈里·凯恩"),
    ("kane", "哈里·凯恩"),
    ("vinicius junior", "维尼修斯·儒尼奥尔"),
    ("neymar junior", "内马尔"),
    ("neymar", "内马尔"),
    ("rodrygo", "罗德里戈"),
    ("raphinha", "拉菲尼亚"),
    ("casemiro", "卡塞米罗"),
    ("alisson", "阿利松"),
    ("ederson", "埃德森"),
    ("marquinhos", "马尔基尼奥斯"),
    ("eder militao", "埃德尔·米利唐"),
    ("bruno fernandes", "布鲁诺·费尔南德斯"),
    ("bernardo silva", "贝尔纳多·席尔瓦"),
    ("joao cancelo", "若昂·坎塞洛"),
    ("ruben dias", "鲁本·迪亚斯"),
    ("joao felix", "若昂·菲利克斯"),
    ("diogo jota", "迪奥戈·若塔"),
    ("pedri", "佩德里"),
    ("gavi", "加维"),
    ("rodri", "罗德里"),
    ("rodrigo hernandez", "罗德里"),
    ("mikel merino", "米克尔·梅里诺"),
    ("nico williams", "尼科·威廉姆斯"),
    ("dani olmo", "达尼·奥尔莫"),
    ("alvaro morata", "阿尔瓦罗·莫拉塔"),
    ("ferran torres", "费兰·托雷斯"),
    ("unai simon", "乌奈·西蒙"),
    ("antoine griezmann", "安托万·格列兹曼"),
    ("ousmane dembele", "奥斯曼·登贝莱"),
    ("eduardo camavinga", "爱德华多·卡马文加"),
    ("aurelien tchouameni", "奥雷利安·楚阿梅尼"),
    ("jules kounde", "儒勒·孔德"),
    ("theo hernandez", "特奥·埃尔南德斯"),
    ("mike maignan", "迈克·迈尼昂"),
    ("adrien rabiot", "阿德里安·拉比奥"),
    ("marcus thuram", "马库斯·图拉姆"),
    ("phil foden", "菲尔·福登"),
    ("bukayo saka", "布卡约·萨卡"),
    ("declan rice", "德克兰·赖斯"),
    ("cole palmer", "科尔·帕尔默"),
    ("trent alexander-arnold", "特伦特·亚历山大-阿诺德"),
    ("marcus rashford", "马库斯·拉什福德"),
    ("kevin de bruyne", "凯文·德布劳内"),
    ("romelu lukaku", "罗梅卢·卢卡库"),
    ("jeremy doku", "杰里米·多库"),
    ("thibaut courtois", "蒂博·库尔图瓦"),
    ("achraf hakimi", "阿什拉夫·哈基米"),
    ("yassine bounou", "亚辛·布努"),
    ("hakim ziyech", "哈基姆·齐耶赫"),
    ("sofyan amrabat", "索菲扬·阿姆拉巴特"),
    ("youssef en-nesyri", "优素福·恩内斯里"),
    ("martin odegaard", "马丁·厄德高"),
    ("granit xhaka", "格拉尼特·扎卡"),
    ("xherdan shaqiri", "谢尔丹·沙奇里"),
    ("manuel akanji", "曼努埃尔·阿坎吉"),
    ("christian pulisic", "克里斯蒂安·普利西奇"),
    ("weston mckennie", "韦斯顿·麦肯尼"),
    ("giovanni reyna", "乔瓦尼·雷纳"),
    ("son heung min", "孙兴慜"),
    ("kim min jae", "金玟哉"),
    ("takefusa kubo", "久保建英"),
    ("kaoru mitoma", "三笘薰"),
    ("wataru endo", "远藤航"),
    ("riyad mahrez", "里亚德·马赫雷斯"),
    ("mohamed salah", "穆罕默德·萨拉赫"),
    ("salah", "穆罕默德·萨拉赫"),
    ("lautaro martinez", "劳塔罗·马丁内斯"),
    ("julian alvarez", "胡利安·阿尔瓦雷斯"),
    ("emiliano martinez", "埃米利亚诺·马丁内斯"),
    ("enzo fernandez", "恩佐·费尔南德斯"),
    ("rodrigo de paul", "罗德里戈·德保罗"),
    ("alexis mac allister", "亚历克西斯·麦卡利斯特"),
    ("son", "孙兴慜"),
]

FAME_FRAGMENTS = [item[0] for item in COMMON_CHINESE_NAMES]
CANONICAL_ENGLISH_NAMES: list[tuple[str, str]] = [
    ("cristiano ronaldo", "Cristiano Ronaldo"),
    ("kylian mbappe", "Kylian Mbappe"),
    ("lamine yamal", "Lamine Yamal"),
    ("lamine yamal yamal", "Lamine Yamal"),
    ("raphael raphinha", "Raphinha"),
    ("raphinha", "Raphinha"),
    ("casemiroc", "Casemiro"),
    ("casemiro", "Casemiro"),
    ("alisson", "Alisson"),
    ("edersone", "Ederson"),
    ("ederson", "Ederson"),
    ("marquinhosm", "Marquinhos"),
    ("marquinhos", "Marquinhos"),
    ("bernardo bernardo silva", "Bernardo Silva"),
    ("bernardo silva", "Bernardo Silva"),
    ("joao pedro joao cancelo", "Joao Cancelo"),
    ("joao cancelo", "Joao Cancelo"),
    ("ruben ruben dias", "Ruben Dias"),
    ("ruben dias", "Ruben Dias"),
    ("joao joao felix", "Joao Felix"),
    ("joao felix", "Joao Felix"),
    ("pedro pedri", "Pedri"),
    ("pedri", "Pedri"),
    ("pablo gavi", "Gavi"),
    ("gavi", "Gavi"),
    ("rodrigo rodri", "Rodri"),
    ("mikel merino", "Mikel Merino"),
    ("nico williams", "Nico Williams"),
    ("dani olmo", "Dani Olmo"),
    ("ferran torres", "Ferran Torres"),
    ("unai simon", "Unai Simon"),
    ("lionel messi", "Lionel Messi"),
    ("lionel andres messi", "Lionel Messi"),
    ("messi", "Lionel Messi"),
    ("erling haaland", "Erling Haaland"),
    ("erling braut haaland", "Erling Haaland"),
    ("haaland", "Erling Haaland"),
    ("jude bellingham", "Jude Bellingham"),
    ("harry kane", "Harry Kane"),
    ("harry edward kane", "Harry Kane"),
    ("kane", "Harry Kane"),
    ("vinicius junior", "Vinicius Junior"),
    ("neymar neymar jr", "Neymar Junior"),
    ("neymar", "Neymar Junior"),
    ("bruno fernandes", "Bruno Fernandes"),
    ("kevin de bruyne", "Kevin De Bruyne"),
    ("romelu lukaku", "Romelu Lukaku"),
    ("achraf hakimi", "Achraf Hakimi"),
    ("yassine bounou", "Yassine Bounou"),
    ("martin odegaard", "Martin Odegaard"),
    ("mohamed salah mohamed salah", "Mohamed Salah"),
    ("mohamed salah", "Mohamed Salah"),
    ("salah", "Mohamed Salah"),
    ("heungmin min son", "Son Heung-min"),
    ("son", "Son Heung-min"),
]


@dataclass(frozen=True)
class OfficialPlayerRow:
    team_code: str
    team_name_en: str
    name: str
    position_code: str
    dob: date
    club: str
    height_cm: int | None
    caps: int
    goals: int


def _clean(text: str) -> str:
    return re.sub(r"\s+", " ", text.replace("\x00", "")).strip()


def _ascii_key(text: str) -> str:
    normalized = unicodedata.normalize("NFKD", text)
    return normalized.encode("ascii", "ignore").decode("ascii").lower()


def _slugify(text: str) -> str:
    value = re.sub(r"[^a-z0-9]+", "-", _ascii_key(text)).strip("-")
    return value or "player"


def _contains_fragment(key: str, fragment: str) -> bool:
    return bool(re.search(rf"(?<![a-z0-9]){re.escape(fragment)}(?![a-z0-9])", key))


def canonical_english_name(name: str) -> str:
    key = _ascii_key(name)
    for fragment, canonical in CANONICAL_ENGLISH_NAMES:
        if _contains_fragment(key, fragment) or fragment in key:
            return canonical
    return name


def common_chinese_name(name: str) -> str:
    key = _ascii_key(name)
    for fragment, chinese in COMMON_CHINESE_NAMES:
        if _contains_fragment(key, fragment):
            return chinese
    return name


def player_fame_rank(name: str, english_name: str = "") -> int:
    key = _ascii_key(f"{name} {english_name}")
    for index, fragment in enumerate(FAME_FRAGMENTS):
        if _contains_fragment(key, fragment):
            return index
    return 10_000


def _split_caps_goals(raw: str) -> tuple[int, int]:
    parts = raw.strip().split()
    if len(parts) >= 2:
        return int(parts[-2]), int(parts[-1])
    digits = re.sub(r"\D", "", raw)
    if not digits:
        return 0, 0
    best: tuple[int, int] | None = None
    for index in range(1, len(digits)):
        caps = int(digits[:index])
        goals = int(digits[index:])
        if caps <= 260 and goals <= 160 and goals <= max(160, caps + 20):
            best = (caps, goals)
    return best or (int(digits), 0)


def _given_tokens(rest: str) -> str:
    tokens: list[str] = []
    for token in rest.split():
        if re.match(r"^[A-ZÀ-Þ][A-ZÀ-Þ'’.-]+$", token):
            break
        match = re.match(r"^([A-ZÀ-Þ][a-zà-ÿ'’.-]+)", token)
        if not match:
            break
        value = match.group(1)
        middle = len(value) // 2
        if len(value) % 2 == 0 and value[:middle].lower() == value[middle:].lower():
            value = value[:middle]
        if tokens and _ascii_key(tokens[-1]) == _ascii_key(value):
            continue
        tokens.append(value)
        if len(tokens) >= 2:
            break
    return " ".join(tokens)


def _format_surname(value: str) -> str:
    if re.match(r"^Mc[A-ZÀ-Þ]", value):
        return "Mc" + value[2:].title()
    return value.title()


def _parse_player_name(body: str) -> str:
    body = _clean(body)
    mc_match = re.match(r"^(Mc[A-ZÀ-Þ][A-ZÀ-Þ'’.-]*)\s+(.+)$", body)
    if mc_match:
        last_name = _format_surname(mc_match.group(1))
        given = _given_tokens(mc_match.group(2))
        return _clean(f"{given} {last_name}") if given else last_name
    first_lower = re.search(r"[a-zà-ÿ]", body)
    if not first_lower:
        return body.title()
    prefix = body[: first_lower.start()]
    last_name = prefix.rsplit(" ", 1)[0].strip() if " " in prefix else prefix.strip()
    rest = body[len(last_name) :].strip()
    given = _given_tokens(rest)
    return _clean(f"{given} {_format_surname(last_name)}") if given else _format_surname(last_name)


def _age_from_dob(dob: date) -> int:
    today = date.today()
    return today.year - dob.year - ((today.month, today.day) < (dob.month, dob.day))


class PlayerSyncService:
    async def _download_pdf(self) -> Path:
        cache_dir = ROOT_DIR / "storage" / "source_cache"
        cache_dir.mkdir(parents=True, exist_ok=True)
        target = cache_dir / "fifa_squadlists_2026.pdf"
        headers = {"User-Agent": "WorldCupAIUniverse/1.0"}
        async with httpx.AsyncClient(
            headers=headers,
            timeout=httpx.Timeout(60.0, connect=10.0),
            follow_redirects=True,
            trust_env=True,
        ) as client:
            response = await client.get(FIFA_SQUAD_LIST_URL)
            response.raise_for_status()
        target.write_bytes(response.content)
        return target

    def parse_pdf(self, path: Path) -> list[OfficialPlayerRow]:
        reader = PdfReader(str(path))
        rows: list[OfficialPlayerRow] = []
        for page in reader.pages:
            text = page.extract_text() or ""
            lines = [_clean(line) for line in text.splitlines() if line.strip()]
            if not lines:
                continue
            team_match = re.match(r"(.+) \(([A-Z]{3})\)", lines[0])
            if not team_match:
                continue
            team_name_en, team_code = team_match.groups()
            for line in lines:
                if not re.match(r"^(GK|DF|MF|FW)", line):
                    continue
                match = re.match(
                    r"^(GK|DF|MF|FW)(.+?)(\d{2}/\d{2}/\d{4})(.+?)\s+(\d{3})\s+([\d\s]+)$",
                    line,
                )
                if not match:
                    continue
                position, name_blob, dob_text, club, height, caps_goals = match.groups()
                caps, goals = _split_caps_goals(caps_goals)
                rows.append(
                    OfficialPlayerRow(
                        team_code=team_code,
                        team_name_en=team_name_en,
                        name=_parse_player_name(name_blob),
                        position_code=position,
                        dob=datetime.strptime(dob_text, "%d/%m/%Y").date(),
                        club=_clean(club),
                        height_cm=int(height) if height else None,
                        caps=caps,
                        goals=goals,
                    )
                )
        return rows

    async def refresh_from_fifa(self, db: Session) -> dict:
        path = await self._download_pdf()
        rows = self.parse_pdf(path)
        teams = {team.country_code: team for team in db.scalars(select(Team)).all()}
        db.execute(delete(Player).where(Player.is_legend.is_(False)))
        db.commit()
        updated = 0
        inserted = 0
        skipped = 0
        used_slugs = set(db.scalars(select(Player.slug)).all())
        for row in rows:
            team = teams.get(row.team_code)
            if not team:
                skipped += 1
                continue
            english_name = canonical_english_name(row.name)
            chinese_name = common_chinese_name(english_name)
            slug_base = f"{team.slug}-{_slugify(english_name)}"
            slug = slug_base
            suffix = 2
            while slug in used_slugs:
                existing_for_slug = db.scalar(select(Player).where(Player.slug == slug))
                if existing_for_slug and existing_for_slug.team_id == team.id and existing_for_slug.english_name == english_name:
                    break
                slug = f"{slug_base}-{suffix}"
                suffix += 1
            existing = db.scalar(select(Player).where(Player.slug == slug))
            if existing is None:
                existing = db.scalar(
                    select(Player).where(Player.team_id == team.id, Player.english_name == english_name)
                )
            if existing is None:
                existing = Player(slug=slug, name=chinese_name, english_name=english_name)
                db.add(existing)
                inserted += 1
                used_slugs.add(slug)
            else:
                updated += 1
            existing.team_id = team.id
            existing.name = chinese_name
            existing.english_name = english_name
            existing.country = team.name
            existing.flag = team.flag
            existing.position = POSITION_MAP.get(row.position_code, row.position_code)
            existing.club = row.club
            existing.number = None
            existing.age = _age_from_dob(row.dob)
            existing.is_legend = False
            existing.career = (
                f"来源：FIFA 2026 SquadLists-English.pdf。"
                f"生日 {row.dob.isoformat()}；身高 {row.height_cm or ''}cm；"
                f"国家队出场 {row.caps}，进球 {row.goals}。"
            )
            existing.world_cup_appearances = row.caps
            existing.world_cup_goals = row.goals
            existing.world_cup_assists = 0
            existing.rating = 0.0
            existing.form_score = 0.0
            existing.strengths = []
            existing.weaknesses = []
            existing.impact = "FIFA官方名单球员；本站不补写未公开的状态评分或技术标签。"
            existing.avatar_url = ""
        db.commit()
        return {
            "source": FIFA_SQUAD_LIST_URL,
            "parsed": len(rows),
            "inserted": inserted,
            "updated": updated,
            "skipped": skipped,
        }


@lru_cache(maxsize=1)
def get_player_sync_service() -> PlayerSyncService:
    return PlayerSyncService()
