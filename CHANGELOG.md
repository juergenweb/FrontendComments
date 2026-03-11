# Change Log
All notable changes to this project will be documented in this file.

## [1.0.1] - 2025-06-11

New status "featured" has been added. Can be used to create a list with selected comments only (for example for "Our customers say...")

## [1.0.4] - 2025-08-11

Completely rewriting of the code base.

## [1.0.5] - 2025-08-12

Module comments manager added.

## [1.0.6] - 2025-08-25

Configuration field for sender email address removed, because a custom sender email address could prevent emails from beeing sent on shared hosts.

## [1.0.7] - 2026-03-11

According to a user issue report [here](https://processwire.com/talk/topic/31293-new-module-frontendcomments-a-comment-module-with-a-lot-of-features-based-on-frontendforms/#comment-251985), the following buts have been fixed:

* FirstAndLastName sanitizer has been removed on author name input field, because the field has been validated wrong if the username was used. This has been fixed now
* If the site is hosted on localhost, WireMail complains about the the domain localhost is not valid. This has been fixed now by adding".com" to the domain.






