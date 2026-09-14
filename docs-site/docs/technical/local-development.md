---
title: Local development
sidebar_position: 2
---

## Requirements

- Docker Desktop with Docker Compose
- Git
- A modern browser

## Start the stack

From the repository root:

```bash
docker compose up -d
```

Open these local services:

| Service | Address |
| --- | --- |
| WordPress | `http://localhost:8090` |
| WordPress admin | `http://localhost:8090/wp-admin` |
| Mailpit | `http://localhost:8025` |

The MariaDB service is available only inside the Docker network. The WP-CLI container remains available for setup and maintenance commands.

## Stop the stack

```bash
docker compose down
```

Avoid `docker compose down -v` unless you deliberately intend to delete the local database volume.

## Local credentials

The README contains development-only credentials for the local stack. Never reuse those values in staging or production.
