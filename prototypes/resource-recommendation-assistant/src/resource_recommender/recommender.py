from __future__ import annotations

from datetime import date
from typing import Iterable

from .models import Library, Recommendation, RecommendationResult, Resource, UserNeed
from .retrieval import LocalTfidfRetriever, RetrievalHit
from .text import normalize, overlap_score, parse_date, tokenize, trim_quote


LOCATION_PARENTS = {
    "eleuthera": "bahamas",
    "new providence": "bahamas",
    "grand bahama": "bahamas",
    "abaco": "bahamas",
    "andros": "bahamas",
    "exuma": "bahamas",
    "long island": "bahamas",
    "cat island": "bahamas",
    "bimini": "bahamas",
}

ROLLING_TERMS = {"rolling", "ongoing", "open", "no fixed deadline"}


def _contains_phrase(text: str, options: Iterable[str]) -> str:
    normalized = f" {normalize(text)} "
    text_tokens = set(tokenize(text, expand=False))
    candidates = sorted({item for item in options if item}, key=len, reverse=True)
    for option in candidates:
        if f" {normalize(option)} " in normalized:
            return option
    for option in candidates:
        option_tokens = set(tokenize(option, expand=False))
        if len(option_tokens) > 1 and option_tokens.issubset(text_tokens):
            return option
    return ""


def _location_match_score(user_location: str, eligible_location: str) -> float:
    user = normalize(user_location)
    eligible = normalize(eligible_location)
    if not user or not eligible:
        return 0.0
    if user == eligible or user in eligible or eligible in user:
        return 1.0
    if LOCATION_PARENTS.get(user) == eligible or LOCATION_PARENTS.get(eligible) == user:
        return 0.85
    return 0.0


def _match_list(query: str, values: list[str]) -> tuple[float, list[str]]:
    if not query or not values:
        return 0.5, []
    exact = [value for value in values if normalize(value) in normalize(query) or normalize(query) in normalize(value)]
    if exact:
        return 1.0, exact
    score = overlap_score(query, values)
    return score, []


class ResourceRecommender:
    def __init__(self, library: Library, *, today: date | None = None):
        self.library = library
        self.today = today or date.today()
        self.retriever = LocalTfidfRetriever(library.resources)

    def understand(
        self,
        situation: str,
        *,
        location: str = "",
        sector: str = "",
        needs: list[str] | None = None,
        constraints: list[str] | None = None,
        user_type: str = "",
    ) -> UserNeed:
        """Create a structured need, only inferring values present in the library."""
        locations = [item for resource in self.library.resources for item in resource.eligible_islands]
        sectors = [item for resource in self.library.resources for item in resource.sectors]
        targets = [item for resource in self.library.resources for item in resource.target_users]
        supported_needs = [item for resource in self.library.resources for item in resource.needs_addressed]
        inferred_location = location or _contains_phrase(situation, locations)
        inferred_sector = sector or _contains_phrase(situation, sectors)
        inferred_user_type = user_type or _contains_phrase(situation, targets)
        inferred_needs = list(needs or [])
        if not inferred_needs:
            inferred_needs = [
                item
                for item in supported_needs
                if f" {normalize(item)} " in f" {normalize(situation)} "
            ]
            if not inferred_needs:
                inferred_needs = [situation]
        return UserNeed(
            situation=situation,
            location=inferred_location,
            sector=inferred_sector,
            needs=inferred_needs,
            constraints=list(constraints or []),
            user_type=inferred_user_type,
        )

    def recommend(self, need: UserNeed, *, top_k: int = 3, minimum_score: float = 0.05) -> RecommendationResult:
        hits = self.retriever.search(need.query_text())
        recommendations = []
        for hit in hits:
            recommendation = self._score(hit, need)
            if recommendation.fit_score >= minimum_score:
                recommendations.append(recommendation)
        recommendations.sort(key=lambda item: item.fit_score, reverse=True)
        recommendations = _diversify(recommendations, top_k)

        warnings = list(self.library.warnings)
        if not recommendations:
            warnings.append(
                "No sufficiently supported match was found. Add source material or provide more location, sector, and need detail."
            )
        has_application_resources = any(_is_application_resource(resource) for resource in self.library.resources)
        if not need.location and has_application_resources:
            warnings.append("No location was provided or confidently inferred; location eligibility remains uncertain.")
        if not need.sector and has_application_resources:
            warnings.append("No sector was provided or confidently inferred; sector fit remains uncertain.")
        return RecommendationResult(query=need, recommendations=recommendations[:top_k], warnings=warnings)

    def _score(self, hit: RetrievalHit, need: UserNeed) -> Recommendation:
        resource = hit.resource
        application_resource = _is_application_resource(resource)
        reasons: list[str] = []
        barriers: list[str] = []
        uncertainties: list[str] = []

        location_score = 0.5
        location_mismatch = False
        matched_locations: list[str] = []
        if need.location and resource.eligible_islands:
            matched_locations = [
                location
                for location in resource.eligible_islands
                if _location_match_score(need.location, location) > 0
            ]
            location_score = max(
                (_location_match_score(need.location, location) for location in resource.eligible_islands),
                default=0.0,
            )
            location_mismatch = not matched_locations
            if matched_locations:
                if application_resource:
                    reasons.append(f"Available in {need.location}.")
                else:
                    reasons.append(f"Location coverage includes {need.location}.")
            else:
                if application_resource:
                    barriers.append(
                        f"Location mismatch: the record lists {', '.join(resource.eligible_islands)}, not {need.location}."
                    )
                else:
                    location_mismatch = False
        elif need.location and application_resource:
            uncertainties.append("The source does not state eligible islands or locations.")
        elif resource.eligible_islands and application_resource:
            uncertainties.append("User location is unknown, so location eligibility could not be checked.")

        sector_score, matched_sectors = _match_list(need.sector, resource.sectors)
        sector_mismatch = bool(need.sector and resource.sectors and sector_score == 0)
        if matched_sectors:
            reasons.append(f"Relevant to the {need.sector} sector.")
        elif sector_mismatch:
            if application_resource:
                barriers.append(f"Sector mismatch: the record lists {', '.join(resource.sectors)}.")
            else:
                sector_mismatch = False
        elif need.sector and not resource.sectors and application_resource:
            uncertainties.append("The source does not state eligible sectors.")

        need_text = " ".join(need.needs) or need.situation
        needs_score = overlap_score(need_text, resource.needs_addressed or [resource.description])
        if needs_score > 0 and resource.needs_addressed:
            matches = _best_labels(need_text, resource.needs_addressed)
            if matches:
                reasons.append(f"Addresses {', '.join(matches[:3])}.")

        user_score, matched_users = _match_list(
            f"{need.user_type} {need.situation}", resource.target_users
        )
        if matched_users:
            reasons.append(f"Designed for {', '.join(matched_users[:2])}.")
        elif need.user_type and resource.target_users and user_score == 0:
            barriers.append(f"Applicant-type mismatch: the record targets {', '.join(resource.target_users)}.")

        freshness_score, freshness_barrier, freshness_uncertainty = self._freshness(
            resource, application_resource=application_resource
        )
        if freshness_barrier:
            barriers.append(freshness_barrier)
        if freshness_uncertainty:
            uncertainties.append(freshness_uncertainty)

        score_breakdown = {
            "retrieval": round(hit.score, 4),
            "location": round(location_score, 4),
            "sector": round(sector_score, 4),
            "need": round(needs_score, 4),
            "target_user": round(user_score, 4),
            "freshness": round(freshness_score, 4),
        }
        score = (
            hit.score * 0.40
            + location_score * 0.20
            + sector_score * 0.13
            + needs_score * 0.17
            + user_score * 0.05
            + freshness_score * 0.05
        )
        if location_mismatch or sector_mismatch:
            score *= 0.35
        if freshness_score == 0 and parse_date(resource.application_deadline):
            score *= 0.25

        eligibility_status = "needs_verification" if application_resource else "not_applicable"
        if location_mismatch or sector_mismatch or freshness_score == 0:
            eligibility_status = "unlikely_or_ineligible"

        if resource.eligibility_rules:
            barriers.extend(f"Requirement: {rule}" for rule in resource.eligibility_rules)
        elif application_resource:
            uncertainties.append("Eligibility rules are missing from the source record.")
        if not resource.contact_information and application_resource:
            uncertainties.append("Contact information is missing from the source record.")
        if not resource.financial_support and application_resource:
            uncertainties.append("The source does not state whether financial support is included.")
        if resource.data_status and normalize(resource.data_status) not in {
            "verified",
            "current",
            "approved",
            "publish",
            "published",
        }:
            uncertainties.append(f"Data status: {resource.data_status}.")

        next_steps = list(resource.next_steps)
        if not next_steps:
            if application_resource:
                next_steps.append("Review the cited source and confirm the eligibility details.")
            else:
                next_steps.append("Open the cited Hub content and review the full material.")
            if resource.contact_information:
                next_steps.append(f"Contact {resource.contact_information}.")

        sources = []
        seen_sources: set[tuple] = set()
        for chunk in hit.chunks:
            source = chunk.source.to_dict()
            key = (
                source.get("document"),
                source.get("page"),
                source.get("sheet"),
                source.get("row"),
                source.get("paragraph"),
            )
            if key not in seen_sources:
                sources.append(source)
                seen_sources.add(key)

        if not reasons and hit.matched_terms:
            reasons.append(f"Covers related themes: {', '.join(hit.matched_terms[:6])}.")

        return Recommendation(
            resource=resource.resource_name,
            resource_type=resource.resource_type or "Resource",
            summary=trim_quote(resource.description, 360),
            fit_score=round(max(0.0, min(1.0, score)), 4),
            eligibility_status=eligibility_status,
            why_it_fits=reasons,
            possible_barriers=_deduplicate(barriers),
            eligibility=resource.eligibility_rules,
            financial_support=resource.financial_support or "Not stated in the source record",
            application_deadline=resource.application_deadline or "Not stated in the source record",
            contact_information=resource.contact_information or "Not stated in the source record",
            next_steps=next_steps,
            uncertainties=_deduplicate(uncertainties),
            sources=sources,
            score_breakdown=score_breakdown,
        )

    def _freshness(
        self, resource: Resource, *, application_resource: bool
    ) -> tuple[float, str, str]:
        deadline_score = 1.0 if not application_resource else 0.35
        barrier = ""
        notes = []
        deadline = parse_date(resource.application_deadline)
        if deadline:
            if deadline < self.today:
                return 0.0, f"The recorded deadline ({deadline.isoformat()}) has passed.", ""
            deadline_score = 1.0
        else:
            normalized_deadline = normalize(resource.application_deadline)
            if normalized_deadline in ROLLING_TERMS:
                deadline_score = 0.85
                notes.append("Confirm that rolling applications are still open before applying.")
            elif resource.application_deadline:
                deadline_score = 0.4
                notes.append(f"The deadline value could not be interpreted: {resource.application_deadline}.")
            elif application_resource:
                notes.append("No application deadline is stated; current availability must be confirmed.")

        verified_date = parse_date((resource.updated_at or "")[:10])
        if verified_date:
            age_days = (self.today - verified_date).days
            if age_days < -7:
                verification_score = 0.3
                notes.append(f"The last-updated date is in the future: {verified_date.isoformat()}.")
            elif age_days > 365:
                verification_score = 0.35
                notes.append(f"The record was last updated {verified_date.isoformat()} and may be stale.")
            else:
                verification_score = 1.0
        else:
            verification_score = 0.5
            notes.append("No usable last-updated date is present, so freshness could not be fully checked.")
        freshness = deadline_score * 0.7 + verification_score * 0.3
        return freshness, barrier, " ".join(notes)


def _best_labels(query: str, labels: list[str]) -> list[str]:
    query_tokens = set(tokenize(query))
    scored = []
    for label in labels:
        label_tokens = set(tokenize(label))
        overlap = len(query_tokens & label_tokens)
        if overlap:
            scored.append((overlap, label))
    scored.sort(key=lambda item: (-item[0], item[1]))
    return [label for _, label in scored]


def _deduplicate(items: list[str]) -> list[str]:
    seen = set()
    result = []
    for item in items:
        key = normalize(item)
        if key and key not in seen:
            result.append(item)
            seen.add(key)
    return result


def _is_application_resource(resource: Resource) -> bool:
    resource_type = normalize(resource.resource_type)
    return any(
        marker in resource_type
        for marker in ("grant", "fund", "funding", "loan", "programme", "program", "opportunity", "training")
    )


def _content_group(resource_type: str) -> str:
    normalized = normalize(resource_type)
    if normalized.startswith("data centre") or normalized == "data story":
        return "data_centre"
    if normalized.startswith("learn"):
        return "learn"
    if normalized.startswith("resource") or normalized in {"document", "toolkit", "template"}:
        return "resources"
    if normalized.startswith("case study"):
        return "case_studies"
    if normalized.startswith("forum"):
        return "community"
    if normalized.startswith("expert"):
        return "experts"
    if "webinar" in normalized or "event" in normalized:
        return "events"
    if any(marker in normalized for marker in ("grant", "fund", "loan", "programme", "program", "training")):
        return "opportunities"
    return normalized or "other"


def _starting_point(recommendations: list[Recommendation]) -> Recommendation:
    """Prefer a near-equivalent actionable item over evidence as the first step."""
    strongest = recommendations[0]
    if _content_group(strongest.resource_type) != "data_centre":
        return strongest

    actionable_groups = {"learn", "resources", "case_studies", "opportunities"}
    near_matches = [
        item
        for item in recommendations[1:]
        if _content_group(item.resource_type) in actionable_groups
        and item.fit_score >= strongest.fit_score * 0.85
    ]
    return max(near_matches, key=lambda item: item.fit_score, default=strongest)


def _diversify(recommendations: list[Recommendation], top_k: int) -> list[Recommendation]:
    if len(recommendations) <= 1 or top_k <= 1:
        return recommendations
    starting_point = _starting_point(recommendations)
    selected = [starting_point]
    selected_ids = {id(starting_point)}
    seen_groups = {_content_group(starting_point.resource_type)}
    threshold = max(0.08, recommendations[0].fit_score * 0.35)

    for item in recommendations:
        if id(item) in selected_ids:
            continue
        group = _content_group(item.resource_type)
        if group not in seen_groups and item.fit_score >= threshold:
            selected.append(item)
            selected_ids.add(id(item))
            seen_groups.add(group)
            if len(selected) >= top_k:
                return selected

    for item in recommendations:
        if id(item) not in selected_ids:
            selected.append(item)
            if len(selected) >= top_k:
                break
    return selected
