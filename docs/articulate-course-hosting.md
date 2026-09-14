# Articulate Course Hosting

This site can host Articulate Rise or Storyline web exports as static course packages and embed them inside WordPress or Tutor LMS content.

## Static Web Export Flow

1. Export the course from Articulate 360 as a web package when you only need the learner to view the course.
2. Unzip the package outside WordPress.
3. Copy the package contents into:

   ```text
   wp-content/uploads/articulate-courses/{course-slug}/
   ```

4. Confirm the folder contains `index.html` at the top level.
5. Embed the course in a page, post, Tutor course, or Tutor lesson with:

   ```text
   [gsf_articulate_course slug="{course-slug}" title="Course title"]
   ```

The current gender course is installed at:

```text
wp-content/uploads/articulate-courses/gender-mainstreaming-conservation/
```

Its shortcode is:

```text
[gsf_articulate_course slug="gender-mainstreaming-conservation" title="Gender Mainstreaming in Conservation Fund Management: Tools for CTFs"]
```

## Where It Fits In Tutor LMS

Use Tutor LMS for the public course card, enrollment, access rules, certificates, and learner dashboard. Add a lesson such as `Launch interactive course`, then place the shortcode in that lesson content.

This keeps the learning catalog native to WordPress while letting Articulate run its own HTML, JavaScript, fonts, and media from a stable static folder.

## LMS Tracking Flow

Use the Articulate LMS export when completion, score, quiz, or learner progress data needs to flow back to the LMS.

The current LMS export is:

```text
/Users/randyllpandohie/Downloads/Gender Course - LMS files-20260612T194510Z-3-001.zip
```

That package is a SCORM 1.2 export. Its manifest declares:

```text
schema: ADL SCORM
schemaversion: 1.2
launch file: scormdriver/indexAPI.html
title: Final Gender Mainstreaming in Conservation Fund Management: Tools for CTFs
```

This repo already includes `grassblade-xapi-tutorlms`, which is the Tutor LMS bridge for xAPI, SCORM, and cmi5 content. It does not currently include the required base plugin:

```text
wp-content/plugins/grassblade/grassblade.php
```

To use SCORM tracking in WordPress:

1. Install and activate Tutor LMS.
2. Install and activate GrassBlade xAPI Companion.
3. Activate Experience API for TutorLMS by GrassBlade.
4. Configure a Learning Record Store if detailed xAPI reporting is required.
5. In WordPress admin, upload the Articulate LMS ZIP as xAPI/SCORM content through GrassBlade.
6. Add the uploaded xAPI/SCORM content to a Tutor lesson or quiz.
7. Enable completion tracking for that lesson if the Tutor course should advance only after the Articulate package reports completion.

Use the static web-hosted option for simple launch/view access, and use SCORM/xAPI when completion data matters.

## Free SCORM Test With CLUEVO

CLUEVO LMS is installed locally at:

```text
wp-content/plugins/cluevo-lms/
```

Installed version:

```text
1.13.3
```

CLUEVO can be used as a free proof-of-concept for this Articulate SCORM 1.2 package. It is not a native Tutor LMS integration, so use it to validate that the SCORM package launches, resumes, completes, and stores SCORM progress data before choosing a production Tutor-compatible SCORM layer.

To test with CLUEVO:

1. Activate `CLUEVO LMS` in WordPress admin.
2. Open the CLUEVO learning management area.
3. Upload the Articulate LMS ZIP as a SCORM module.
4. Add the module to a CLUEVO course/chapter structure.
5. Enroll or grant access to a test user.
6. Launch the module, complete it, then check CLUEVO reports/progress.

CLUEVO should be treated as a test path unless it passes compatibility checks against the site’s active WordPress version, theme, user roles, and long-term reporting requirements.

## GSF SCORM Lite Proof Plugin

A GSF-specific proof-of-concept plugin has been added locally at:

```text
wp-content/plugins/gsf-scorm-lite/
```

It provides:

```text
[gsf_scorm_player slug="gender-mainstreaming-conservation-lms" title="Gender Mainstreaming in Conservation Fund Management: Tools for CTFs"]
```

The current Articulate LMS export has been installed at:

```text
wp-content/uploads/gsf-scorm-packages/gender-mainstreaming-conservation-lms/
```

The plugin adds a WordPress admin page:

```text
GSF SCORM
```

Use that page to upload future Articulate SCORM ZIP packages. The importer finds `imsmanifest.xml`, normalizes packages with an extra root folder, and extracts the course into `wp-content/uploads/gsf-scorm-packages/{slug}/`.

The frontend player defines a minimal SCORM 1.2 `window.API` object for Articulate content and stores committed learner data in a custom WordPress table:

```text
wp_gsf_scorm_attempts
```

Tracked fields include:

```text
cmi.core.lesson_status
cmi.core.lesson_location
cmi.suspend_data
cmi.core.score.raw
cmi.core.score.min
cmi.core.score.max
cmi.core.total_time
cmi.core.session_time
```

This is suitable for validating whether the package launches, resumes, and sends basic completion/progress data. It is not a certified SCORM runtime and should not be treated as a production replacement for a mature SCORM/xAPI/LRS stack until it has been tested against the exact reporting and certificate requirements.

### Expanded GSF LMS Features

The plugin now adds a GSF-specific LMS layer:

```text
Post type: GSF Courses (`gsf_course`)
Role: GSF Student (`gsf_student`)
Enrollment table: wp_gsf_lms_enrollments
Feedback table: wp_gsf_lms_feedback
Certificate table: wp_gsf_lms_certificates
```

Course editors can create a `GSF Course` post and select an imported SCORM package in the `GSF LMS Settings` metabox. The single course page handles enrollment, launch, status display, post-completion feedback, and certificate record display.

Useful shortcodes:

```text
[gsf_lms_catalog]
[gsf_lms_dashboard]
[gsf_scorm_player slug="gender-mainstreaming-conservation-lms" title="Gender Mainstreaming in Conservation Fund Management: Tools for CTFs"]
```

Recommended setup:

1. Activate `GSF SCORM Lite`.
2. Open `GSF SCORM` in WordPress admin.
3. Use `Create Gender Course` to create/update the starter course post.
4. Create a public course catalog page with `[gsf_lms_catalog]`.
5. Create a learner dashboard page with `[gsf_lms_dashboard]`.
6. Visit the Gender course page as a logged-in learner, enroll, launch the SCORM course, complete it, then check the learner dashboard.

When the SCORM package reports `completed` or `passed`, the plugin updates enrollment status to `completed` and creates a certificate record if certificates are enabled for the course. The current certificate is a printable on-page record with a certificate ID; PDF rendering can be added as the next layer.

Admins can review learner progress from:

```text
GSF SCORM > Student Progress
```

The report shows student name/email, course, enrollment status, SCORM lesson status, score, enrollment/completion timestamps, last SCORM commit, feedback rating/comments, and certificate ID.

The post-course feedback form captures the capacity-building survey: knowledge/understanding rating, planned application and sharing approach, whether expectations were met, daily-work takeaways, and suggested improvements. Structured survey answers are shown in `GSF SCORM > Student Progress`.

Certificate templates can be configured from:

```text
GSF SCORM > Certificate Settings
```

Admins can set the certificate title, intro line, completion line, issuer, footer, logo URL, primary color, and accent color. Completed learners see a styled certificate card on the course page with a `Print or Save PDF` button.

## Do Not Upload Through Media Library

Do not upload Articulate packages through the normal WordPress media library. These packages include HTML and JavaScript files, and WordPress media handling may block, rename, or flatten files that Articulate expects to stay in place.
