# GSF Hub Sunrise

## Version 0.1.4

- Stores the Canada partnership and Caribbean Biodiversity Fund logos in the child theme.
- Uses transparent PNG logo artwork in both the site header and footer.

Uploadable child theme for the GSF Hub website. Version 0.1.3 replaces the homepage keyword search with a compact Resource Assistant prompt and representative example questions.

## Installation

1. Install the `GSF Hub` parent theme, but do not activate it.
2. Install and activate the `GSF Resource Recommendation Assistant` plugin.
3. Create a page with the slug `resource-assistant` and add `[gsf_resource_assistant]` to its content.
4. Upload this child-theme ZIP through **Appearance → Themes → Add New → Upload Theme**.
5. Activate **GSF Hub Sunrise**.
6. Clear any WordPress, server, and CDN caches.

The homepage prompt sends each question to the Resource Assistant page. WordPress installs themes and plugins separately, so the theme does not duplicate or embed the recommendation engine.
