from __future__ import annotations

import argparse
import json
import os
from datetime import date
from pathlib import Path

from .ingest import IngestionError, ingest_path, ingest_wordpress_url
from .models import Library, RecommendationResult
from .recommender import ResourceRecommender
from .server import serve


def _parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Citation-first resource recommendation prototype")
    subparsers = parser.add_subparsers(dest="command", required=True)

    ingest = subparsers.add_parser("ingest", help="Ingest source files and write a portable JSON index")
    ingest.add_argument("source", type=Path, help="A supported file or directory")
    ingest.add_argument("--output", "-o", type=Path, required=True, help="Index JSON destination")

    recommend = subparsers.add_parser("recommend", help="Recommend resources for a user's situation")
    _add_library_arguments(recommend)
    recommend.add_argument("--situation", required=True)
    recommend.add_argument("--location", default="")
    recommend.add_argument("--sector", default="")
    recommend.add_argument("--need", action="append", dest="needs", default=[])
    recommend.add_argument("--constraint", action="append", dest="constraints", default=[])
    recommend.add_argument("--user-type", default="")
    recommend.add_argument("--top-k", type=int, default=3)
    recommend.add_argument("--today", type=date.fromisoformat, default=None, help="Override today's date (YYYY-MM-DD)")
    recommend.add_argument("--format", choices=("json", "text"), default="json")

    api = subparsers.add_parser("serve", help="Run the read-only HTTP API used by WordPress")
    _add_library_arguments(api)
    api.add_argument("--host", default="127.0.0.1")
    api.add_argument("--port", type=int, default=8765)
    api.add_argument("--refresh-seconds", type=int, default=60)
    api.add_argument(
        "--api-token",
        default=os.environ.get("GSF_RESOURCE_ASSISTANT_API_TOKEN", ""),
        help="Bearer token (defaults to GSF_RESOURCE_ASSISTANT_API_TOKEN)",
    )
    return parser


def _add_library_arguments(parser: argparse.ArgumentParser) -> None:
    group = parser.add_mutually_exclusive_group(required=False)
    group.add_argument("--data", type=Path, help="Ingest this source file or directory at startup")
    group.add_argument("--index", type=Path, help="Use a previously generated JSON index")
    parser.add_argument(
        "--wordpress-url",
        help="Load public Hub content from the WordPress corpus REST endpoint",
    )


def _load_library(args: argparse.Namespace) -> Library:
    base = _load_static_library(args)
    if getattr(args, "wordpress_url", None):
        return _merge_libraries(base, ingest_wordpress_url(args.wordpress_url))
    if not base.resources and not base.warnings:
        raise IngestionError("Provide --data, --index, or --wordpress-url.")
    return base


def _load_static_library(args: argparse.Namespace) -> Library:
    if getattr(args, "data", None):
        return ingest_path(args.data)
    elif getattr(args, "index", None):
        try:
            payload = json.loads(args.index.read_text(encoding="utf-8"))
        except (OSError, UnicodeError, json.JSONDecodeError) as exc:
            raise IngestionError(f"Could not load index {args.index}: {exc}") from exc
        return Library.from_dict(payload)
    return Library(resources=[])


def _merge_libraries(*libraries: Library) -> Library:
    resources = []
    warnings = []
    seen = set()
    roots = []
    for library in libraries:
        roots.append(library.source_root)
        warnings.extend(library.warnings)
        for resource in library.resources:
            key = (resource.resource_name.casefold(), resource.resource_type.casefold())
            if key not in seen:
                resources.append(resource)
                seen.add(key)
    return Library(
        resources=resources,
        warnings=list(dict.fromkeys(warnings)),
        source_root="; ".join(root for root in roots if root),
    )


def _render_text(result: RecommendationResult) -> str:
    lines = []
    if not result.recommendations:
        lines.append("No supported match found.")
    for index, item in enumerate(result.recommendations, start=1):
        label = "Best match" if index == 1 else f"Match {index}"
        lines.extend(
            [
                f"{label}: {item.resource}",
                f"Type: {item.resource_type}",
                f"Fit score: {item.fit_score:.2f}",
            ]
        )
        if item.eligibility_status != "not_applicable":
            lines.append(f"Eligibility status: {item.eligibility_status}")
        if item.why_it_fits:
            lines.append("Why: " + " ".join(item.why_it_fits))
        if item.possible_barriers:
            lines.append("Barriers: " + " ".join(item.possible_barriers))
        if item.application_deadline != "Not stated in the source record":
            lines.append("Deadline: " + item.application_deadline)
        if item.contact_information != "Not stated in the source record":
            lines.append("Contact: " + item.contact_information)
        if item.next_steps:
            lines.append("Next steps: " + " ".join(item.next_steps))
        citations = []
        for source in item.sources:
            locator = source["document"]
            if source.get("page"):
                locator += f", page {source['page']}"
            elif source.get("sheet") and source.get("row"):
                locator += f", {source['sheet']} row {source['row']}"
            elif source.get("row"):
                locator += f", row {source['row']}"
            elif source.get("paragraph"):
                locator += f", paragraph {source['paragraph']}"
            citations.append(locator)
            if source.get("url"):
                citations[-1] += f" ({source['url']})"
        lines.append("Sources: " + "; ".join(citations))
        if item.uncertainties:
            lines.append("Uncertainties: " + " ".join(item.uncertainties))
        lines.append("")
    if result.warnings:
        lines.append("Library warnings: " + " ".join(result.warnings))
    return "\n".join(lines).rstrip()


def main(argv: list[str] | None = None) -> int:
    args = _parser().parse_args(argv)
    try:
        if args.command == "ingest":
            library = ingest_path(args.source)
            args.output.parent.mkdir(parents=True, exist_ok=True)
            args.output.write_text(json.dumps(library.to_dict(), indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
            print(f"Ingested {len(library.resources)} resources into {args.output}")
            for warning in library.warnings:
                print(f"Warning: {warning}")
            return 0 if library.resources else 2

        if args.command == "serve" and args.wordpress_url:
            static_library = _load_static_library(args)
            base_library = static_library
            if not base_library.resources:
                base_library = Library(
                    resources=[],
                    warnings=["Waiting for the WordPress public-content corpus to become available."],
                )

            def loader() -> Library:
                return _merge_libraries(static_library, ingest_wordpress_url(args.wordpress_url))

            serve(
                base_library,
                host=args.host,
                port=args.port,
                api_token=args.api_token,
                library_loader=loader,
                refresh_seconds=args.refresh_seconds,
            )
            return 0

        library = _load_library(args)
        if args.command == "serve":
            loader = None
            serve(
                library,
                host=args.host,
                port=args.port,
                api_token=args.api_token,
                library_loader=loader,
                refresh_seconds=args.refresh_seconds,
            )
            return 0

        recommender = ResourceRecommender(library, today=args.today)
        need = recommender.understand(
            args.situation,
            location=args.location,
            sector=args.sector,
            needs=args.needs,
            constraints=args.constraints,
            user_type=args.user_type,
        )
        result = recommender.recommend(need, top_k=max(1, min(10, args.top_k)))
        if args.format == "text":
            print(_render_text(result))
        else:
            print(json.dumps(result.to_dict(), indent=2, ensure_ascii=False))
        return 0
    except (IngestionError, ValueError) as exc:
        print(f"Error: {exc}")
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
