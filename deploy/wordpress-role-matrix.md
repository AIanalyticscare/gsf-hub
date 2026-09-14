# WordPress Role Matrix

Source: live role configuration from the local GSF Hub WordPress site on June 10, 2026, using `wp role list` and `$wp_roles->roles`.

Legend:
- `Yes`: explicitly granted by the role data.
- `No`: explicitly absent or denied by the role data.
- `Limited`: available, but only for a narrower scope such as own content or plugin-specific content.
- `Plugin/default`: not overridden by Ultimate Member in the role data; final behavior should be confirmed in staging.

## Editorial And Site Roles

| Role Slug | Role Name | Source | WP Admin Access | Admin Bar Visible | Site Settings / Plugins / Themes | User Management | Content Rights | Media Upload | Tutor LMS | Forum Rights | Front-End Profile Edit | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `administrator` | Administrator | Core + Tutor | Yes | Yes | Yes | Yes | All posts and pages | Yes | Yes | Indirect via site admin | Yes | Full site control. Also has Tutor LMS management rights. |
| `editor` | Editor | Core | No | No | No | No | All posts and pages | Yes | No | No explicit forum rights | Yes | Ultimate Member is currently blocking wp-admin access even though the core editor role can manage content. |
| `author` | Author | Core | No | No | No | No | Own posts only, including publish/delete | Yes | No | No explicit forum rights | Yes | Good for staff who publish their own articles only. |
| `contributor` | Contributor | Core | No | No | No | No | Own posts only, draft/edit/delete before publish | No | No | No explicit forum rights | Yes | Cannot publish posts or upload media. |
| `subscriber` | Subscriber | Core | No | No | No | No | Read only | No | No | No explicit forum rights | Yes | Basic logged-in account role. |

## Plugin And Workflow Roles

| Role Slug | Role Name | Source | WP Admin Access | Admin Bar Visible | Site Settings / Plugins / Themes | User Management | Content Rights | Media Upload | Tutor LMS | Forum Rights | Front-End Profile Edit | Notes |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `tutor_instructor` | Tutor Instructor | Tutor LMS | Plugin/default | Plugin/default | No | No | Tutor content only | Yes | Yes | No explicit forum rights | Plugin/default | Can manage Tutor courses, lessons, quizzes, and questions. This role should be tested in staging because Ultimate Member does not currently override it. |
| `bbp_keymaster` | Keymaster | bbPress | No explicit dashboard capability | No explicit dashboard capability | No | No | Forum management only | No | No | Full forum moderation and management | No explicit profile capability | Highest bbPress role. Not a substitute for site administrator. |
| `bbp_moderator` | Moderator | bbPress | No explicit dashboard capability | No explicit dashboard capability | No | No | Forum moderation only | No | No | Strong forum moderation | No explicit profile capability | Can moderate topics and replies, including others' content. |
| `bbp_participant` | Participant | bbPress | No explicit dashboard capability | No explicit dashboard capability | No | No | Forum participation only | No | No | Can create and edit own topics/replies | No explicit profile capability | Standard forum member role. |
| `bbp_spectator` | Spectator | bbPress | No explicit dashboard capability | No explicit dashboard capability | No | No | Read-only forum access | No | No | View-only forum access | No explicit profile capability | Suitable for read-only forum viewers. |
| `bbp_blocked` | Blocked | bbPress | No | No | No | No | None | No | No | No forum participation | No | Explicitly denied forum rights. |
| `um_expert_pending` | Expert Applicant | Ultimate Member | No | No | No | No | Read only | No | No | No explicit forum rights | Yes | Workflow role for pending expert applications. Redirects to the site front end after login. |
| `um_expert_approved` | Expert Roster Member | Ultimate Member | No | No | No | No | Read only | No | No | No explicit forum rights | Yes | Workflow role for approved expert roster members. Redirects to the site front end after login. |

## Implementation Notes

- Ultimate Member is currently applying front-end restrictions to the core editorial roles and expert workflow roles.
- The `editor`, `author`, `contributor`, `subscriber`, `um_expert_pending`, and `um_expert_approved` roles are all configured to hide the admin bar.
- The `editor`, `author`, `contributor`, `subscriber`, `um_expert_pending`, and `um_expert_approved` roles are all configured with no wp-admin access.
- `administrator` is the only role in the current dataset with explicit private-profile access and site-wide profile management rights.
- `tutor_instructor` does not currently include Ultimate Member overrides in the stored role data, so the exact dashboard experience should be verified after migration.
- The bbPress roles appear as WordPress roles, but they are forum-specific permission roles rather than general site management roles.

## Recommended Practical Use

| Use Case | Recommended Role |
|---|---|
| Full platform owner / technical admin | `administrator` |
| Content manager for pages, posts, and site copy | `editor` |
| Staff writer publishing their own updates | `author` |
| Draft-only contributor | `contributor` |
| General member / learner / standard account | `subscriber` |
| Course instructor for Tutor LMS | `tutor_instructor` |
| Forum lead / community moderator | `bbp_moderator` or `bbp_keymaster` |
| Expert application workflow | `um_expert_pending` and `um_expert_approved` |
