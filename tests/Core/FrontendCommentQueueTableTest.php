<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\Page;
use ProcessWire\TestServices;
use Tests\Support\TestCase;

/**
 * Unit tests for the notification queue table (`fc_comments_queues`) methods on FrontendComment:
 * addCommentToQueueTable() (queues one row per recipient when a comment is published),
 * deleteEntriesInQueueTable() (removes a specific comment's queued rows), deleteEmailsInQueueTable()
 * (removes queued rows for one recipient, e.g. after they unsubscribe) and
 * getCommentIDFromDatabase() (looks up a newly added comment's real database id by its `code`).
 *
 * All four are plain DB-only methods reachable via newWithoutConstructor() + setProp('field'/'page')
 * - no real FrontendComment constructor needed. sendQueuedEmails() (the LazyCron job that actually
 * SENDS the queued mails) is deliberately not covered: it ultimately builds a real FrontendComment
 * via the full, heavy constructor to hand to Notifications::sendNotificationAboutNewReply(), the
 * same reason ___wakeupValue()/getCommentByID() were skipped earlier in this suite.
 */
final class FrontendCommentQueueTableTest extends TestCase
{
    private function newComment(int $id, int $parentId, string $email, int $fieldId = 1, int $pageId = 1): FrontendComment
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $field = $this->newField($fieldId, 'comments')->setArray(['table' => 'field_comments']);
        $page = new Page();
        $page->id = $pageId;

        $comment->set('id', $id);
        $comment->set('parent_id', $parentId);
        $comment->set('email', $email);
        // addCommentToQueueTable() reads field/page via ->get('field')/->get('page') (the WireData
        // storage), while deleteEntriesInQueueTable()/deleteEmailsInQueueTable() read them via the
        // protected typed properties instead - set both the same way the real constructor would.
        $comment->set('field', $field);
        $comment->set('page', $page);
        $this->setProp($comment, 'field', $field);
        $this->setProp($comment, 'page', $page);

        return $comment;
    }

    private function database(): \ProcessWire\Database
    {
        return TestServices::get('database');
    }

    // -----------------------------------------------------------------
    // addCommentToQueueTable()
    // -----------------------------------------------------------------

    public function testAddCommentToQueueTableDoesNothingWhenNobodyWantsToBeNotified(): void
    {
        $comment = $this->newComment(id: 42, parentId: 0, email: 'commenter@example.com');
        $this->database()->queueResult(0, true, []); // the notify-all/parent-reply lookup finds nobody

        $result = $this->callMethod($comment, 'addCommentToQueueTable');

        self::assertNull($result);
        self::assertCount(1, $this->database()->prepared, 'nothing beyond the initial recipient lookup should have been prepared');
    }

    public function testAddCommentToQueueTableExcludesTheCommentersOwnEmail(): void
    {
        $comment = $this->newComment(id: 42, parentId: 0, email: 'commenter@example.com');
        $this->database()->queueResult(0, true, [
            ['email' => 'commenter@example.com'], // the commenter themself - must be excluded
        ]);

        $result = $this->callMethod($comment, 'addCommentToQueueTable');

        self::assertNull($result, 'with the only "recipient" being the commenter, nothing is left to queue');
        self::assertCount(1, $this->database()->prepared);
    }

    public function testAddCommentToQueueTableChecksAndInsertsEachRecipientIndividually(): void
    {
        // Regression test for the fix documented directly at this call site: the "is this
        // recipient already queued?" check used to run only once, after the recipient loop, so it
        // only ever saw the LAST recipient - every other recipient's check was silently skipped
        // while a single batched INSERT still added rows for all of them regardless. Checking and
        // inserting per recipient (as tested here) fixes both the missed checks and the
        // unconditional batch insert.
        $comment = $this->newComment(id: 42, parentId: 0, email: 'commenter@example.com');
        $db = $this->database();

        $db->queueResult(0, true, [ // 1) recipient lookup
            ['email' => 'new@example.com'],
            ['email' => 'already-queued@example.com'],
        ]);
        $db->queueResult(0, true, []);              // 2) "already queued?" check for new@example.com -> no
        $db->queueResult(1, true);                   // 3) INSERT for new@example.com -> succeeds
        $db->queueResult(1, true, [['id' => 99]]);   // 4) "already queued?" check for already-queued@example.com -> yes

        $result = $this->callMethod($comment, 'addCommentToQueueTable');

        self::assertTrue($result);
        self::assertCount(4, $db->prepared, 'exactly 4 statements: lookup, check+insert for the new recipient, check only for the already-queued one');

        $insertStatements = array_values(array_filter($db->prepared, fn($s) => str_starts_with($s->sql, 'INSERT')));
        self::assertCount(1, $insertStatements, 'only the recipient who was not yet queued may be inserted');
        self::assertSame('new@example.com', $insertStatements[0]->bound[':email']);
    }

    public function testAddCommentToQueueTableReturnsNullWhenEveryRecipientIsAlreadyQueued(): void
    {
        $comment = $this->newComment(id: 42, parentId: 0, email: 'commenter@example.com');
        $db = $this->database();

        $db->queueResult(0, true, [['email' => 'already-queued@example.com']]);
        $db->queueResult(1, true, [['id' => 99]]); // already queued -> skip

        $result = $this->callMethod($comment, 'addCommentToQueueTable');

        self::assertNull($result, 'no row was ever actually inserted, so this must be null, not true');
        self::assertCount(2, $db->prepared, 'no INSERT should have been prepared at all');
    }

    public function testAddCommentToQueueTableOnlyLooksUpRecipientsWhoHaveConfirmedTheirEmailAddress(): void
    {
        // Regression test for the double opt-in fix: the recipient lookup must require
        // notification_confirmed=1 in BOTH the "notify about all comments" and the "notify about a
        // reply to my own comment" branch of the WHERE clause - otherwise a comment posted using
        // someone else's, never-confirmed email address would still receive notification mails.
        $comment = $this->newComment(id: 42, parentId: 0, email: 'commenter@example.com');
        $this->database()->queueResult(0, true, []);

        $this->callMethod($comment, 'addCommentToQueueTable');

        $statement = $this->database()->prepared[0];
        self::assertStringContainsString(
            'notification=:notification AND notification_confirmed=1',
            $statement->sql,
            'the "notify about all comments" branch must also require a confirmed address'
        );
        self::assertStringContainsString(
            'notification=:parent_notification AND notification_confirmed=1',
            $statement->sql,
            'the "notify about a reply to my own comment" branch must also require a confirmed address'
        );
    }

    // -----------------------------------------------------------------
    // deleteEntriesInQueueTable()
    // -----------------------------------------------------------------

    public function testDeleteEntriesInQueueTableDeletesOnlyThisCommentsRows(): void
    {
        $comment = $this->newComment(id: 7, parentId: 0, email: 'a@example.com', fieldId: 3, pageId: 5);

        $comment->deleteEntriesInQueueTable();

        self::assertCount(1, $this->database()->prepared);
        $statement = $this->database()->prepared[0];
        self::assertStringContainsString('DELETE FROM fc_comments_queues', $statement->sql);
        self::assertStringContainsString('comment_id=:id', $statement->sql);
        self::assertSame(7, $statement->bound[':id']);
        self::assertSame(3, $statement->bound[':field_id']);
        self::assertSame(5, $statement->bound[':page_id']);
    }

    // -----------------------------------------------------------------
    // deleteEmailsInQueueTable()
    // -----------------------------------------------------------------

    public function testDeleteEmailsInQueueTableIsScopedToThisPageAndField(): void
    {
        // Regression test for the fix documented directly at this call site: this DELETE used to
        // match only the email address, so unsubscribing on one page silently deleted the same
        // person's still-pending, unrelated queue entries for every OTHER page/field where they
        // remain legitimately subscribed. It must be scoped to page_id AND field_id too.
        $comment = $this->newComment(id: 7, parentId: 0, email: 'unsubscribing@example.com', fieldId: 3, pageId: 5);

        $comment->deleteEmailsInQueueTable();

        self::assertCount(1, $this->database()->prepared);
        $statement = $this->database()->prepared[0];
        self::assertStringContainsString('email=:email', $statement->sql);
        self::assertStringContainsString('page_id=:page_id', $statement->sql, 'must be scoped to the page, not just the email address');
        self::assertStringContainsString('field_id=:field_id', $statement->sql, 'must be scoped to the field, not just the email address');
        self::assertSame('unsubscribing@example.com', $statement->bound[':email']);
        self::assertSame(5, $statement->bound[':page_id']);
        self::assertSame(3, $statement->bound[':field_id']);
    }

    // -----------------------------------------------------------------
    // getCommentIDFromDatabase()
    // -----------------------------------------------------------------

    public function testGetCommentIdFromDatabaseCastsThePdoStringResultToAnInt(): void
    {
        // Regression test for a fix made in this session: PDO (emulated prepares, ProcessWire's own
        // default) returns numeric columns as strings, not ints - the same reasoning already
        // documented and fixed on FrontendCommentArray::getLastID(). Without the explicit (int)
        // cast this file's declare(strict_types=1) turns that raw string into a TypeError, since the
        // method is typed ": int|null".
        $comment = $this->newComment(id: 0, parentId: 0, email: 'commenter@example.com');
        $comment->set('code', 'abc123');

        $this->database()->queueResult(1, true, [['id' => '55']]); // string, like real PDO

        $result = $comment->getCommentIDFromDatabase();

        self::assertSame(55, $result);
        self::assertIsInt($result);
    }

    public function testGetCommentIdFromDatabaseReturnsNullWhenNoMatchingRowExists(): void
    {
        $comment = $this->newComment(id: 0, parentId: 0, email: 'commenter@example.com');
        $comment->set('code', 'does-not-exist');

        $this->database()->queueResult(1, true, []);

        self::assertNull($comment->getCommentIDFromDatabase());
    }
}
