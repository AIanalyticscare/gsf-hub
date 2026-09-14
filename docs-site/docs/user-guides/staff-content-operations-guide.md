---
title: GSF Hub Staff Content Operations Guide
---

import useBaseUrl from '@docusaurus/useBaseUrl';

<div className="guide-downloads">
  <a className="button button--primary" href={useBaseUrl('/guides/GSF-Hub-Staff-Content-Operations-Guide.pdf')}>Download the approved PDF</a>
</div>

> Operate Newsletter, Data Centre, Learn, Case Studies, Resources, and Forums.

| Guide detail | Description |
| --- | --- |
| Audience | GSF content staff, MEAL officers, CBF staff, course managers, and forum moderators. |
| Scope | Day-to-day content and data operations. Platform-level updates, plugins, themes, users, and settings belong to administrators. |
| Tested against | GSF Hub local test site at localhost:8090 |
| Version date | July 28, 2026 |

**How to use this guide.** Follow the numbered procedures in order. Screenshots show the current local test build; labels may move slightly after WordPress, theme, or plugin updates.

## Contents

1.  Access and publishing standard

2.  Newsletter and mailing-list form

3.  Data Centre

4.  Learn

5.  Resources

6.  Case Studies

7.  Forums

8.  Quality assurance and escalation

## 1. Access and publishing standard

Open /wp-admin/ and use the menu for your assigned role. The visible menu can differ by role; contact an administrator if a required area is missing.

| Work area | Typical access |
| --- | --- |
| Newsletter form | Administrator or a role specifically granted Mailchimp settings access. |
| Data Centre | GSF MEAL Officer, GSF CBF Staff, GSF Admin, or Administrator. |
| Learn | GSF Content Staff with Learn capability, course manager, or Administrator. |
| Resources and Case Studies | GSF Content Staff, Editor-equivalent role, or Administrator. |
| Forum participation | bbPress Participant. |
| Forum moderation | bbPress Moderator, Keymaster, or Administrator. |

**If access is missing.** Do not borrow another person's account. Ask an administrator to assign the smallest role or capability set needed for your duties.

### Standard publish cycle

1. Prepare the approved text, files, image, language, metadata, and owner.
1. Create or edit the item in WordPress.
1. Save as Draft and review the preview.
1. Check links, downloads, spelling, accessibility, and filters.
1. Publish only after content approval.
1. Open the public page in a signed-out view and verify the result.
### Multilingual content

- Select the correct language before publishing.
- Connect translations using the Languages panel; do not overwrite one language with another.
- Verify each language's title, body, metadata, file, and public URL.
## 2. Newsletter and mailing-list form

The current WordPress integration manages the website signup form and selected Mailchimp audience. Campaign creation and sending remain in Mailchimp.


![Mailchimp Form Settings in WordPress.](/img/guides/staff-01-newsletter.png)

*Figure 1. Mailchimp Form Settings in WordPress.*

### Configure the website signup form

1. Open Mailchimp > Form Settings.
1. Confirm the selected audience or list is GSF Hub.
1. Review Form copy, button text, and any success or error messaging.
1. Select only the audience fields the website form should collect.
1. Review the Form preview.
1. Save changes, then submit a test address from the public site footer.
### Create and send a newsletter

- Create the campaign in the connected Mailchimp account, not in the current WordPress plugin.
- Use the correct audience, subject, preview text, sender identity, and approved content.
- Send a test to internal reviewers and check desktop, mobile, links, alt text, and unsubscribe.
- Schedule or send only after approval; review delivery, opens, clicks, and unsubscribes in Mailchimp.
**Local testing.** WordPress transactional email is routed to Mailpit at localhost:8025. Mailchimp campaign sending may still require the external Mailchimp account and should not be tested with real audiences from localhost.

## 3. Data Centre

The Data Centre combines the PMF dashboard with indicators, results, disaggregation, organizations, projects, evidence, activities, Ecoequity, CSV tools, and settings.


![Data Centre dashboard and its operating menu.](/img/guides/staff-02-data-centre.png)

*Figure 2. Data Centre dashboard and its operating menu.*

### Recommended data-entry order

1. Confirm the organization exists.
1. Create or update the project and its classification fields.
1. Confirm the relevant PMF indicator exists.
1. Add or update the indicator result for the correct reporting period.
1. Add disaggregated rows that reconcile to the result total.
1. Attach supporting evidence and activity records.
1. Review the public dashboard and exported report.
### Dashboard and indicators


![PMF Indicators screen.](/img/guides/staff-10-data-indicators.png)

*Figure 3. PMF Indicators screen.*

- Dashboard summarizes indicator, result, project, evidence, and Ecoequity counts.
- Indicators define the PMF framework; change definitions only with MEAL approval.
- Indicator Results are the reported values. Check indicator, project, period, status, unit, and evidence.
### Disaggregation


![Disaggregation editor and inclusion dashboard.](/img/guides/staff-11-data-disaggregation.png)

*Figure 4. Disaggregation editor and inclusion dashboard.*

- Use Result ID to connect each row to the correct indicator result.
- Choose the category, enter a clear value label, and enter the numeric value.
- Save edits and confirm that disaggregated values do not exceed or contradict the reported total.
**Data integrity.** Do not guess missing values. Use the approved zero, not applicable, or no-data treatment for the indicator and reporting period.

### CSV import and export


![CSV import templates and export reports.](/img/guides/staff-12-data-import-export.png)

*Figure 5. CSV import templates and export reports.*

1. Download the matching import template.
1. Keep the header names and required identifiers unchanged.
1. Validate dates, numeric fields, codes, and duplicates before upload.
1. Choose the correct Import type, select the CSV file, and import.
1. Review the result message and spot-check the affected records.
1. Export the related report and reconcile totals.
**Before a large import.** Ask an administrator to confirm a recent backup and run the import on localhost or staging first.

## 4. Learn

Learn staff manage SCORM packages, course records, learner reminders, progress, and certificate appearance.


![Learner Reminders settings.](/img/guides/staff-03-learn.png)

*Figure 6. Learner Reminders settings.*

### Upload and publish a course


![Upload SCORM Package screen.](/img/guides/staff-09-upload-course.png)

*Figure 7. Upload SCORM Package screen.*

1. Open Learn > Upload SCORM Package.
1. Upload the approved SCORM ZIP package and wait for successful extraction.
1. Open Learn > Add Course or the course record created by the upload.
1. Enter the title, public description, duration, featured image, language, and SCORM package connection.
1. Keep Issue certificate after completion and feedback enabled when a certificate is required.
1. Save as Draft, preview, and test enrollment, launch, completion, feedback, and certificate.
1. Publish after the complete learner path passes.
### Monitor learner progress

- Filter by course and status: Enrolled, In progress, Completed, or Failed.
- Investigate stalled records using the learner, course, last activity, and SCORM completion status.
- Do not manually mark completion unless an approved correction procedure exists.
### Learner reminders

- Enable reminders only after outbound email is working.
- Set an inactivity period and a clear subject and message.
- Send a test reminder before enabling the schedule.
- Check Schedule status after saving.
### Certificate settings


![Certificate text, logos, and brand colors.](/img/guides/staff-14-learn-certificates.png)

*Figure 8. Certificate text, logos, and brand colors.*

- Review title, introduction, completion line, issuer, and footer.
- Use approved GSF, Canada, and Caribbean Biodiversity Fund logo URLs.
- Confirm primary and accent colors and test Print or Save PDF.
**Certificate gate.** A learner's certificate is issued only after verified course completion and submitted feedback.

## 5. Resources


![Resource Library list in WordPress.](/img/guides/staff-04-resources.png)

*Figure 9. Resource Library list in WordPress.*

### Add a resource


![Add New Resource editor and metadata fields.](/img/guides/staff-07-add-resource.png)

*Figure 10. Add New Resource editor and metadata fields.*

1. Open Resources > Add Resource.
1. Enter the title and a concise description in the editor.
1. Set Resource Type, Topic, Country/Region, Publication Year, and Source Organization.
1. Attach the resource file and/or enter the approved external URL.
1. Set a featured image and the correct language.
1. Use Featured Resource only for approved homepage or promoted placement.
1. Save as Draft and preview.
1. Publish, then verify search, filters, file download, and external links on /resources/.
**Files.** Use descriptive filenames, an accessible document format, a reasonable file size, and the approved version. Replace superseded files rather than leaving users with ambiguous duplicates.

## 6. Case Studies


![Case Studies list in WordPress.](/img/guides/staff-05-case-studies.png)

*Figure 11. Case Studies list in WordPress.*

### Add a case study


![Add Case Study editor.](/img/guides/staff-08-add-case-study.png)

*Figure 12. Add Case Study editor.*

1. Open Case Studies > Add Case Study.
1. Enter a specific outcome-focused title and the approved narrative.
1. Set Case Study Type, Focus Area, Country/Region, and Implementation Date.
1. Enter Lead Organization and Partners.
1. Set a featured image and meaningful image alternative text in the Media Library.
1. Use Featured Case Study only with editorial approval.
1. Select the correct language and connect translations.
1. Preview, publish, and verify the listing card, filters, and detail page.
### Editorial checklist

- The title names the place, intervention, or outcome.
- Claims and numbers have an approved source.
- People are represented with consent and appropriate safeguarding.
- The story identifies reusable lessons, not only activities.
- Partner names, dates, and geography are consistent with Data Centre records.
## 7. Forums

Forum staff organize spaces and moderate topics and replies. Community members create discussions from the public site.


![Forums list and bbPress administration.](/img/guides/staff-06-forum.png)

*Figure 13. Forums list and bbPress administration.*

### Manage forum spaces

- Use Forums to edit the title, description, order, status, and visibility of each forum.
- Avoid renaming or deleting a live forum without checking links and moving its topics.
- Use closed or private status only when the access decision is approved.
### Moderate topics

- Filter by forum and search by title or author.
- Open a topic to review content, author, tags, status, and forum assignment.
- Move misplaced topics, edit tags, close resolved threads, or mark spam according to policy.
### Moderate replies

- Review the parent forum and topic before changing a reply.
- Use Trash or Spam only when the moderation policy supports it.
- Escalate threats, harassment, sensitive personal information, or safeguarding concerns immediately.
**Forum authority.** GSF Content Staff plus bbPress Participant can participate but cannot fully moderate. Moderation requires bbPress Moderator, Keymaster, or Administrator.

## 8. Quality assurance and escalation

### Public verification checklist

- Correct title, language, date, owner, and featured image.
- No draft notes, localhost-only text, broken shortcodes, or placeholder content.
- Links and downloads open the intended destination.
- Search and filters return the new or updated item.
- Mobile layout is readable and controls remain usable.
- Images have meaningful alternative text; headings follow a logical order.
- Restricted data, private contact details, and internal files are not public.
### Escalate to an administrator when

- A menu is missing or a permission blocks approved work.
- A plugin, theme, update, import, backup, restore, user-role, or site-setting change is required.
- Registration, login, email, SCORM launch, certificate generation, or public data is malfunctioning.
- A bulk change or large CSV import could affect many records.
- You suspect a security, privacy, or safeguarding incident.
