"""Citation-first resource recommendation prototype."""

from .ingest import IngestionError, ingest_path
from .models import Library, RecommendationResult, Resource, UserNeed
from .recommender import ResourceRecommender

__all__ = [
    "IngestionError",
    "Library",
    "RecommendationResult",
    "Resource",
    "ResourceRecommender",
    "UserNeed",
    "ingest_path",
]

__version__ = "0.1.0"

