---
title: Deployment and releases
sidebar_position: 5
---

Deploy custom source through a reviewed release process. Keep the production database and uploads outside Git, back them up before material changes, and test the release in a staging environment first.

## Suggested release checklist

1. Review the Git diff and confirm the intended theme and plugin versions.
2. Run PHP syntax checks and the relevant automated tests.
3. Create a restorable database and uploads backup outside the repository.
4. Deploy to staging and test public pages, sign-in, Learn, forums, the Data Centre, and mail delivery.
5. Deploy the approved package to production.
6. Repeat smoke tests and record the release outcome.

The `deploy` directory contains targeted migration notes and historical update bundles. Treat the current source under `wp-content` as the primary implementation.
