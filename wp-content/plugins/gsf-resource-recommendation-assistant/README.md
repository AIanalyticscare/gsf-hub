# GSF Resource Recommendation Assistant

A self-contained WordPress plugin that turns the GSF Hub's published content into ranked, cited recommendation cards.

## What it includes

- One conversational prompt for goals, roles, locations, and challenges.
- Local PHP matching and ranking; no Python service, API key, or second server.
- Results drawn from published Learn, Resources, Data Centre, Case Studies, public Forums, Experts, and Events content.
- Actionable-first ranking for general questions and evidence-first ranking when a user explicitly asks for Data Centre indicators or results.
- Category-diverse cards with fit reasons, possible barriers, next steps, freshness warnings, and direct source links.
- A traditional WordPress search fallback for exact titles and phrases.
- A public, read-only corpus endpoint for transparency and future integrations.

## Install

1. In WordPress Admin, open **Plugins → Add New → Upload Plugin**.
2. Upload the plugin ZIP and activate **GSF Resource Recommendation Assistant**.
3. Create or edit the Resource Assistant page and add `[gsf_resource_assistant]`.
4. To replace a homepage search area without changing themes, add `[gsf_resource_assistant_home]` to a Shortcode block or page-builder shortcode widget.
5. Confirm **Settings → Resource Assistant** says “Local WordPress engine active.”

The plugin replaces standard WordPress search forms with an **Ask the Hub** prompt and redirects normal `?s=` searches to `/resource-assistant/`. Add `traditional_search=1` to retain an exact WordPress search request.

## Content and citations

Only published, password-free, publicly queryable content is included. Private forums, forum replies, named test records, and draft Data Centre activities are excluded. Every result links back to its source item in the Hub so the user can verify the recommendation.

Application-style resources receive additional checks for stated eligibility, deadline, financial support, and contact details. Missing or stale fields are presented as uncertainties rather than inferred.

## Requirements

- WordPress 6.2 or later
- PHP 7.4 or later
- JavaScript enabled in the visitor's browser

## Changelog

### 1.0.3

- Added a compact, theme-independent `[gsf_resource_assistant_home]` shortcode for replacing only the homepage search area.
- The homepage prompt no longer requires activating the GSF Hub Sunrise child theme.

### 1.0.2

- Replaced narrow topic examples with representative questions for NCTFs, inclusive project design, team training and tools, and programme reporting.

### 1.0.1

- Removed the public visitor nonce requirement so cached pages and CDNs cannot cause “Request verification failed” errors.
- Retained request rate limiting, input limits, sanitization, and output escaping.

## Optional future AI

The current release is deterministic and does not call a language model. A future AI or embeddings layer can be added behind the same response contract, while keeping WordPress content, explicit eligibility rules, and source citations authoritative.
