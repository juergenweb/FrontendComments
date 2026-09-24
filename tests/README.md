# FrontendComments – Unit Test Suite

Tests the recently fixed security vulnerabilities as regression tests, without requiring an
actual ProcessWire installation or database.

## How it works

`tests/Support/ProcessWireStubs.php` and `tests/Support/FrontendFormsStubs.php` contain
lightweight reproductions of the ProcessWire core classes (`Wire`, `WireData`, `WireArray`,
`Sanitizer`, `Field`, `Page`, `Config`, `Database` …) and of the external FrontendForms classes
(`Tag`, `TextElements`, `Form`, `Link`, `Alert`) that this module requires. Two methods were taken
near-verbatim from the real ProcessWire core (not reinvented), because the fixes depend precisely
on their behavior: `Sanitizer::entities()` and `Sanitizer::selectorValue()`
(wire/core/Sanitizer.php, https://github.com/processwire/processwire).

The actual tests then load the **real, unmodified** module files
(`FrontendComment.php`, `FrontendCommentArray.php`, `FrontendCommentForm.php`,
`FrontendComments.php`, `FrontendCommentPagination.php`, `Notifications.php`,
`FieldtypeFrontendComments.module`, `InputfieldFrontendComments.module`) and call their (partly
protected) methods via reflection - so it is actually your production code being tested, not a
reimplementation of it.

## Setup

There are two ways, depending on how "I already have PHPUnit" looks for you.

### Option A: You have a globally working `phpunit` command

(e.g. via a Linux package manager, or PHPUnit is already somewhere in the PATH.)

```bash
cd FrontendComments-main
phpunit
```

`phpunit.xml` already points to `tests/bootstrap.php` and to `tests/Security`. If your PHPUnit
version does not automatically find the `phpunit.xml`:

```bash
phpunit --bootstrap tests/bootstrap.php tests/Security
```

### Option B: `vendor\bin\phpunit` (Composer-based, common on Windows)

If there is not yet a `vendor` folder in this module directory (error "The system cannot find the
path specified" when calling `vendor\bin\phpunit`), PHPUnit simply hasn't been installed here yet
- the `composer.json` included in the zip fixes that:

1. Make sure [Composer](https://getcomposer.org/download/) is installed:
   `composer -V` should print a version number, not an error.
2. Run this inside the module folder (where `composer.json` now also lives):
   ```
   composer install
   ```
   This downloads PHPUnit into `vendor\` and creates `vendor\bin\phpunit.bat`.
3. After that:
   ```
   vendor\bin\phpunit
   ```

If `composer` itself cannot be found, there is a third option without Composer: download the
standalone `phpunit.phar` file directly from https://phar.phpunit.de (e.g.
`phpunit-10.phar` for PHP 8.1+) and run it with:
```
php phpunit.phar --bootstrap tests\bootstrap.php tests\Security
```

Whichever way you choose: **no ProcessWire installation and no database** are needed, only PHP
(and, for option B, Composer). This setup was tested against PHPUnit 9.6 under PHP 8.4; PHPUnit
10/11 (which `composer.json` pulls in as 10.x) should work equally well - no version-specific
syntax such as PHP attributes is used.

## What is currently covered

Each of the most recently discussed security vulnerabilities has at least one test that (a) passes
(green) with the fix in place and (b) demonstrably fails (red) when the fix is reverted (this was
verified by hand for every single test before the suite was delivered):

| File | Test | Checks |
|---|---|---|
| `FrontendCommentXssTest.php` | `FrontendComment::createCommentAuthor()`/`createCommentText()` | Author/text are entity-encoded before `setContent()` |
| `FrontendCommentArraySelectorInjectionTest.php` | `saveStatusRemote()` | `code` is quoted via `selectorValue()`; an invalid status is rejected via whitelist |
| | `saveReplyNotificationRemote()` | `email` is quoted via `selectorValue()` |
| `FrontendCommentArrayVoteEchoTest.php` | `saveVotes()` | the Ajax response only ever outputs `up`/`down`, never the raw query parameter |
| `FrontendCommentFormSelectorInjectionTest.php` | `getCommentStatusForStorage()` | `email` is quoted via `selectorValue()` |
| `NotificationsEscapingTest.php` | `renderNotificationAboutNewCommentBody()`, `renderButton()`, `renderNotificationAboutNewReplyBody()`, `renderStatusChangeBody()` | Author/text/URL are escaped before being embedded in the HTML mail; the email address is `urlencode()`d |
| `FieldtypeFrontendCommentsDeleteSpamGuardTest.php` | `deleteSpam()` | the generated SQL contains the `NOT EXISTS` guard clause against comments with replies |
| `FieldtypeFrontendCommentsPermissionGuardTest.php` | `noEMailWarning()` | warning only for superusers, not for every logged-in frontend user |

**Deliberately not covered** (see the docblocks in the respective test classes): the actual
ProcessWire selector-to-SQL translation (that would require a real PW installation + DB - the
tests instead check the generated selector string directly) and `deleteSpam()`'s DB behavior
end-to-end (this was verified once, manually, against a real MariaDB instance with test data, see
the comment in the test file).

### `tests/Core` – tombstone rendering (not a security topic, but the originally reported
display regression: a parent comment marked as spam, with still-visible replies, disappeared
completely instead of being shown as a placeholder)

| Test | Checks |
|---|---|
| `TombstoneRenderingTest::testNumberOfRepliesCountsOnlyDirectChildrenOfThisComment` | `numberOfReplies()`/`hasReplies()` count only direct children, no grandchildren or siblings |
| `testHasVisibleRepliesIsTrueOnlyForApprovedOrFeaturedReplies` | `hasVisibleReplies()` is only true for approved/featured replies, not for pending/spam |
| `testIsSpamPlaceholderIsTrueOnlyForSpamStatus` | `isSpamPlaceholder()` |
| `testOrdinaryApprovedCommentRendersItsRealContentUnchanged` / `testSpamCommentSuppressesIdentityAndShowsPlaceholderNoticeInstead` | `buildSpamPlaceholderVars()`/`renderCommentTemplate()` render via the **real** `templates/comment.php` - author/avatar/text are suppressed for spam and replaced with a neutral notice text |
| `testRenderCommentTemplateReturnsFallbackWhenNoTemplateFileIsConfigured` / `...ThrowsWhenAConfiguredTemplateFileIsMissing` | the two edge cases in `renderCommentTemplate()` |
| **`testSpamParentWithAnApprovedReplyStaysInTheTreeAsAPlaceholderWithItsReplyBelowIt`** | **the originally reported bug**: the parent comment remains as a placeholder, the reply remains visible below it |
| `testSpamParentWithoutAnyVisibleReplyIsRemovedFromTheTreeEntirely` / `...WithOnlyASpamReplyIsRemovedFromTheTreeEntirely` | spam without visible replies still disappears completely (no placeholder overkill) |
| `testOrdinaryApprovedThreadIsUnaffectedByTheSpamHandling` | a normal, multi-level thread without spam remains correct and unchanged |
| `testPendingAndPlainRejectedCommentsNeverAppearInTheTreeAtAll` | unmoderated comments never appear in the tree |

The most important test here is `testSpamParentWithAnApprovedReplyStaysInTheTreeAsAPlaceholderWithItsReplyBelowIt`:
it was run, as a trial, against the original (buggy) version of
`FrontendComments::getCommentListArray()` and, as expected, failed (0 instead of 2 comments in the
tree) - this is exactly the bug reported at the start of this project.

### `tests/Core` – further tests, file by file

In addition to the security and tombstone tests above: for every module file, a targeted search
was made once more for methods with real (non-trivial) logic - validation, computation, case
distinctions - and tests were added for them. Pure pass-through setters (e.g.
`setLoginRequired()`) or methods that merely assemble backend markup (`___render()` in
`InputfieldFrontendComments.module`) were deliberately left out - the effort of stubbing a dozen
ProcessWire Inputfield types is disproportionate to the business-logic risk there.

| File | Test | Checks |
|---|---|---|
| `FrontendCommentTest.php` | `isPublished()` | only approved/featured count as published |
| | `getPreviousCommentStatus()` | reads the dedicated `old_status` property maintained for this purpose (see the docblock of the real method: `getChanges(true)` never works for this, since this module does not enable `trackChangesValues`) |
| | `formIsAjaxLoaded()` / `formIsSubmitted()` | Ajax detection based on `commentid`/Ajax flag; POST key format `reply-form-{id}-ajax-{pageid}-comments-{id}` |
| | `getFormattedCommentCreationDate()` | absolute vs. relative date format depending on the configuration value |
| | `___renderStarsOnly()` | whole/half/empty stars for all rating levels (0, null, integer, .5 value, full) |
| `FrontendCommentArrayTest.php` | `setModeration()` | accepts only 0/1/2, otherwise throws an exception |
| | `getModerationEmail()` | all three cases: `custom` list, `text` (split line by line), any other field (unchanged) |
| | **`deleteComment()`** | **guard**: a comment that still has replies is not deleted (`null` instead of `bool`); a leaf comment without replies is removed correctly |
| `FrontendCommentFormTest.php` | `getCommunityGuidelinesURL()` | disabled → `false`; internal page → its URL; external URL → value unchanged **and** the link is marked `rel="nofollow"` (only for external URLs, not for internal ones) |
| `NotificationsTest.php` | `replaceKey()` / `replaceValue()` | pure array helper functions for renaming/replacing form values before sending mail |
| | `getSenderName()` | reads the configured sender name, empty if not set |
| | `getCommunityGuidelinesURL()` | as above, additionally with multi-language support: a language-specific external URL is preferred, with a fallback to the default URL |
| `FrontendCommentsTest.php` | Constructor logic for the headline | explicit text is used as-is; empty/`null` → default text "Comments"; **`"none"`** → the headline is suppressed entirely (not just rendered empty) - this is the `$headlineSuppressed` distinction documented in the code |
| `FrontendCommentPaginationTest.php` | `paginationPagesNumber()` | rounding up to full pages; exact division; division guard when pagination is disabled (no `numCommentsPage` of 0) |
| | `createPageOfPageText()` | "Showing x to y of z" for the first/middle/last page; **deliberately returns an empty string when `start === end`** (e.g. exactly 1 comment) |
| | **`getPaginationItems()`** | **the windowing algorithm that decides which page-number links are actually shown** (always current page ±2, plus separate "go to first/last page" links and "…" separators, as soon as the window no longer reaches that edge on its own) - 6 tests cover: only 1 page → no items; a few pages fit entirely inside the window (no start item, no dots); many pages show a start item, both dot separators and an end item; the window already reaches page 1 or the last page on its own (then no extra start/end item is needed); current page `0` is treated like page 1 for the window calculation |
| | `renderNavItems()` | renders exactly the items returned by `getPaginationItems()`, in the same order, concatenated together |
| `FieldtypeFrontendCommentsHelpersTest.php` | `statusTexts()` | fixes the mapping of status code → label (typos here would mislabel the backend everywhere) |
| | `groupBy()` | pure array grouping |
| | `sanitizeMultilineTextarea()` | `null` → empty array; takes only the first whitespace-separated token per line; documents the (easily overlooked) special case of a line with a leading space |
| | `findParents()` | documents the current, deliberately limited state (cannot climb further up past the given ID) |
| | `getFrameWork()` / `checkForClass()` | framework name derivation from the file name; the theme-specific class is preferred, with a fallback to the unthemed class |
| | `resolveTemplateFile()` | a site override under `site/assets/.../templates/{Theme}/` takes precedence over the module's default file, provided the override file exists |
| `InputfieldFrontendCommentsTest.php` | `___processInput()`: required fields | empty text/email/author field → warning, value remains unchanged |
| | **`___processInput()`: email/website validation** | **an invalid email or URL is rejected (error + session flag) and never written back to the comment** - documented in the code itself as a deliberate fix, since `Inputfield::error()` does not prevent saving |
| | `___processInput()`: status whitelist | a status value outside the allowed list is rejected, a valid change is applied |

### Another round: further candidates with real logic

Another pass over all the files turned up a few more methods with non-trivial logic that had
previously been deferred (mostly because of their DB or constructor dependencies). For some of
these, the stub infrastructure had to be extended (among other things, real change tracking on
`Wire` with `trackChange()`/`isChanged()`/`getChanges()` instead of a plain no-op, a `HookEvent`
stub, and `wire()`/`$this->wire()` without an argument, which now - as in real ProcessWire -
returns an object through which arbitrary API variables can be accessed as properties, e.g.
`$this->wire()->sanitizer`).

| File | Test | Checks |
|---|---|---|
| `FieldtypeFrontendCommentsSleepWakeTest.php` | `sanitizeValue()` / `getBlankValue()` | an invalid stored value is replaced with an empty array correctly wired to the Page/Field |
| | `___sleepValue()` | converts every comment into a flat array for the DB (`text` → column `data`) |
| | **`___sleepValue()`: `moderation_feedback`** | **keeps allowed HTML (bold, italic, lists, links) from the rich-text editor, but strips `<script>`** - documented as a fix in the code itself (previously, `string()` removed all markup, including markup deliberately set by the moderator) |
| | `getConfigValue()` | the field's own value takes precedence, otherwise falls back to `getDefaultData()` |
| | `queueTableHasClaimedColumn()` | schema detection based on the number of columns |
| `FieldtypeFrontendCommentsCorrectStatusValuesTest.php` | **`correctStatusValues()`** | **the core logic of the hook that validates status changes in the backend before saving** - see below |
| `FrontendCommentArrayLookupsTest.php` | `getLastID()` | explicitly casts the PDO result (string) to `int`; `null` for an empty table; `null` on a DB error |
| | `getCommentPage()` | correctly determines the pagination page of a comment based on its position in the sorted tree; `1` if pagination is disabled |

**`correctStatusValues()`** deserves its own explanation, because the test reproduces a real bug
documented in the code itself: two different blocks ("reset to pending" and "delete") used to
share a single `hasReplies()` check. However, that check only counted *visible* (approved/featured)
replies - a comment with only pending or spam replies slipped through the check in both cases,
even though "delete" should never have been allowed to go through in that case (it physically
removes the database row, regardless of the reply's status). The fix splits this into two separate
guards: `hasVisibleReplies()` for resetting to pending (since only visible replies can actually be
orphaned), and `hasReplies()` for deleting (stricter, since *every* reply still references the row
via `parent_id`). The test `testDeletingIsBlockedWhenAnyReplyStillExistsEvenAMerelyPendingOne`
checks exactly the case that used to slip through.

### The notification queue (`fc_comments_queues`)

At explicit request, as its own block: the methods around the queue table from which notification
emails are sent to subscribers.

| File | Test | Checks |
|---|---|---|
| `FrontendCommentQueueTableTest.php` | `addCommentToQueueTable()` | nothing is entered if nobody wants to be notified; the comment's own author is excluded from the recipient list |
| | **`addCommentToQueueTable()`: check+insert per recipient** | **regression test for a fix documented in the code itself**: the "is this recipient already in the queue?" check used to run only once, after the loop, and therefore only ever saw the last recipient - every other recipient was never checked, while the (string-concatenated) INSERT still blindly inserted all recipients anyway. Now the check runs per recipient, and each one is inserted individually (with bound parameters) |
| | `deleteEntriesInQueueTable()` | deletes exclusively the rows for this one comment (WHERE clause on `comment_id`/`field_id`/`page_id`) |
| | **`deleteEmailsInQueueTable()`** | **regression test for a fix documented in the code itself**: the DELETE on unsubscribe used to be restricted only to the email address, and therefore accidentally also deleted other, independent, still-valid queue entries of the same person on other pages/fields; now additionally restricted to `page_id` and `field_id` |
| | **`getCommentIDFromDatabase()`** | **regression test for a bug found and fixed in this session**: the method did not explicitly cast the database's `id` result to `int` (unlike its sister method `getLastID()`, which documentedly does). Under `declare(strict_types=1)` and the return type `int\|null`, this led to a `TypeError` as soon as the database (as is typical for PDO with emulated prepares) returned the value as a string instead of an integer |
| `FieldtypeFrontendCommentsUpdateTablesTest.php` | `updateTables()` | the save hook that keeps the queue/votes tables in sync: a comment set to approved/featured is entered into the queue; a status change to pending/spam clears the queue+votes; a newly created, published comment is looked up via `getCommentIDFromDatabase()` and likewise entered; if unpublished, it is only looked up; without a matching DB row the ID remains unchanged; removed comments (`getItemsRemoved()`) clear the queue+votes; `notificationStop=1` specifically triggers `deleteEmailsInQueueTable()` |
| | **`updateTables()`: approved/featured -> queue, newly created + published -> queue** | **regression tests for a bug found and fixed in this session**: `addCommentToQueueTable()` was declared `protected`, but was called directly by `updateTables()` - an unrelated class. This is a plain PHP visibility violation and, in practice, caused a fatal error on virtually every "approve comment" save operation (confirmed against the then-unmodified source code, see below) |

`sendQueuedEmails()` (the LazyCron job that actually works through the queue and sends the mails)
is deliberately not covered: at the end, it builds a complete, real `FrontendComment` via its
(heavy) constructor in order to pass it to `Notifications::sendNotificationAboutNewReply()` - the
same reason `___wakeupValue()`/`getCommentByID()` were left out elsewhere in this suite.

**Two bugs newly found in this round were fixed directly, on request** (both fixes verified as
usual: the fix was briefly reverted, it was confirmed that the corresponding test then fails, and
the original file was restored):

1. `FrontendComment::getCommentIDFromDatabase()` now explicitly casts the `id` result to `int`,
   exactly as its sister method `getLastID()` already did.
2. `FrontendComment::addCommentToQueueTable()` is now `public` instead of `protected` - the method
   is called by `FieldtypeFrontendComments::updateTables()`, a class unrelated to it, which used to
   be a PHP visibility violation. Since `updateTables()` actually runs via
   `addHookAfter('FieldtypeMulti::savePageField', ...)` on every page save with a comment field,
   this would, in practice, have caused a fatal error on every approval of a comment (approved or
   featured) as well as on every newly saved, already-published comment - the core path of the
   notification queue.

For the new `getPaginationItems()` tests, the `Link` stub in
`tests/Support/FrontendFormsStubs.php` had to be improved once: `Link::render()` had so far
completely ignored `setAttribute()` values (e.g. `aria-current`, which marks the current page),
even though `Tag::render()` (the base class that `TextElements` also inherits from) had long since
output them correctly - without this correction, the new tests could never have distinguished
between the current page and a normal page link.

### Double opt-in for notification emails

Newly implemented (at explicit request, after discussing the data-protection side of the topic):
before notification emails about new replies/comments are actually sent to an email address
entered while commenting, the owner of that address must first confirm it via a confirmation link
in a separate email. Background: while the "notify me" choice is made by the commenter, the
entered email address itself was never previously verified - someone could (accidentally or
deliberately) enter someone else's address, whose owner would then receive unsolicited mail. A
plain unsubscribe link (which already exists via `saveReplyNotificationRemote()`) does not fix
this, because without prior confirmation, an unsolicited first email would already have been
delivered in the first place.

Implementation:

- New column `notification_confirmed` (tinyint, default 0) in
  `FieldtypeFrontendComments::getDatabaseSchema()` - migrated automatically by ProcessWire's
  Fieldtype mechanism like all other columns of this table, no manual `___upgrade()` change needed
  (unlike the separately managed `fc_comments_queues` table).
- `FrontendComment::addCommentToQueueTable()`'s recipient query now additionally requires
  `notification_confirmed=1` in both branches of the WHERE clause (both for "all comments" and for
  "replies to your own comment").
- New method `Notifications::sendNotificationConfirmationMail()` (incl.
  `renderNotificationConfirmationBody()`) sends the confirmation email. Deliberately kept
  content-free/anonymous: no author name, no comment text, no page info - after all, whoever
  receives this email might not actually be the real author, and should therefore learn as little
  as possible about someone else's comment. The confirmation link uses the same 120-character
  `code` already used for the status-change/spam links.
- New method `FrontendCommentArray::confirmNotificationRemote()` (following the same pattern as
  `saveStatusRemote()`/`saveReplyNotificationRemote()`: read the code from the query string, look
  it up protected by `selectorValue()`, check the state) sets `notification_confirmed=1` as soon as
  the link is clicked. It is hooked into `render()` between `saveReplyNotificationRemote()` and the
  end of remote-link processing.
- `FrontendCommentForm.php` triggers sending the confirmation email directly after successfully
  saving a new comment, provided a notification was requested at all
  (`notification !== flagNotifyNone`).

| File | Test | Checks |
|---|---|---|
| `FrontendCommentQueueTableTest.php` | **`addCommentToQueueTable()`: double opt-in** | **regression test**: both branches of the recipient query contain `notification_confirmed=1` |
| `FrontendCommentArrayNotificationConfirmationTest.php` | `confirmNotificationRemote()` | without a `confirmnotification` parameter, nothing happens; no matching comment → warning; the comment never requested a notification (`flagNotifyNone`) → warning, nothing is confirmed; already confirmed → warning; a valid, not-yet-confirmed request is confirmed and saved |
| `FrontendCommentArraySelectorInjectionTest.php` | **`confirmNotificationRemote()`: selector injection** | the same protection as `saveStatusRemote()`/`saveReplyNotificationRemote()`: the `code` parameter is protected by `selectorValue()` before being used in the selector string |
| `NotificationsTest.php` | `renderNotificationConfirmationBody()` | correctly builds the confirmation link from the page URL and `code`; reveals nothing about the actual comment content (author, text, email address do not appear in the mail body) |

`Notifications::sendNotificationConfirmationMail()` itself - like `sendNotificationAboutNewReply()`,
`sendStatusChangeEmail()` and `sendModerationNotificationMail()` too - is deliberately not tested
directly: the `WireMail` stub returns only `$this` for every method call (incl. `send()`) via
`__call()`, which under `declare(strict_types=1)` would violate the declared `int` return type if
the method were actually executed. Only the render half (`renderNotificationConfirmationBody()`)
is therefore meaningfully testable - exactly the same pre-existing limitation as for the other
mail-sending methods in this file.

### Addendum: missing migration of the new column on existing installations (module version 1.0.8)

After the double opt-in change above was deployed, this error occurred in practice when deleting a
comment in the FrontendCommentManager:

```
FieldtypeFrontendComments: SQLSTATE[42S22]: Column not found: 1054 Unknown column
'field_comments.notification_confirmed' in 'field list'
```

**Cause:** `getDatabaseSchema()` does describe the new column, but ProcessWire only automatically
reconciles the schema of a table managed by a Fieldtype (such as `field_comments`) when the FIELD
itself is saved (Setup > Fields > comment field > Save) - not on every request, and not
automatically just because of the new module version. On an existing installation that received
the updated code without the field being saved again, the column simply did not yet exist in the
real database. Every query that references it (`FrontendComment::addCommentToQueueTable()`) then
failed - visibly on deletion, because deleting a comment internally triggers
`FieldtypeMulti::savePageField()`, which in turn runs the `updateTables()` hook for the remaining
comments and can call `addCommentToQueueTable()` again in the process.

**Fix:** `FieldtypeFrontendComments::___upgrade()` (the same method that already retrofits the
`claimed` column of the queue table) now additionally runs over **all** fields on the site whose
type is this Fieldtype, and adds `notification_confirmed` to each one's own table if it is still
missing there (`SHOW COLUMNS ... LIKE`, the same detection mechanism as
`queueTableHasClaimedColumn()`). The module version was raised to **1.0.8** for this, so that
ProcessWire automatically triggers the upgrade hook on the next module refresh - a newly created
comment field gets the column automatically from `getDatabaseSchema()` anyway.

**To resolve the acute error on your site, either of the following is enough:**

1. Deploy this updated version and run *Modules > Refresh* once in ProcessWire (triggers the
   upgrade hook that automatically adds the column for all comment fields), or
2. open and save the affected comment field once in Setup > Fields (even without any actual
   change - this triggers ProcessWire's own, independent schema reconciliation for that one
   field), or
3. directly via SQL: `ALTER TABLE field_comments ADD COLUMN notification_confirmed tinyint(3) NOT NULL DEFAULT 0;`
   (adjust the table name if necessary, in case your comment field is named something other than
   "comments").

To actually cover this via PHPUnit, the `Database` stub in `tests/Support/ProcessWireStubs.php`
had to be extended with `exec()` (logs the DDL SQL executed), `tableExists()` (with a settable
override), and `escapeTable()` - previously these three methods were not reproduced there at all,
which is why `___upgrade()` itself had so far been completely untested (only the helper method
`queueTableHasClaimedColumn()` had tests). The `Fields` stub was additionally made iterable
(`IteratorAggregate`), and `Field` was given a `type` property, so that `___upgrade()`'s new
`foreach ($this->wire('fields') as $field)` loop is testable.

| File | Test | Checks |
|---|---|---|
| `FieldtypeFrontendCommentsSleepWakeTest.php` | `fieldTableHasNotificationConfirmedColumn()` | column detection based on the row count, analogous to `queueTableHasClaimedColumn()` |
| | **`___upgrade()`: 1.0.8 migration** | **regression test for the bug described above, which actually occurred in practice**: a comment field whose table does not yet have the column is extended via `ALTER TABLE`; a field of a different type is skipped entirely; a field whose table already has the column is not altered a second time; a field whose table does not (yet) exist at all is left untouched |

A second, related bug came to light during the same debugging session:
`FieldtypeFrontendComments::___sleepValue()` (builds the flat arrays from which every comment row
is rewritten on save) had simply forgotten to include `notification_confirmed`. Without this line,
`$comment->set('notification_confirmed', 1)` in `confirmNotificationRemote()` had **no effect
whatsoever on the database** - every subsequent save operation (of any kind) would have silently
reverted the column back to its original value when the row was rewritten, since `savePageField()`
completely rebuilds every comment row from exactly this array.

| File | Test | Checks |
|---|---|---|
| `FieldtypeFrontendCommentsSleepWakeTest.php` | **`___sleepValue()`: `notification_confirmed`** | **regression test for the bug described above, found in practice**: the column is now included in the result array and correctly cast to `int` |

### Another, more serious bug: all remote links via code/email were practically never functional

While debugging the "no matching comment was found" problem above (which also occurred with the
already existing "mark as SPAM" link, not only with the new confirmation link), a considerably
larger, **pre-existing** bug turned up that has nothing to do with the double opt-in change
itself, but was noticed for the very first time because of it:

`saveStatusRemote()`, `saveReplyNotificationRemote()` (both already present before this engagement
series) and `confirmNotificationRemote()` (new) each look up a comment via
`$this->wire('sanitizer')->selectorValue($code)` or `...($email)` respectively, before the value is
inserted into a selector string - that is the correct protection against selector injection. What
was overlooked, however: `selectorValue()` has its own `maxLength` option with a **default value
of 100**. But a comment's `code` value is 120 characters long (`FrontendCommentForm.php`:
`$random->alphanumeric(120)`) - 20 characters more than the default. As a result, `selectorValue()`
**silently truncated the code to its first 100 characters** before it was inserted into the
selector string. The resulting selector then asked for a comment whose `code` equals this
100-character prefix - which never matches the actually stored, full 120-character code, no matter
how exactly the code in the link matches the database. Result: **every** one of these remote links
(approve, mark as SPAM, unsubscribe from notifications, and now also the new confirmation link)
reliably failed with "no matching comment was found for this code" - this bug was reproduced and
confirmed live on your own test installation, not just derived theoretically.

**Fix:** at all three affected spots in `FrontendCommentArray.php`, an explicit, sufficiently large
`maxLength` is now passed - `120` for the two `code`-based lookups (matching the actually generated
length), `255` for the `email`-based lookup in `saveReplyNotificationRemote()` (matching the
`varchar(255)` column width in `getDatabaseSchema()` - a real email address will hardly ever be
100+ characters long, but the column allows it, so the same class of bug was fixed here too, as a
precaution).

The already-existing selector-injection tests (`FrontendCommentArraySelectorInjectionTest.php`)
never found this bug, because they work exclusively with short, manipulated codes/emails (well
under 100 characters) - a short value is not changed at all by the truncation to 100 characters,
and therefore still happens to "match" correctly by coincidence. The new tests below therefore
deliberately use a code that is **exactly 120 characters** long, or an email address over 100
characters long, to reliably cover exactly this class of bug.

| File | Test | Checks |
|---|---|---|
| `FrontendCommentArrayRemoteLinkLookupTest.php` | **`saveStatusRemote()` with the full 120-character code** | **regression test for the bug described above, reproduced in practice**: a comment with a real, 120-character-long code is now found instead of being incorrectly reported as "not found" |
| | **`confirmNotificationRemote()` with the full 120-character code** | the same regression, for the new confirmation link |
| | **`saveReplyNotificationRemote()` with a long email address** | the same class of bug, also proactively fixed for the email-based unsubscribe link |

## Confirmation email only once per comment field + page (instead of with every comment)

Changed at explicit request: the confirmation email for notifications (`notification_confirmed`,
see above) is no longer sent with every single comment, but only the first time a particular email
address requests a notification **on this comment field, on this page**. Two exceptions mean that
no email is needed at all:

- **The user is already logged in** when writing the (first) comment: the email field is then
  pre-filled, read-only, directly from the user account (`___getEmailField()`), so the address is
  already unambiguously assigned - a confirmation here would be a pure formality.
- **The same email address has already confirmed once for the same field on the same page** (an
  earlier comment with `notification_confirmed=1`). If the same comment field is also used on a
  DIFFERENT page, a new confirmation is required there again - this follows automatically from the
  fact that `FrontendCommentForm::$comments` only ever contains the comments of the current field
  on the current page anyway (ProcessWire loads a separate, dedicated `FrontendCommentArray` per
  field+page).

Implemented in `FrontendCommentForm.php`:

- New method `notificationAlreadyConfirmedForEmail(string $email): bool` - checks, via
  `$this->comments->find('email=..,notification_confirmed=1')`, whether there is already a
  confirmed comment for this address on this field/page (the email is protected here via
  `selectorValue()`, as at the already-existing spots in this file).
- New method `notificationConfirmationCanBeSkipped(FrontendComment $newComment): bool` - combines
  both exception cases mentioned above (logged in OR already confirmed) into a single decision.
- This decision is made **before** the first `saveComment()` call: if it is `true`,
  `notification_confirmed` is set directly to `1`, so the new comment is saved correctly right
  away (no second write needed).
- The confirmation email (`sendNotificationConfirmationMail()`) is then only sent if
  `notification_confirmed` is not already `1` at this point.

| File | Test | Checks |
|---|---|---|
| `FrontendCommentFormTest.php` | `notificationAlreadyConfirmedForEmail()` (5 tests) | no comments present, address never confirmed, a different address already confirmed, the same address already confirmed, empty address |
| | `notificationConfirmationCanBeSkipped()` (3 tests) | logged-in user on the very first comment, guest on the very first comment (must be `false`), guest with an already confirmed address on the same field/page |

## Bug (fixed, external to FrontendForms): "No notification" (value "0") failed form validation

A real bug, reported by a user: when "No notification" (value `"0"`,
`FrontendComment::flagNotifyNone`) was deliberately selected on the notification radio field,
validation failed with the message *"The email notification contains invalid value."* - even
though this is a perfectly normal, valid option of the field.

**The cause was NOT in this module, but in the (externally included) FrontendForms library**:
`Inputfields::setRule()` passes every array argument of a validation rule through its own
`array_filter_recursive()` helper function, which internally uses PHP's normal `array_filter()` -
and that, by default, removes every array VALUE that PHP classifies as "falsy", among them the
string `"0"`. The rule `$this->notify->setRule('in', $allowedValues)` (with, e.g.,
`$allowedValues = ['0', '1']`) passes exactly such an array - so `"0"` was silently removed from
the list of allowed values already when the rule was registered, long before any form was ever
submitted. Every selection of "No notification" therefore inevitably had to fail.

**Fix: fixed directly in FrontendForms** (by the user themselves, after consultation) -
`array_filter_recursive()` now only filters out genuine `null` values instead of every "falsy"
value, so `"0"` (and, e.g., `0`/`false` as a rule parameter too) is preserved. A workaround
initially built into this module to bypass the `"in"` rule via a `"regex"` rule
(`allowedNotificationValuesPattern()` in `FrontendCommentForm.php`, including 4 of its own tests)
was subsequently reverted - the original, simpler `setRule('in', $allowedValues)` is now back in
effect, since the actual root cause (in FrontendForms) has been eliminated.

## Bug: "quiet_save" had no effect when editing a comment directly in normal page edit

Checked on request whether `input_fc_quiet_save` ("quiet save": when saving a comment, the page's
`modified`/`modified_users_id` in the `pages` table should NOT change) really takes effect on
every save path (frontend AND backend). Result: it already worked correctly at two of three spots,
but not at one genuine, actually usable backend spot.

**Save paths checked:**

1. **Frontend comment form** (`FrontendCommentForm::render()`): its own SQL `UPDATE pages...`
   statement, already correctly guarded with `if (!$this->field->get('input_fc_quiet_save'))`. ✓
2. **Backend via the Comments Manager** (`FrontendCommentsManager.module` →
   `FrontendCommentArray::saveComment()` → `Fieldtype::savePageField()`): this path never calls
   `Pages::save()` and therefore never touches `pages.modified` anyway - `quiet_save` is therefore
   not needed at all here, the behavior has always been "quiet". ✓
3. **Backend via normal page editing** (Setup > Pages > Edit, when the "use Comments Manager"
   option is OFF): `InputfieldFrontendComments` then shows the comments directly as editable
   fields inside normal page editing (text, email, author, status, homepage, moderation feedback -
   including the ability to delete a comment there via the "Delete the comment" status). If the
   page is saved there via the normal "Save" button, this goes through ProcessWire's own
   `Pages::save()` - and that fundamentally ALWAYS updates `modified`/`modified_users_id`, unless
   the `['quiet' => true]` option is explicitly passed yourself. This module's own
   `input_fc_quiet_save` flag previously had no influence whatsoever on this save operation, which
   is controlled by ProcessWire itself. **✗ Bug.**

**Fix**: new hook `quietPageEditSaveForCommentsOnly()` in `FieldtypeFrontendComments.module`,
registered on `Pages::save` (a before-hook, can modify `$options` before the actual save happens).
The logic:

- For every `FrontendComments` field on the page, it is checked whether its `FrontendCommentArray`
  has any change at all (`isChanged()` - the same flag that
  `InputfieldFrontendComments::___processInput()` and `correctStatusValues()` already set via
  `trackChange('statuschange'/'update'/'remove', ...)`).
- If there was no comment activity at all → do nothing (a normal click on "Save" without any
  comment change is left untouched).
- If there was comment activity, but the affected field does NOT have `quiet_save` enabled → do
  nothing (normal behavior is preserved for this field).
- If there was comment activity and `quiet_save` is active → additionally check whether ANY other
  field on the page was changed in the same save operation (`$page->getChanges()`). If so (e.g.
  the page title was also changed in the same edit) → do nothing, `modified` is updated as usual,
  since this is then no longer a pure comment action.
- Only if there is exclusively comment activity on `quiet_save` fields, and nothing else on the
  page was changed → set `$options['quiet'] = true`, so that ProcessWire itself leaves
  `modified`/`modified_users_id` untouched.

This condition ("only suppress if exclusively comments were changed") was chosen deliberately
(confirmed on request), so that a simultaneous, genuine edit to another page field still updates
the timestamp completely normally.

*Side finding (no code change)*: `FrontendComment::updateComment()` also checks
`input_fc_quiet_save` correctly, but is not called anywhere in the entire module (dead code) -
therefore has no effect on actual behavior.

| File | Test | Checks |
|---|---|---|
| `FieldtypeFrontendCommentsQuietSaveTest.php` | `quietPageEditSaveForCommentsOnly()` (5 tests) | no comment activity → not forced, pure comment activity on a `quiet_save` field → forced, field without `quiet_save` → not forced, an additionally changed other field → not forced, options with `quiet` already set are left untouched |

## Addendum: "quiet_save" retrofitted in the Comments Manager (backend) too

Clarified on request: via the **Comments Manager** (`FrontendCommentsManager.module`),
`modified`/`modified_users_id` in the `pages` table **never** changed so far - regardless of
whether `quiet_save` was switched on or off. Reason: this path saves directly via
`FrontendCommentArray::saveComment()`/`deleteComment()` → `Fieldtype::savePageField()`, which
never calls `Pages::save()` and therefore fundamentally never touches the `pages` table. Unlike
the frontend form (which correctly updates `modified` when `quiet_save` is switched off),
`quiet_save` in the Comments Manager therefore had no effect at all so far - "off" behaved exactly
like "on".

**Fix**: new method `updatePageModifiedUnlessQuiet(Page $page, Field $field)` in
`FrontendCommentsManager.module`, which - following exactly the same pattern as the
already-existing block in `FrontendCommentForm::render()` - executes its own
`UPDATE pages SET modified_users_id=..., modified=CURRENT_TIMESTAMP() WHERE id=...` statement,
unless the affected field has `quiet_save` enabled. It is called at all four places where this
module saves or deletes a comment (`processEditForm()` for single-item editing, the bulk
processing in `___processInput()`), and in each case **only on actual success** - a deletion
refused because replies still exist, for instance, must not touch `modified`.

This makes `quiet_save` behave consistently across all three save paths now: "on" keeps `modified`
unchanged everywhere (frontend, Comments Manager, normal page editing), "off" updates it
everywhere, exactly as the frontend form has always done.

*Technical side note for this test setup*: in order to be able to test
`FrontendCommentsManager.module` via reflection at all, a minimal `Process` stub (the base class
of this module) and a `class_alias(Database::class, WireDatabasePDO::class)` (the module declares
its own `$database` property with this type) were added - both purely on the test side, with no
effect on the production code.

| File | Test | Checks |
|---|---|---|
| `FrontendCommentsManagerQuietSaveTest.php` | `updatePageModifiedUnlessQuiet()` (3 tests) | `quiet_save` on → no SQL execution, `quiet_save` off → exactly one `UPDATE pages` statement with the correct page ID, setting never saved (neither on nor off) → behaves like "off" |

## Addendum: `in_array(): ... must be of type array, null given` when "changing status to approved"

User-reported error when changing a comment's status via remote link (`saveStatusRemote()` in
`FrontendCommentArray.php`, line 665). Cause: `input_fc_status_change_notification` is a
checkboxes field; if this setting has never been explicitly saved for a field (e.g. because the
field was created before this option existed), `$this->field->get(...)` returns `null` instead of
the configured default value `[]` (`FieldtypeFrontendComments::getDefaultData()`) - this default
only takes effect when rendering the admin configuration form, not on this direct read access.
Since PHP 8, `in_array()` strictly requires an array as its second argument, hence the fatal error.

**Fix**: the value is now safeguarded via an `(array)` cast, so that "never configured" is treated
the same as "nothing checked", instead of crashing. As a side effect, the previously unused
intermediate value `$statusChangeNotification` is now also reused, instead of reading the field
twice.

| File | Test | Checks |
|---|---|---|
| `FrontendCommentArrayRemoteLinkLookupTest.php` | `testSaveStatusRemoteApprovingDoesNotFatalWhenStatusChangeNotificationWasNeverConfigured` | a field without any value ever set for `input_fc_status_change_notification` → `saveStatusRemote()` must no longer end fatally |

## Addendum: pagination was not shown at all for fields that had never been explicitly saved

User-reported error: "the pagination is not shown for the comments". Cause: the same pattern as
above, this time at four spots at once in `FrontendCommentPagination.php`, all revolving around
`input_fc_pagnumber`/`input_fc_pagorientation` for a field whose pagination fieldset had never
been explicitly saved (`field->get(...)` returns `null` instead of the configured defaults
`10`/`'center'`):

1. **Constructor crash**: `numCommentsPage()` and the line that writes `input_fc_pagorientation`
   directly into `$this->alignment` assigned `null` to a non-nullable `int` or `string` property
   respectively → `TypeError`, on **every** render of the comments for such a field (the
   constructor runs on every instantiation). Since `FrontendCommentArray::render()` appends
   `renderPagination()` directly after `renderComments()` without a try/catch, this error took
   down the entire comments+pagination output with it.
2. **Duplicate gate with `null < 1`**: `___render()` additionally read `input_fc_pagnumber`
   directly once more (instead of using `$this->numCommentsPage`, which the constructor had
   already correctly defaulted) and compared it with `< 1`. Since `null < 1` is `true` in PHP, the
   pagination remained suppressed even after (1) was fixed.
3. **Incorrect framework class name resolution** (a separate, independently found bug): the name
   of the framework-specific subclass was built as `'<Framework>Pagination'` (e.g.
   "Bootstrap5Pagination"), which never exists - the real classes are named
   `'FrontendCommentPagination<Framework>'` (e.g. `FrontendCommentPaginationBootstrap5`, see
   `frameworks/*.php` and the pattern already correctly used in
   `FrontendCommentArray::getPagination()`). `class_exists()` therefore always returned `false`,
   and it silently fell back to the unthemed base markup instead of the configured framework.

**Fix**: all four spots now default to `FieldtypeFrontendComments::getDefaultData()` when the field
value is `null` (instead of a hard-coded value, so a future change to the default only needs to be
maintained in one place), the `___render()` gate now uses the already-defaulted
`$this->numCommentsPage`, and the class-name resolution now follows the same pattern as elsewhere
in the module.

*Technical side note for this test setup*: in order to run the real constructor (instead of
`newWithoutConstructor()`), the `Config` stub was given `pageNumUrlPrefix` (default `'page'`, as
in the real ProcessWire core), the `TextElements` stub a `setText()` method, and the `Wire` stub a
generic `__call()` that - as ProcessWire does for hookable `___method()` methods - automatically
resolves a call like `$obj->renderPaginationMarkup()` to `$obj->___renderPaginationMarkup()`
(previously there was no substitute for this, which is why every test that calls a hookable method
under its public name, instead of using `___method()` directly, would have failed). For the
framework class-name test, `tests/Support/FakeFrameworkPagination.php` stands in for a real
framework subclass such as `FrontendCommentPaginationBootstrap5` (the real classes call
`removeAttribute()`, which this suite does not otherwise need).

| File | Test | Checks |
|---|---|---|
| `FrontendCommentPaginationConstructionTest.php` | `testConstructorDoesNotFatalWhenPaginationWasNeverConfigured` | the real constructor with a never-configured field → no `TypeError`, defaults `10`/`'center'` |
| | `testRenderProducesPaginationMarkupWhenPaginationWasNeverConfigured` | a never-configured field with enough comments for 2 pages → markup is actually rendered |
| | `testRenderStillReturnsEmptyWhenExplicitlyDisabled` | `input_fc_pagnumber = 0` (deliberately disabled) still remains empty - the fix must only change the "never configured" case |
| | `testRenderDelegatesToTheConfiguredFrameworksSubclass` | the configured framework is actually resolved as a subclass, and its `___renderPaginationMarkup()` is used |

## Addendum: the same null-safety gap, this time in `FrontendComments.php` (independent of `FrontendCommentPagination.php`)

After the pagination fix above was delivered, the user reported again: "pagination is still not
shown underneath". A renewed code review found that `FrontendComments.php` (the class that renders
the actual comment list, including the pagination call) has **the same field-never-configured gap
at four further spots, independent of `FrontendCommentPagination.php`**:

1. **Constructor**, `$num_comments_on_page` (non-nullable `int|string` property):
   `$this->field->get('input_fc_pagnumber')` assigned directly → `TypeError` on every render of a
   never-configured field, even before a single comment row is rendered.
2. **Constructor**, `$commentsHeadline->setTag(...)`: `FrontendForms\TextElements::setTag()`
   expects a non-nullable `string` - `input_fc_comments_tag_headline` unset → the same `TypeError`,
   likewise in the constructor, so likewise on every render.
3. **`getCommentsForDisplay()`**: the same `input_fc_pagnumber` value is read here a second time
   (to slice the comment list down to the current page) - same bug, same spot in the data flow as
   (1), just later in the process.
4. **`___renderCommentsDiv()`**, `$maxLevel` (from `input_fc_reply_depth`): is compared unchecked
   against every comment's `level` and, if applicable, written into a reply comment via
   `set('level', $maxLevel)`. For top-level comments (`level = 0`), `0 > null` remains `false` in
   PHP, so the error goes unnoticed - only for a **reply** (`level >= 1`) does `1 > null` become
   `true`; the `null` value then potentially ends up in the comment object via
   `set('level', null)`, and `renderComment()` (which has a non-nullable `int $level` parameter)
   throws the `TypeError` there.

**Fix**: all four spots now use the same `?? FieldtypeFrontendComments::getDefaultData()[...]`
fallback as everywhere else in the module. There are dedicated regression tests for (1) and (3);
(2) is indirectly covered as well (without the fix, both new tests already fail in the
constructor, see the test code); for (4), deliberately **no** dedicated test was written -
actually rendering a reply comment through `FrontendComment::___renderComment()` drags in its
entire template-rendering chain (avatar, author, rating, forms, ...), which would have required a
disproportionately large stub expansion unrelated to pagination; the fix follows exactly the same,
already repeatedly verified pattern as (1)-(3), and was cross-checked with `php -l` and a full
suite run.

*Important, to be honest*: I was **not able to reproduce** the specific "pagination is not shown"
symptom with the parameters you confirmed (field has `input_fc_pagnumber = 10` **explicitly** set,
10+ approved comments) - neither with the unthemed base markup nor with the Bootstrap 5 theme does
the current code (including all fixes so far) produce an empty result in a reproduction with 15
approved top-level comments; the pagination is rendered correctly in both cases. Since you
additionally confirmed that no error message occurs and `pagination-wrapper` does not appear in
the HTML source (so not a CSS problem, but the output is completely missing server-side) and that
you call `render()` (not only `renderComments()`), the four bugs found above remain a sensible,
self-contained hardening - but probably not the sole explanation for your specific case. The most
likely remaining cause is a **caching layer that keeps serving the old (pre-fix) output**, even
though the file has already been replaced - PHP OPcache, ProCache/template cache in ProcessWire,
or a reverse proxy/CDN. Concrete next step: add a timestamp comment to the file (or check
`filemtime()` on the server), reset OPcache (restarting PHP-FPM is usually enough), and, if
ProCache or a similar module is active, explicitly clear its cache for exactly this page.

| File | Test | Checks |
|---|---|---|
| `FrontendCommentsPaginationNumberTest.php` | `testConstructorDoesNotFatalWhenPaginationNumberWasNeverConfigured` | the real constructor with a never-configured `input_fc_pagnumber` → no `TypeError`, default `10` |
| | `testGetCommentsForDisplaySlicesToTheDefaultPageSizeWhenNeverConfigured` | 15 approved comments, a never-configured field → `getCommentsForDisplay()` returns exactly 10 (default page size), no `TypeError` |

*Technical side note for this test setup*: `WireArray` was given stubs for `filter()` (mutates the
array in place, unlike `find()`) and `slice()`, and `WireInput` a `queryStringClean()` - all three
were already used in production by `FrontendComments::getCommentsForDisplay()`, but had so far
been completely unstubbed in the test suite (this code path therefore previously had **no** test
coverage at all). In addition, the `Alert` stub was given `getContent()`, `setAttribute()` and
`render()`, and `Tag` (the base of `TextElements`) a `removeAttribute()` - both needed to let the
real constructors of `FrontendCommentArray` and the framework pagination subclasses (e.g.
`FrontendCommentPaginationBootstrap5`, which calls `removeAttribute()` several times) run through
for the reproduction above, without changing production code.

A total of **186 tests** (Security + Core combined), all using the same verification method
described above: for every test, the corresponding fix/logic in the real module code was briefly
reverted to confirm that the test then actually fails, before the original file was restored.

## Addendum: pagination invisible with EXACTLY one full page (found via a concrete live reproduction: 4 comments, 4 per page)

The previous suspicion (cache) was not confirmed - instead, the user supplied a minimal, directly
reproducible test scenario: comments per page set to 4, exactly 4 comments present in total (1
main comment + 3 replies) - all 4 are shown correctly, but the pagination remains completely
invisible, without any error message.

**Root cause**, found in `FrontendCommentPagination::getPaginationItems()`: the method only ever
added an `<li>` element to the `<ul class="pagination">` when `$pages > 1` (i.e. when genuinely
more than one page existed). `___renderPaginationMarkup()`'s own gate, however, is more generous
(`ceil($totalComments / $numCommentsPage) > 0`, i.e. true for any comment count > 0) - so the outer
`<div class="outer-pagination-wrapper">` with `<nav class="pagination-wrapper">` and
`<ul class="pagination">` was still built and output for exactly one full page, just with a
**completely empty** `<ul>`. If "Showing x to y of z comments" is additionally disabled (or
`createPageOfPageText()`'s own "start === end" special rule does not apply), objectively **no
visible content** remains of the entire pagination block - the wrapper is present in the HTML (a
grep for e.g. "pagination-wrapper" in the source would therefore have found it - that just wasn't
the case in the previous live incident, where an old, unpatched version was presumably actually
still in use), but without any visible content.

**Fix**: `getPaginationItems()` now uses `$pages >= 1` instead of `$pages > 1` - a single page now
consistently shows its own "page 1" marker, matching the already-existing, more generous outer
gate. Multi-page behavior (2+ pages) is unchanged by this - the existing tests in
`FrontendCommentPaginationTest.php` continue to cover that. An already-existing test
(`FrontendCommentPaginationTest.php`) that had explicitly expected **0** items for a single page
was corrected accordingly - it had documented the old, buggy behavior instead of guarding against
it.

| File | Test | Checks |
|---|---|---|
| `FrontendCommentPaginationTest.php` | `testOnlyACurrentPageIndicatorWhenThereIsOnlyOnePage` (renamed/corrected) | `getPaginationItems()` returns exactly 1 item for exactly one page (the "page 1" marker), no longer 0 |
| `FrontendCommentPaginationSinglePageTest.php` | `testPaginationIsVisibleWhenCommentCountExactlyFillsOnePage` | end-to-end via the real constructor + `renderPagination()`: 4 comments / 4 per page (exactly the reported scenario) → the markup contains a visible "page 1" marker, not just an empty wrapper |
| | `testPaginationStillShowsBothPagesWhenCommentCountExceedsOnePage` | control case: 5 comments / 4 per page → 2 pages continue to be shown correctly (the fix does not affect the multi-page case) |

A total of **188 tests** (Security + Core combined), all using the same verification method
described above.

## Addendum: `$this->renderPagination()` never actually called the pagination - `$this->___renderPagination()` was required

Found by the module author himself, via direct debugging on the live site: in
`FrontendCommentArray::render()`, `$out .= $this->renderPagination();` does not work - no error
message, simply no pagination output - while `$out .= $this->___renderPagination();` (the real,
hookable method name with three underscores) works correctly.

Notably: `$this->renderComments($this)` and `$this->renderForm()` right next to it use the same
automatic ProcessWire resolution from `publicName` to `___publicName` (via `Wire::__call()`) and
work perfectly - this rules out a general hook problem on this class and instead points to a name
collision specific to "renderPagination". `FrontendCommentArray` extends `PaginatedArray` (not the
ordinary `WireArray`) and implements `WirePaginatable` - most likely, a real, concrete method with
exactly this name already exists somewhere in this ProcessWire core inheritance chain. PHP then
resolves `$this->renderPagination()` directly to that real method, and `Wire::__call()` (which
would handle the hook resolution to `___renderPagination()`) is never invoked at all - the
module's own method is therefore silently shadowed.

*To be honest*: this is precisely the only plausible explanation I can substantiate from the code
in this repository (without access to the ProcessWire core source code itself) - for 100%
certainty, one would have to search `/wire/core/PaginatedArray.php` or `/wire/core/WireArray.php`
of the ProcessWire installation for an already-existing `renderPagination` method.

**Fix**: `render()` now explicitly calls the hookable method by its real name,
`___renderPagination()`, as the user has already successfully tested himself - this bypasses the
name collision regardless of exactly where it comes from.

*Important limitation regarding test coverage*: there is **no** dedicated regression test for this
fix. This suite's `Wire` stub (`tests/Support/ProcessWireStubs.php`) implements `__call()` so that
it **always** reliably resolves `publicName()` to `___publicName()` (see the pagination addendum
section further above) - this is a deliberately simplified reproduction of ProcessWire, not the
real `PaginatedArray`/`WirePaginatable` code, and can therefore fundamentally not reproduce this
specific name collision. I explicitly cross-checked this: with `$this->renderPagination()` (the
old, buggy line), **all 188 tests** in this suite still pass - the bug simply cannot be detected
with the current stubs. A genuine regression test for this would need either the real ProcessWire
core classes or a deliberate stub reproduction of the competing core method - either would exceed
the scope of this suite (which deliberately works without a ProcessWire installation). As a
substitute: the code comment at the fix location itself documents the problem in detail, and the
general principle for this codebase going forward should be to consistently call hookable methods
by their real `___name()` from now on, instead of relying on automatic resolution - especially for
classes that extend more than just `WireArray`.

## Extending the suite

For a new test:

1. New file under `tests/Security/` or `tests/Core/` (or a further subdirectory following the same
   pattern).
2. `extends \Tests\Support\TestCase` - gives you `newWithoutConstructor()`, `setProp()`,
   `getProp()`, `callMethod()` and `captureOutput()`, as well as a freshly set-up service registry
   (`ProcessWire\TestServices`) per test.
3. Add further services to `ProcessWireStubs.php` if needed (e.g. `pages`, `modules`) - only as
   much as the method under test actually touches, which keeps the stubs small and maintainable.

## Composer

`composer.json` is included so that `composer install` gets you (or `vendor\bin\phpunit`) a
working PHPUnit, in case none exists yet. Anyone who already has a globally working `phpunit`
command can ignore `composer.json` and call `phpunit` directly from the module directory (see
"Setup" above) - `tests/` is already named PSR-4-compliant, in case you later integrate the suite
into an existing Composer/CI configuration.
