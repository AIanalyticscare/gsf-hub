from __future__ import annotations

from dataclasses import asdict, dataclass, field
from pathlib import Path
from typing import Any


@dataclass(slots=True)
class SourceReference:
    document: str
    url: str | None = None
    page: int | None = None
    sheet: str | None = None
    row: int | None = None
    paragraph: int | None = None
    quote: str = ""

    def to_dict(self) -> dict[str, Any]:
        return {key: value for key, value in asdict(self).items() if value not in (None, "")}


@dataclass(slots=True)
class EvidenceChunk:
    chunk_id: str
    resource_id: str
    text: str
    source: SourceReference

    def to_dict(self) -> dict[str, Any]:
        return {
            "chunk_id": self.chunk_id,
            "resource_id": self.resource_id,
            "text": self.text,
            "source": self.source.to_dict(),
        }


@dataclass(slots=True)
class Resource:
    resource_id: str
    resource_name: str
    description: str = ""
    resource_type: str = ""
    target_users: list[str] = field(default_factory=list)
    eligible_islands: list[str] = field(default_factory=list)
    sectors: list[str] = field(default_factory=list)
    needs_addressed: list[str] = field(default_factory=list)
    eligibility_rules: list[str] = field(default_factory=list)
    financial_support: str = ""
    application_deadline: str = ""
    contact_information: str = ""
    next_steps: list[str] = field(default_factory=list)
    updated_at: str = ""
    data_status: str = ""
    evidence: list[EvidenceChunk] = field(default_factory=list)

    def searchable_text(self) -> str:
        values = [
            self.resource_name,
            self.description,
            self.resource_type,
            *self.target_users,
            *self.eligible_islands,
            *self.sectors,
            *self.needs_addressed,
            *self.eligibility_rules,
            self.financial_support,
        ]
        return " ".join(value for value in values if value)

    def to_dict(self) -> dict[str, Any]:
        result = asdict(self)
        result["evidence"] = [chunk.to_dict() for chunk in self.evidence]
        return result

    @classmethod
    def from_dict(cls, data: dict[str, Any]) -> "Resource":
        evidence = []
        for item in data.get("evidence", []):
            source = SourceReference(**item["source"])
            evidence.append(
                EvidenceChunk(
                    chunk_id=item["chunk_id"],
                    resource_id=item["resource_id"],
                    text=item["text"],
                    source=source,
                )
            )
        fields = {key: value for key, value in data.items() if key != "evidence"}
        return cls(**fields, evidence=evidence)


@dataclass(slots=True)
class Library:
    resources: list[Resource]
    warnings: list[str] = field(default_factory=list)
    source_root: str = ""

    def to_dict(self) -> dict[str, Any]:
        return {
            "schema_version": "0.1",
            "source_root": self.source_root,
            "warnings": self.warnings,
            "resources": [resource.to_dict() for resource in self.resources],
        }

    @classmethod
    def from_dict(cls, data: dict[str, Any]) -> "Library":
        return cls(
            resources=[Resource.from_dict(item) for item in data.get("resources", [])],
            warnings=list(data.get("warnings", [])),
            source_root=data.get("source_root", ""),
        )


@dataclass(slots=True)
class UserNeed:
    situation: str
    location: str = ""
    sector: str = ""
    needs: list[str] = field(default_factory=list)
    constraints: list[str] = field(default_factory=list)
    user_type: str = ""

    def query_text(self) -> str:
        return " ".join(
            value
            for value in [
                self.situation,
                self.location,
                self.sector,
                " ".join(self.needs),
                " ".join(self.constraints),
                self.user_type,
            ]
            if value
        )

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(slots=True)
class Recommendation:
    resource: str
    resource_type: str
    summary: str
    fit_score: float
    eligibility_status: str
    why_it_fits: list[str]
    possible_barriers: list[str]
    eligibility: list[str]
    financial_support: str
    application_deadline: str
    contact_information: str
    next_steps: list[str]
    uncertainties: list[str]
    sources: list[dict[str, Any]]
    score_breakdown: dict[str, float]

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(slots=True)
class RecommendationResult:
    query: UserNeed
    recommendations: list[Recommendation]
    warnings: list[str] = field(default_factory=list)

    def to_dict(self) -> dict[str, Any]:
        return {
            "query": self.query.to_dict(),
            "recommendations": [item.to_dict() for item in self.recommendations],
            "warnings": self.warnings,
        }


def relative_document(path: Path, root: Path) -> str:
    try:
        return str(path.resolve().relative_to(root.resolve()))
    except ValueError:
        return path.name
