GSF Hub Expert / Account Update
Generated: 2026-06-22

Upload target:
Copy the wp-content folder in this package over the WordPress wp-content folder on the other site.

Files included:
- wp-content/themes/gsf-hub/functions.php
- wp-content/themes/gsf-hub/header.php
- wp-content/themes/gsf-hub/footer.php
- wp-content/themes/gsf-hub/template-expert-directory.php
- wp-content/themes/gsf-hub-sunrise/header.php
- wp-content/themes/gsf-hub-sunrise/footer.php
- wp-content/plugins/gsf-hub-expert-workflow/gsf-hub-expert-workflow.php

Before upload:
1. Back up the existing versions of these files on the live site.
2. Confirm the other site already has the gsf-hub theme, gsf-hub-sunrise theme, and gsf-hub-expert-workflow plugin folders.

After upload:
1. Clear site/cache plugin cache if enabled.
2. Confirm the GSF Hub Expert Workflow plugin is active.
3. Confirm Ultimate Member pages exist at /login/, /register/, and /account/.
4. Test /directory/, /expert-portal/, and /account/.

Expected behavior:
- Logged-out header shows Member Login and Create Account.
- Logged-in header shows My Account and Log out.
- Expert Directory shows Join the Expert Roster and Create Account for logged-out visitors.
- Expert Portal explains that member account registration is separate from the expert roster application.
