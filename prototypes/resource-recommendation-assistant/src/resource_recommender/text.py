from __future__ import annotations

import math
import re
import unicodedata
from collections import Counter
from datetime import date, datetime
from typing import Iterable


TOKEN_PATTERN = re.compile(r"[a-z0-9]+")

STOP_WORDS = {
    "a",
    "an",
    "and",
    "are",
    "as",
    "at",
    "be",
    "but",
    "by",
    "for",
    "from",
    "i",
    "in",
    "is",
    "it",
    "my",
    "need",
    "of",
    "on",
    "or",
    "our",
    "the",
    "their",
    "to",
    "we",
    "with",
}

# This small, inspectable vocabulary improves the offline prototype without
# pretending to be a full embedding model. Production can replace the retriever.
CONCEPT_GROUPS = [
    {"hurricane", "storm", "cyclone", "disaster", "resilience", "resilient"},
    {"tourism", "hospitality", "visitor", "hotel", "guesthouse"},
    {"business", "enterprise", "company", "sme", "entrepreneur"},
    {"equipment", "retrofit", "shutter", "generator", "protection"},
    {"climate", "adaptation", "resilience", "hazard"},
    {"fund", "funding", "finance", "financial", "grant"},
    {"training", "workshop", "course", "capacity", "learning"},
    {"coastal", "mangrove", "shoreline", "marine"},
    {"conservation", "biodiversity", "ecosystem", "environmental"},
]

SYNONYMS: dict[str, set[str]] = {}
for group in CONCEPT_GROUPS:
    for token in group:
        SYNONYMS[token] = group - {token}

SYNONYMS["nctf"] = {"ctf", "conservation", "trust", "fund"}
SYNONYMS["ctf"] = {"nctf", "conservation", "trust", "fund"}


def normalize(value: str) -> str:
    value = unicodedata.normalize("NFKD", value or "")
    value = "".join(char for char in value if not unicodedata.combining(char))
    return " ".join(TOKEN_PATTERN.findall(value.lower()))


def tokenize(value: str, *, expand: bool = True) -> list[str]:
    tokens = [_stem(token) for token in TOKEN_PATTERN.findall(normalize(value)) if token not in STOP_WORDS]
    if not expand:
        return tokens
    expanded = list(tokens)
    for token in tokens:
        expanded.extend(sorted(SYNONYMS.get(token, ())))
    return expanded


def _stem(token: str) -> str:
    """Normalize a few common English plurals without a black-box stemmer."""
    if len(token) > 5 and token.endswith("ies"):
        return token[:-3] + "y"
    if len(token) > 5 and token.endswith("sses"):
        return token[:-2]
    if len(token) > 4 and token.endswith("s") and not token.endswith("ss"):
        return token[:-1]
    return token


def split_values(value: object) -> list[str]:
    if value is None:
        return []
    if isinstance(value, (list, tuple, set)):
        parts = [str(item).strip() for item in value]
    else:
        parts = re.split(r"\s*(?:\||;|\n)\s*", str(value).strip())
    return [part for part in parts if part]


def slugify(value: str) -> str:
    slug = "-".join(TOKEN_PATTERN.findall(normalize(value)))
    return slug or "resource"


def trim_quote(value: str, limit: int = 280) -> str:
    compact = " ".join((value or "").split())
    if len(compact) <= limit:
        return compact
    return compact[: limit - 1].rstrip() + "…"


def cosine(left: Counter[str], right: Counter[str], idf: dict[str, float]) -> float:
    common = set(left) & set(right)
    numerator = sum(left[token] * right[token] * idf.get(token, 1.0) ** 2 for token in common)
    left_norm = math.sqrt(sum((count * idf.get(token, 1.0)) ** 2 for token, count in left.items()))
    right_norm = math.sqrt(sum((count * idf.get(token, 1.0)) ** 2 for token, count in right.items()))
    if not left_norm or not right_norm:
        return 0.0
    return numerator / (left_norm * right_norm)


def overlap_score(query: str, values: Iterable[str]) -> float:
    query_tokens = set(tokenize(query))
    value_tokens = set(tokenize(" ".join(values)))
    if not query_tokens or not value_tokens:
        return 0.0
    intersection = query_tokens & value_tokens
    return min(1.0, len(intersection) / max(1, min(len(query_tokens), len(value_tokens))))


def parse_date(value: str) -> date | None:
    clean = (value or "").strip()
    if not clean:
        return None
    for pattern in ("%Y-%m-%d", "%Y/%m/%d", "%m/%d/%Y", "%d/%m/%Y", "%B %d, %Y", "%b %d, %Y"):
        try:
            return datetime.strptime(clean, pattern).date()
        except ValueError:
            continue
    return None
