---
title: Technical WordPress overview
sidebar_position: 1
---

The GSF Hub is a Docker-based WordPress project with custom themes, custom plugins, setup scripts, and a companion resource-recommendation prototype. The repository keeps the parts that should be reviewed and versioned while excluding private site data and generated packages.

## Repository map

| Path | Purpose |
| --- | --- |
| `wp-content/themes/gsf-hub` | Main custom theme |
| `wp-content/themes/gsf-hub-sunrise` | Alternate Sunrise theme |
| `wp-content/themes/gsf-hub-assistant-child` | Resource-assistant child theme |
| `wp-content/plugins/gsf-*` | Custom GSF plugins |
| `scripts` | WordPress setup and document-generation utilities |
| `prototypes/resource-recommendation-assistant` | Citation-first recommendation service prototype |
| `docker-compose.yml` | Local WordPress, MariaDB, Mailpit, and WP-CLI stack |
| `docs-site` | This Docusaurus knowledge base |

## Version-control boundary

The repository intentionally excludes WordPress core, third-party plugins, uploaded media, database dumps, migration archives, generated outputs, and packaged online courses. Those files are either recreated by the local stack or transferred separately under the appropriate data-handling process.
