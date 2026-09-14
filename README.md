# GSF Hub WordPress Project

This repository now contains:

- a custom WordPress theme at `wp-content/themes/gsf-hub`
- a local Docker-based vanilla WordPress install
- a Docusaurus knowledge base at `docs-site` with technical documentation,
  role-based user guides, and video tutorials

## Documentation site

The documentation site requires Node.js 20 or newer and pnpm. To run it locally:

1. Change to `docs-site`.
2. Run `pnpm install`.
3. Run `pnpm start`.

Use `pnpm build` to validate a production build. The GitHub Actions workflow in
`.github/workflows/docs.yml` checks the documentation on pushes and pull requests.

## Local stack

Services are defined in `docker-compose.yml`:

- WordPress: `http://localhost:8090`
- Mailpit: `http://localhost:8025`
- MariaDB: internal Docker service
- WP-CLI: helper container for setup and theme activation

## Local admin credentials

- Username: `gsfadmin`
- Password: `gsf-hub-local-admin`
- Email: `admin@example.com`

## Theme

The custom theme lives in:

- `wp-content/themes/gsf-hub`

What is included:

- A responsive homepage template that mirrors the supplied GSF Hub mockup
- WordPress theme setup with logo support and primary/footer menus
- Search, archive, single post, page, and 404 templates
- Mobile navigation and a custom visual system for the GSF brand direction

## Run locally

1. Start the stack with `docker compose up -d`.
2. Open `http://localhost:8090`.
3. Log into WordPress admin at `http://localhost:8090/wp-admin`.

The repository contains the custom source code and local development setup. Database
dumps, WordPress uploads, migration backups, generated deliverables, and packaged
course archives are intentionally excluded because they can contain private data or
large binary files. Share those separately through an approved secure channel when a
collaborator needs a full content migration.

## Content notes

The homepage sections currently use theme-coded placeholder content in:

- `wp-content/themes/gsf-hub/front-page.php`

If you want this to become fully editor-managed, the next practical step is to wire these sections to:

- native WordPress pages/posts/custom post types, or
- Advanced Custom Fields for homepage section editing

## Tutorial videos

The content-manager video library is available in [docs/tutorial-videos](docs/tutorial-videos/README.md). It includes short narrated walkthroughs for signing in, editing pages, managing resources and case studies, and creating online courses.

## Suggested next build phase

- Add custom post types for resources, case studies, webinars, and experts
- Add multilingual support with Polylang or WPML
- Replace placeholder counts and charts with live reporting data
- Add real asset imagery and partner logos

## Resource recommendation assistant prototype

A citation-first Python prototype and WordPress integration are available at:

- `prototypes/resource-recommendation-assistant`
- `wp-content/plugins/gsf-resource-recommendation-assistant`

The prototype reads common office files and the Hub's live public WordPress corpus, applies explicit matching, eligibility, and freshness rules, and exposes cited recommendations through the `[gsf_resource_assistant]` shortcode. It can recommend Learn courses, resources, case studies, public forums/topics, data stories, experts, and webinars/events. Its standalone Bahamas records are fictional, unverified demo data and are not used by the live WordPress Docker integration. See the prototype README for setup, testing, privacy boundaries, Docker, and production-hardening guidance.
