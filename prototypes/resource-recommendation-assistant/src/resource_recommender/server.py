from __future__ import annotations

import hmac
import json
import threading
import time
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from collections.abc import Callable
from typing import Any

from .models import Library
from .recommender import ResourceRecommender


MAX_REQUEST_BYTES = 1_000_000


class RecommendationServer(ThreadingHTTPServer):
    recommender: ResourceRecommender
    api_token: str
    library_loader: Callable[[], Library] | None
    refresh_seconds: int
    last_refresh: float
    last_refresh_error: str
    refresh_lock: threading.Lock

    def refresh_library_if_due(self, *, force: bool = False) -> None:
        if not getattr(self, "library_loader", None):
            return
        now = time.monotonic()
        if not force and now - self.last_refresh < self.refresh_seconds:
            return
        with self.refresh_lock:
            now = time.monotonic()
            if not force and now - self.last_refresh < self.refresh_seconds:
                return
            try:
                library = self.library_loader()
                self.recommender = ResourceRecommender(library)
                self.last_refresh_error = ""
            except Exception as exc:  # Keep the last valid library available.
                self.last_refresh_error = str(exc)
                print(f"WordPress corpus refresh failed: {exc}")
            finally:
                self.last_refresh = now


class RecommendationHandler(BaseHTTPRequestHandler):
    server: RecommendationServer

    def do_GET(self) -> None:  # noqa: N802 - BaseHTTPRequestHandler API
        if self.path.rstrip("/") == "/health":
            self._send_json(
                HTTPStatus.OK,
                {
                    "status": "ok",
                    "resources": len(self.server.recommender.library.resources),
                    "refresh_error": getattr(self.server, "last_refresh_error", ""),
                },
            )
            return
        self._send_json(HTTPStatus.NOT_FOUND, {"error": "Not found"})

    def do_POST(self) -> None:  # noqa: N802 - BaseHTTPRequestHandler API
        if self.path.rstrip("/") != "/recommend":
            self._send_json(HTTPStatus.NOT_FOUND, {"error": "Not found"})
            return
        if not self._authorized():
            self._send_json(HTTPStatus.UNAUTHORIZED, {"error": "Unauthorized"})
            return
        self.server.refresh_library_if_due()

        try:
            length = int(self.headers.get("Content-Length", "0"))
        except ValueError:
            self._send_json(HTTPStatus.BAD_REQUEST, {"error": "Invalid Content-Length"})
            return
        if length <= 0 or length > MAX_REQUEST_BYTES:
            self._send_json(HTTPStatus.REQUEST_ENTITY_TOO_LARGE, {"error": "Request body is missing or too large"})
            return

        try:
            payload = json.loads(self.rfile.read(length).decode("utf-8"))
        except (UnicodeError, json.JSONDecodeError):
            self._send_json(HTTPStatus.BAD_REQUEST, {"error": "Request body must be valid JSON"})
            return
        if not isinstance(payload, dict):
            self._send_json(HTTPStatus.BAD_REQUEST, {"error": "Request body must be a JSON object"})
            return

        situation = _string(payload.get("situation"), 4_000)
        if len(situation) < 3:
            self._send_json(HTTPStatus.BAD_REQUEST, {"error": "situation must contain at least 3 characters"})
            return

        try:
            top_k = max(1, min(10, int(payload.get("top_k", 3))))
        except (TypeError, ValueError):
            top_k = 3

        need = self.server.recommender.understand(
            situation,
            location=_string(payload.get("location"), 200),
            sector=_string(payload.get("sector"), 200),
            needs=_string_list(payload.get("needs"), limit=20, item_length=300),
            constraints=_string_list(payload.get("constraints"), limit=20, item_length=300),
            user_type=_string(payload.get("user_type"), 200),
        )
        result = self.server.recommender.recommend(need, top_k=top_k)
        self._send_json(HTTPStatus.OK, result.to_dict())

    def _authorized(self) -> bool:
        expected = self.server.api_token
        if not expected:
            return True
        authorization = self.headers.get("Authorization", "")
        supplied = authorization[7:] if authorization.startswith("Bearer ") else ""
        return bool(supplied) and hmac.compare_digest(supplied, expected)

    def _send_json(self, status: HTTPStatus, payload: dict[str, Any]) -> None:
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(status.value)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)


def _string(value: object, limit: int) -> str:
    return str(value or "").strip()[:limit]


def _string_list(value: object, *, limit: int, item_length: int) -> list[str]:
    if value is None:
        return []
    if isinstance(value, str):
        raw = [item.strip() for item in value.split("|")]
    elif isinstance(value, list):
        raw = [_string(item, item_length) for item in value]
    else:
        return []
    return [item[:item_length] for item in raw[:limit] if item]


def serve(
    library: Library,
    *,
    host: str = "127.0.0.1",
    port: int = 8765,
    api_token: str = "",
    library_loader: Callable[[], Library] | None = None,
    refresh_seconds: int = 60,
) -> None:
    if host not in {"127.0.0.1", "localhost", "::1"} and not api_token:
        raise ValueError("An API token is required when listening beyond the local machine.")
    server = RecommendationServer((host, port), RecommendationHandler)
    server.recommender = ResourceRecommender(library)
    server.api_token = api_token
    server.library_loader = library_loader
    server.refresh_seconds = max(5, refresh_seconds)
    server.last_refresh = 0.0
    server.last_refresh_error = ""
    server.refresh_lock = threading.Lock()
    print(f"Resource recommendation API listening on http://{host}:{port}")
    print(f"Loaded {len(library.resources)} resources")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
