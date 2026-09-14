---
title: GSF Hub Administrator Guide
---

import useBaseUrl from '@docusaurus/useBaseUrl';

<div className="guide-downloads">
  <a className="button button--primary" href={useBaseUrl('/guides/GSF-Hub-Administrator-Guide.pdf')}>Download the approved PDF</a>
</div>

> Manage people, permissions, platform configuration, updates, backups, and operational health.

| Guide detail | Description |
| --- | --- |
| Audience | WordPress administrators, platform owners, and technical administrators. |
| Scope | Site-wide administration. Day-to-day content production is summarized here and covered in the Staff Content Operations Guide. |
| Tested against | GSF Hub local test site at localhost:8090 |
| Version date | September 28, 2026 |

**How to use this guide.** Follow the numbered procedures in order. Screenshots show the current local test build; labels may move slightly after WordPress, theme, or plugin updates.

## Contents

1.  Administrator responsibilities and safe operating pattern

2.  Dashboard and routine checks

3.  Users, registration, roles, and expert applications

4.  Plugins, themes, updates, and site configuration

5.  Backups, migration, email, and site health

6.  First-time end-user display assessment

7.  Incident response and handover checklist

## 1. Administrator responsibilities and safe operating pattern

Administrators have full control of WordPress, plugins, themes, users, roles, settings, and all GSF content areas. Use the administrator role only when site-wide authority is required.

**Administrator standard.** Back up before structural changes, make one change set at a time, test on localhost or staging, verify the public result, and document anything that changes access or data behavior.

### Sign in

1. Open /wp-admin/ on the correct environment.
1. Use your assigned administrator account; do not share credentials.
1. Confirm the site name and environment before changing anything.
1. Use the WordPress toolbar to view the public site after changes.
### Before any high-impact change

- Confirm a recent backup exists and can be restored.
- Record the current plugin or theme version.
- Check whether the change affects registration, login, Learn, Data Centre, multilingual content, or forums.
- Plan a rollback before updating, importing, changing roles, or editing settings.
## 2. Dashboard and routine checks


![WordPress administrator dashboard and GSF Hub menu groups.](/img/guides/admin-01-dashboard.png)

*Figure 1. WordPress administrator dashboard and GSF Hub menu groups.*

### Daily or publishing-day checks

- Review notices for failed background actions, mail failures, or site-health warnings.
- Check new users, pending expert applications, and comments or forum activity.
- Verify newly published courses, resources, case studies, and data on the public site.
### Weekly checks

- Review available updates without applying them automatically to production.
- Review Site Health and email delivery.
- Confirm backups are recent and stored outside the web root.
- Review administrator accounts and remove access that is no longer required.
### Monthly checks

- Test registration, password reset, member login, one Learn enrollment, one forum post, and newsletter signup.
- Review role assignments and Data Centre access.
- Archive superseded exports and document platform changes.
## 3. Users, registration, roles, and expert applications

### Create or update a user

1. Open Users > All Users or Add User.
1. Search for an existing account before creating a duplicate.
1. Set the minimum role needed for the person's work.
1. Add a bbPress forum role only when forum participation or moderation is required.
1. Save the account and ask the user to set or reset their own password.
### GSF role guide

| Role | Recommended use |
| --- | --- |
| Subscriber / GSF Student | Member or learner with front-end access only. |
| GSF Content Staff | Resources, case studies, posts/pages, media, and Learn content where granted. |
| GSF MEAL Officer | Data submission, verification, indicators, evidence, projects, and reports. |
| GSF CBF Staff / GSF Admin | Broad Data Centre management; settings access depends on capability set. |
| bbPress Participant | Create and edit own forum topics and replies. |
| bbPress Moderator | Moderate topics and replies, including other users' content. |
| bbPress Keymaster | Full forum administration. |
| Administrator | Full platform control; reserve for site owners and technical administrators. |

**Least privilege.** Content, Data Centre, Learn, newsletter, and forum duties use different capabilities. Assign only the roles needed; one staff member may require a WordPress role plus a forum role.

### Review and change roles


![User Role Editor for reviewing role capabilities.](/img/guides/admin-03-role-editor.png)

*Figure 2. User Role Editor for reviewing role capabilities.*

- Prefer assigning an existing tested role over editing capabilities directly.
- Never remove capabilities from Administrator without a tested recovery path.
- After a role change, test with a non-administrator account in staging.
### Ultimate Member and expert applications

- Ultimate Member controls the front-end Login, Register, Account, member directory, email templates, and account status.
- Users > Expert Applications supports the expert-roster review workflow.
- Keep expert application status, directory profile status, and WordPress roles aligned.
- The current configuration sends a welcome email for an activated account.
## 4. Plugins, themes, updates, and site configuration


![Installed Plugins screen.](/img/guides/admin-04-plugins.png)

*Figure 3. Installed Plugins screen.*

### Safe update sequence

1. Create or confirm a restorable backup.
1. Review the changelog and compatibility for WordPress, PHP, theme, and dependent plugins.
1. Apply the update on localhost or staging.
1. Test homepage, Login, Register, Account, Learn, Resources, Case Studies, Data Centre, Directory, Forum, and email.
1. Apply to production during an agreed maintenance window.
1. Repeat the smoke test and record the result.
**Do not bulk-update blindly.** The GSF Hub combines Ultimate Member, Polylang, bbPress, Mailchimp, SCORM/Learn, Data Centre, Pods, and custom theme code. Update in small batches so a regression can be isolated.

### Configuration ownership

| Area | Administrator menu |
| --- | --- |
| Site name, timezone, and base settings | Settings > General |
| Homepage and page publishing | Pages and Appearance |
| Navigation | Appearance > Menus |
| Language and translations | Languages |
| Registration and member accounts | Ultimate Member |
| Newsletter signup form and audience | Mailchimp |
| Outbound email delivery | WP Mail SMTP |
| Forums | Forum and Settings > Forums |
| Roles and capabilities | Users > User Role Editor or Members |
| Custom content models | Pods Admin; change only with technical review |

## 5. Backups, migration, email, and site health

### Backups and migration

- Use All-in-One WP Migration or the approved transfer workflow for a full-site package.
- Store backups outside the live WordPress directory and protect them as sensitive data.
- Test restore procedures; a backup is not reliable until a restore has been demonstrated.
- After migration, replace localhost URLs and test file downloads, SCORM packages, email, and multilingual links.
### Email

- WP Mail SMTP manages WordPress mail delivery and diagnostics.
- The local test environment routes mail to Mailpit at localhost:8025.
- Test welcome, password-reset, reminder, subscription, and notification messages after mail changes.
### Site Health

- Open Tools > Site Health and review critical issues first.
- Treat public debug output, old plugins, failed cron jobs, and insecure transport as release blockers.
- Do not paste secrets, full database exports, or member data into tickets or public chats.
## 6. First-time end-user display assessment

This section answers whether a first-user or first-login display can be added for front-end members.

**Finding.** Yes. The current site can support a one-time welcome display for end users. It is not currently implemented as a first-login-only experience.

### What exists today

- Ultimate Member welcome email is enabled.
- After sign-in, members land on /account/ and see Quick Access cards.
- Those cards appear on normal account visits; they are not tracked as a one-time onboarding display.
- The WordPress Welcome panel shown on the administrator dashboard is admin-only and does not serve front-end members.
### Recommended implementation

Add a small GSF onboarding plugin or theme-independent module that redirects a newly activated member to /account/?welcome=1 or displays an accessible panel on the Account page. Store a user-meta flag such as gsf_onboarding_completed so it appears once per user.

| Element | Recommendation |
| --- | --- |
| Audience | Subscriber, GSF Student, expert applicants, and other front-end roles; exclude administrators and staff by default. |
| Trigger | First successful front-end login after activation, not every login. |
| Content | Complete profile, open Learn, browse Resources, visit Forum, and review privacy. |
| Controls | Start tour, Skip for now, and Do not show again; all keyboard accessible. |
| State | Persist a per-user completion or dismissal flag; provide an admin-only reset for testing. |
| Analytics | Track view, step completion, skip, and destination clicks without storing sensitive field values. |
| Fallback | If scripting is blocked, render a normal Account-page welcome card with the same actions. |

### Acceptance checklist

- Appears once for a brand-new approved front-end user.
- Does not interrupt password reset, email activation, course deep links, or expert application redirects.
- Does not appear for administrators or content staff unless explicitly enabled.
- Works on mobile, with keyboard-only navigation, and with a screen reader.
- Skip and completion state survive logout and a new browser session.
- An administrator can reset the flag on a test account.
- The user can still reach every destination later from Account > Quick Access.
**Implementation boundary.** This is a small custom development task rather than a WordPress core setting. Build and test it against Ultimate Member login/registration redirects and the existing Account page.

## 7. Incident response and handover checklist

### If a change causes a problem

1. Stop making further unrelated changes.
1. Record the time, affected pages, user role, error message, and last successful state.
1. Disable or roll back only the suspected change on staging first when possible.
1. Restore from backup if the site or data cannot be safely recovered in place.
1. Retest the affected workflow and the main smoke-test paths.
1. Document the root cause and prevention action.
### Administrator handover

- Named owners for hosting, domain, DNS, backups, WordPress, mail, Mailchimp, and Zoom.
- Current administrator list and emergency access procedure.
- Backup location, restore instructions, and latest successful restore test.
- Plugin/theme inventory, custom-code locations, and update history.
- Known issues, scheduled tasks, and support contacts.
