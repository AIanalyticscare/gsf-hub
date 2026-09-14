from __future__ import annotations

import math
from collections import Counter
from dataclasses import dataclass

from .models import EvidenceChunk, Resource
from .text import cosine, tokenize


@dataclass(slots=True)
class RetrievalHit:
    resource: Resource
    score: float
    chunks: list[EvidenceChunk]
    matched_terms: list[str]


class LocalTfidfRetriever:
    """Small dependency-free retriever for a transparent first prototype.

    Concept expansion in ``text.py`` lets related terms such as hurricane,
    storm, and resilience retrieve one another. This is intentionally easy to
    audit. A production system can replace this class with an embedding store
    while preserving the recommendation and evidence interfaces.
    """

    def __init__(self, resources: list[Resource]):
        self.resources = resources
        self._chunks: list[tuple[Resource, EvidenceChunk, Counter[str]]] = []
        for resource in resources:
            evidence = resource.evidence or []
            if not evidence:
                continue
            for chunk in evidence:
                searchable = f"{resource.searchable_text()} {chunk.text}"
                self._chunks.append((resource, chunk, Counter(tokenize(searchable))))

        document_frequency: Counter[str] = Counter()
        for _, _, tokens in self._chunks:
            document_frequency.update(tokens.keys())
        size = max(1, len(self._chunks))
        self._idf = {
            token: math.log((size + 1) / (frequency + 1)) + 1
            for token, frequency in document_frequency.items()
        }

    def search(self, query: str, *, limit: int | None = None, chunks_per_resource: int = 3) -> list[RetrievalHit]:
        query_tokens = Counter(tokenize(query))
        grouped: dict[str, list[tuple[float, Resource, EvidenceChunk, set[str]]]] = {}
        for resource, chunk, tokens in self._chunks:
            score = cosine(query_tokens, tokens, self._idf)
            matches = set(query_tokens) & set(tokens)
            grouped.setdefault(resource.resource_id, []).append((score, resource, chunk, matches))

        hits = []
        for candidates in grouped.values():
            candidates.sort(key=lambda item: item[0], reverse=True)
            selected = candidates[:chunks_per_resource]
            best = selected[0]
            # Reward a second corroborating passage without allowing long
            # documents to dominate solely because they have more chunks.
            score = best[0] + sum(item[0] for item in selected[1:]) * 0.15
            hits.append(
                RetrievalHit(
                    resource=best[1],
                    score=min(1.0, score),
                    chunks=[item[2] for item in selected if item[0] > 0] or [best[2]],
                    matched_terms=sorted(set().union(*(item[3] for item in selected))),
                )
            )
        hits.sort(key=lambda item: item.score, reverse=True)
        return hits[:limit] if limit else hits

