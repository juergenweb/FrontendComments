# Change Log
All notable changes to this project will be documented in this file.

## [1.0.1] - 2025-06-11

New status "featured" has been added. Can be used to create a list with selected comments only (for example for "Our customers say...")

## [1.0.4] - 2025-08-11

Complete rewrite of the code base.

## [1.0.5] - 2025-08-12

Module comments manager added.

## [1.0.6] - 2025-08-25

Configuration field for sender email address removed, because a custom sender email address could prevent emails from being sent on shared hosts.

## [1.0.7] - 2026-03-11

According to a user issue report [here](https://processwire.com/talk/topic/31293-new-module-frontendcomments-a-comment-module-with-a-lot-of-features-based-on-frontendforms/#comment-251985), the following bugs have been fixed now:

* FirstAndLastName sanitizer has been removed on author name input field, because the field was validated incorrectly if the username was used. This is fixed now.
* If the site is hosted on localhost, WireMail complains that the domain localhost is not valid. This is fixed now by adding ".com" to the domain.

## [2.0.0] - 2026-09-24

* New: Reminder email to the moderator(s) if a comment is still waiting for approval after a configurable number of days (each comment is reminded about only once).
* Fixed: The "View the comment" link was missing from the alert box after changing a comment's status to "approved" via the remote link when no notification checkbox was configured.
* Fixed: Jumping to a comment via a `#comment-{id}` anchor link sometimes landed at the wrong scroll position on the first page load, caused by a browser timing issue with images still loading.
* New: Documented how to override the default comment template files on a per-site basis without touching the module's own files.
* Improved: Redesigned the look of the applied filter tags in the comments manager (proper pill-shaped tags, crisper close icon).
* Requirements: Minimum required FrontendForms version raised to >=3.0.2.
* Improved: JavaScript files now run inside their own namespace object instead of declaring plain global functions, avoiding naming collisions with other scripts on the same page.
* Fixed: Two hardcoded, non-translatable output texts in the JavaScript files (the reply-form-loading error message and the "unsaved changes" warning in the comments manager) are now translated via PHP and passed to JavaScript.
* Numerous other smaller bugs have been fixed.
* Added a PHPUnit-based unit test suite (213 tests) covering the module's core logic, and ran it against every change in this release.
