# GSF Resource Recommendation Assistant

The production WordPress integration is now self-contained. The plugin reads the Hub's published content, ranks relevant paths in PHP, and returns cited recommendation cards without a Python service, API key, or second server.

This directory preserves the earlier Python document-ingestion prototype. It remains useful for experimenting with PDF, Word, and spreadsheet extraction, but it is optional and is not required by the WordPress assistant.

The three records in `data/demo_resources.csv` are fictional, unverified demo data based on the example scenario. They must not be presented to end users as real programmes. Replace the demo file with approved Bahamas material before a pilot.

## What the prototype does

- Reads PDF, Word (`.docx`), Excel (`.xlsx`/`.xlsm`), CSV, TSV, JSON, text, and Markdown files.
- Reads `gsf-hub-transfer` JSON exports and live WordPress corpus responses.
- Unifies published Resources, Learn courses, Data Centre projects/indicators/Ecoequity records, case studies, public forums/topics, data stories, expert profiles, and webinars/events in one recommendation library.
- Preserves PDF page, spreadsheet sheet/row, CSV row, and Word paragraph/table locators.
- Infers only locations, sectors, user types, and needs that exist in the controlled library.
- Combines local concept-expanded retrieval with explicit location, sector, target-user, eligibility, deadline, and freshness rules.
- Returns reasons, requirements/barriers, next steps, missing information, and supporting source excerpts.
- Flags expired, rolling, missing, unparseable, or demo/unverified information instead of silently treating it as current.
- Can expose an authenticated HTTP endpoint for standalone experiments; the production WordPress plugin does not call it.

The first version deliberately uses a deterministic evidence-bound response builder and a transparent local retriever. It does not require an external language model to run. The retriever can later be replaced with embeddings and the response builder can be replaced with an LLM, while retaining the eligibility gates and citation contract.

## Expected structured fields

Structured CSV, TSV, Excel, or JSON sources can use these columns:

```text
resource_name
description
resource_type
target_users
eligible_islands
sectors
needs_addressed
eligibility_rules
financial_support
application_deadline
contact_information
next_steps
source_document
source_page
updated_at
data_status
```

Use `|` or `;` between multiple values. A structured row becomes one resource. A PDF, Word, text, or Markdown file is treated as one resource with multiple evidence chunks. If a handbook contains several programmes, create one structured row per programme and point `source_document` and `source_page` at the relevant source.

Legacy `.xls` files are not read directly; save them as `.xlsx` or CSV first. Scanned PDFs require OCR before this prototype can extract text. Word pagination is not stable outside Word, so Word citations use paragraph or table-row locators rather than invented page numbers.

## Run locally

From this directory:

```bash
python3 -m venv .venv
. .venv/bin/activate
pip install -e .
```

Run the demonstration query:

```bash
gsf-recommend recommend \
  --data data \
  --situation "I manage a small tourism business in Eleuthera and need support for hurricane resilience." \
  --today 2026-08-03 \
  --format text
```

Build a portable index:

```bash
gsf-recommend ingest data --output build/resource-index.json
```

An existing GSF Hub WordPress transfer export can be indexed directly:

```bash
gsf-recommend ingest /path/to/gsf-hub-transfer.json --output build/wordpress-resource-index.json
```

Query the live public WordPress corpus directly:

```bash
gsf-recommend recommend \
  --wordpress-url http://localhost:8090/wp-json/gsf-resource-assistant/v1/corpus \
  --situation "I need a course and practical toolkit for gender mainstreaming in conservation."
```

Use the index for recommendations:

```bash
gsf-recommend recommend \
  --index build/resource-index.json \
  --situation "I need hurricane planning support for a tourism business in Eleuthera."
```

Run the API on the local machine:

```bash
export GSF_RESOURCE_ASSISTANT_API_TOKEN="replace-with-a-long-random-value"
gsf-recommend serve --data data --host 127.0.0.1 --port 8765
```

The API provides `GET /health` and authenticated `POST /recommend`. It refuses to listen on a non-loopback address without a bearer token.

## Production WordPress plugin

The plugin is located at:

```text
wp-content/plugins/gsf-resource-recommendation-assistant
```

It adds:

- a `[gsf_resource_assistant]` shortcode;
- a single conversational prompt and an accessible visual resource map;
- category-coloured recommendation cards spanning Learn, Resources, Data Centre, case studies, community, experts, events, and opportunities;
- a replacement for native WordPress search forms and `?s=` searches, carrying the query into the assistant and running it automatically;
- a secondary traditional-search link after each assistant query for exact titles and keyword matches;
- a compact conversational assistant entry point in place of the homepage keyword-search panel;
- a local PHP matching and ranking engine that runs inside WordPress;
- input limits, a basic public rate limit, output escaping, and no response caching;
- a status page under **Settings → Resource Assistant** showing the active engine and library size;
- a read-only `/wp-json/gsf-resource-assistant/v1/corpus` endpoint containing supported public Hub material.

The corpus endpoint includes only published, password-free, publicly queryable content. Private or hidden forums are excluded, explicitly named test records are excluded, internal draft Data Centre activities are excluded, and forum replies are not ingested by default. Only a small whitelist of public recommendation metadata is exposed.

For the repository's Docker-based WordPress installation, only the normal WordPress stack is required:

```bash
docker compose up -d
```

Activate **GSF Resource Recommendation Assistant** in WordPress and add `[gsf_resource_assistant]` to a page. The current site uses the `/resource-assistant/` page and links the homepage assistant panel to it.

The plugin reads current published WordPress records on each request. It supports Learn, Resources, Data Centre, Case Studies, public Forums, Experts, and Events content, adds a direct source link to every card, and marks missing or old application information instead of inventing it.

No external URL, token, or recommendation-service configuration is needed. The legacy Python overlay in `docker-compose.resource-assistant.yml` is retained only for optional research and document-ingestion experiments; do not use it for the production WordPress assistant.

## WordPress verification

With the optional Python container stopped, verify:

1. The homepage prompt opens `/resource-assistant/` with the question filled in.
2. “Support my NCTF” starts with the CTF Learn course and also maps Data Centre evidence, Resources, Experts, Community, and Case Studies.
3. “Show me Data Centre indicators and evidence for NCTF staff training results” starts with the matching Data Centre indicator.
4. Each card includes an **Open in GSF Hub** source link and an expandable supporting-evidence citation.
5. **Settings → Resource Assistant** reports “Local WordPress engine active.”

## Verification

Run all tests:

```bash
python -m unittest discover -s tests -v
```

The suite checks:

- the expected Eleuthera/tourism/hurricane ranking;
- expired-deadline handling;
- PDF, Word, Excel, and CSV ingestion with locators;
- API bearer-token enforcement and citation output.
- NCTF queries returning a useful mix of Data Centre, Learn, and Resource paths.
- actionable courses, tools, case studies, or opportunities leading when their fit is close to the strongest Data Centre evidence result.

## Further development

Before a public pilot:

1. Review and approve every published Hub record and add an owner plus verification date where appropriate.
2. Add OCR for scanned documents and a review queue for low-quality extraction.
3. If the library eventually outgrows the PHP matcher, store chunks and embeddings in a durable vector-capable database while keeping structured eligibility checks authoritative.
4. If an LLM is added, require schema-constrained output, permit only retrieved evidence, validate every cited locator, and fall back to the deterministic response when validation fails.
5. Add scheduled deadline checks, programme-owner reminders, audit logs without unnecessary personal data, evaluation queries, and human review for high-impact recommendations.
6. If a separate AI or retrieval service is added later, put it behind HTTPS, authentication, network restrictions, monitoring, and production-grade rate limiting.
