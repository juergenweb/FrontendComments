<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use ProcessWire\Field;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\HookEvent;
use ProcessWire\Page;
use ProcessWire\TestServices;
use Tests\Support\TestCase;

/**
 * Unit tests for FieldtypeFrontendComments::updateTables() - the hook that runs after a comment
 * field is saved and keeps the fc_comments_queues (notification queue) and *_votes tables in sync
 * with whatever just changed in the comment array:
 *
 *  - a comment flagged updateQueue=1 (set earlier by correctStatusValues(), see
 *    FieldtypeFrontendCommentsCorrectStatusValuesTest.php) is either queued for notification
 *    (approved/featured) or has its queue/votes rows removed (any other status);
 *  - a brand-new comment (WireArray::getItemsAdded()) has its real database id looked up via
 *    getCommentIDFromDatabase() (it has no id yet - it was never loaded from the DB) and is queued
 *    if published;
 *  - a comment removed from the array (WireArray::getItemsRemoved()) has its queue/votes rows
 *    cleaned up;
 *  - a comment flagged notificationStop=1 has its queue rows for its own email address removed.
 *
 * All four branches only touch the DB through addCommentToQueueTable()/deleteEntriesInQueueTable()/
 * deleteEntriesInVotesTable()/deleteEmailsInQueueTable() and getCommentIDFromDatabase() - all of
 * which are plain DB-only methods already covered directly by FrontendCommentQueueTableTest.php, so
 * here it is enough to assert *which* of them updateTables() calls for a given comment, via the
 * shape of the resulting prepared statements, rather than re-checking their SQL in full.
 *
 * addCommentToQueueTable() used to be declared `protected` on FrontendComment while both of its
 * call sites here are in the unrelated FieldtypeFrontendComments class - a plain PHP visibility
 * violation that made both "approved/featured -> queue" and "newly added and published -> queue"
 * branches fatal in real use (confirmed against the then-unmodified source before this fix). Now
 * fixed to `public` (see FrontendComment.php); the two tests below cover exactly those branches.
 *
 * The stub WireArray::add() always records every add() into getItemsAdded() (see its own docblock -
 * a simplification, real ProcessWire only does this for genuinely new items). To model "this
 * comment already existed before this save" for the updateQueue=1 / notificationStop=1 tests, the
 * helper below attaches the comment and then clears the tracked-additions list via reflection,
 * exactly as if it had been loaded from the database rather than just added in this test.
 */
final class FieldtypeFrontendCommentsUpdateTablesTest extends TestCase
{
    private function newModule(): FieldtypeFrontendComments
    {
        return $this->newWithoutConstructor(FieldtypeFrontendComments::class);
    }

    private function newComment(int $id, int $status, int $parentId = 0, string $email = 'commenter@example.com', int $fieldId = 1, int $pageId = 1): FrontendComment
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $field = $this->newField($fieldId, 'comments');
        $field->table = 'field_comments';
        $page = new Page();
        $page->id = $pageId;

        $comment->set('id', $id);
        $comment->set('parent_id', $parentId);
        $comment->set('email', $email);
        $comment->set('status', $status);
        $comment->set('code', 'code-' . $id);
        // addCommentToQueueTable() reads field/page via ->get('field')/->get('page') (the WireData
        // storage), while deleteEntriesInQueueTable()/deleteEntriesInVotesTable()/
        // deleteEmailsInQueueTable() read them via the protected typed properties instead - set
        // both, as the real constructor would.
        $comment->set('field', $field);
        $comment->set('page', $page);
        $this->setProp($comment, 'field', $field);
        $this->setProp($comment, 'page', $page);

        return $comment;
    }

    /** Attach a comment as if it had already existed before this save (not part of getItemsAdded()). */
    private function attachExisting(FrontendCommentArray $comments, FrontendComment $comment): void
    {
        $comments->add($comment);
        $this->setProp($comments, 'itemsAdded', []);
    }

    private function runHook(FieldtypeFrontendComments $module, Page $page, Field $field, FrontendCommentArray $comments): void
    {
        $page->set($field->name, $comments);
        $event = new HookEvent($module, [$page, $field]);
        $this->callMethod($module, 'updateTables', [$event]);
    }

    private function database(): \ProcessWire\Database
    {
        return TestServices::get('database');
    }

    // -----------------------------------------------------------------
    // updateQueue=1
    // -----------------------------------------------------------------

    public function testApprovedCommentFlaggedForUpdateIsAddedToTheQueueTable(): void
    {
        // Regression test for the addCommentToQueueTable() visibility fix (protected -> public) made
        // in this session: before that fix, this branch fatally errored with "Call to protected
        // method FrontendComments\FrontendComment::addCommentToQueueTable() from scope
        // ProcessWire\FieldtypeFrontendComments" every time an approved/featured comment reached
        // here - i.e. on essentially every normal "approve a comment" save.
        $module = $this->newModule();
        $page = new Page();
        $page->id = 1;
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        $comment = $this->newComment(id: 42, status: FieldtypeFrontendComments::approved);
        $comment->set('updateQueue', 1);
        $this->attachExisting($comments, $comment);

        $this->database()->queueResult(0, true, []); // recipient lookup finds nobody

        $this->runHook($module, $page, $field, $comments);

        self::assertCount(1, $this->database()->prepared, 'only addCommentToQueueTable()\'s recipient lookup, no DELETE');
        self::assertStringContainsString('SELECT email FROM', $this->database()->prepared[0]->sql);
    }

    public function testFeaturedCommentFlaggedForUpdateIsAddedToTheQueueTable(): void
    {
        $module = $this->newModule();
        $page = new Page();
        $page->id = 1;
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        $comment = $this->newComment(id: 42, status: FieldtypeFrontendComments::featured);
        $comment->set('updateQueue', 1);
        $this->attachExisting($comments, $comment);

        $this->database()->queueResult(0, true, []);

        $this->runHook($module, $page, $field, $comments);

        self::assertCount(1, $this->database()->prepared);
        self::assertStringContainsString('SELECT email FROM', $this->database()->prepared[0]->sql);
    }

    public function testCommentRevertedToPendingApprovalIsRemovedFromQueueAndVotesTables(): void
    {
        $module = $this->newModule();
        $page = new Page();
        $page->id = 1;
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        $comment = $this->newComment(id: 42, status: FieldtypeFrontendComments::pendingApproval);
        $comment->set('updateQueue', 1);
        $this->attachExisting($comments, $comment);

        $this->runHook($module, $page, $field, $comments);

        self::assertCount(2, $this->database()->prepared, 'a queue-table delete and a votes-table delete, no recipient lookup');
        self::assertStringContainsString('DELETE FROM fc_comments_queues', $this->database()->prepared[0]->sql);
        self::assertStringContainsString('_votes', $this->database()->prepared[1]->sql);
    }

    public function testCommentMarkedAsSpamIsRemovedFromQueueAndVotesTables(): void
    {
        $module = $this->newModule();
        $page = new Page();
        $page->id = 1;
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        $comment = $this->newComment(id: 42, status: FieldtypeFrontendComments::spam);
        $comment->set('updateQueue', 1);
        $this->attachExisting($comments, $comment);

        $this->runHook($module, $page, $field, $comments);

        self::assertCount(2, $this->database()->prepared);
        self::assertStringContainsString('DELETE FROM fc_comments_queues', $this->database()->prepared[0]->sql);
        self::assertStringContainsString('_votes', $this->database()->prepared[1]->sql);
    }

    // -----------------------------------------------------------------
    // getItemsAdded() - brand-new comments
    // -----------------------------------------------------------------

    public function testNewlyAddedPublishedCommentIsLookedUpAndQueued(): void
    {
        // Regression test for the same addCommentToQueueTable() visibility fix as above, via the
        // other of its two call sites.
        $module = $this->newModule();
        $page = new Page();
        $page->id = 1;
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        // a comment that was just saved and is not yet in this array with a real database id
        // (mirrors how it would arrive fresh from the save handler)
        $comment = $this->newComment(id: 0, status: FieldtypeFrontendComments::approved);
        $comments->add($comment); // stays tracked in getItemsAdded() - this is the point being tested

        // 1) getCommentIDFromDatabase()'s lookup by code - an int, like the already-fixed
        //    getLastID() documents PDO would actually return a numeric string for this; a plain int
        //    is used here to isolate this test from that separate cast question, already covered by
        //    FrontendCommentQueueTableTest::testGetCommentIdFromDatabaseCastsThePdoStringResultToAnInt().
        $this->database()->queueResult(1, true, [['id' => 55]]);
        // 2) addCommentToQueueTable()'s recipient lookup, once the comment is known to be published
        $this->database()->queueResult(0, true, []);

        $this->runHook($module, $page, $field, $comments);

        self::assertSame(55, $comment->get('id'), 'the looked-up database id must be written back onto the comment');
        self::assertCount(2, $this->database()->prepared);
        self::assertStringContainsString('WHERE code=:code', $this->database()->prepared[0]->sql);
        self::assertStringContainsString('SELECT email FROM', $this->database()->prepared[1]->sql);
    }

    public function testNewlyAddedUnpublishedCommentIsLookedUpButNotQueued(): void
    {
        $module = $this->newModule();
        $page = new Page();
        $page->id = 1;
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        $comment = $this->newComment(id: 0, status: FieldtypeFrontendComments::pendingApproval);
        $comments->add($comment);

        $this->database()->queueResult(1, true, [['id' => 77]]);

        $this->runHook($module, $page, $field, $comments);

        self::assertSame(77, $comment->get('id'), 'the id is still looked up and stored regardless of publish state');
        self::assertCount(1, $this->database()->prepared, 'not published, so no recipient lookup/queueing must follow');
    }

    public function testNewlyAddedCommentWithNoMatchingDatabaseRowIsNeverQueued(): void
    {
        $module = $this->newModule();
        $page = new Page();
        $page->id = 1;
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        $comment = $this->newComment(id: 0, status: FieldtypeFrontendComments::approved);
        $comments->add($comment);

        $this->database()->queueResult(1, true, []); // no matching row - getCommentIDFromDatabase() returns null

        $this->runHook($module, $page, $field, $comments);

        self::assertSame(0, $comment->get('id'), 'is_int(null) is false, so the id must be left untouched');
        self::assertCount(1, $this->database()->prepared, 'nothing beyond the failed id lookup should have been prepared');
    }

    // -----------------------------------------------------------------
    // getItemsRemoved() - deleted comments
    // -----------------------------------------------------------------

    public function testRemovedCommentsAreCleanedUpFromQueueAndVotesTables(): void
    {
        $module = $this->newModule();
        $page = new Page();
        $page->id = 1;
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        $comment = $this->newComment(id: 42, status: FieldtypeFrontendComments::approved);
        $this->attachExisting($comments, $comment);
        $comments->remove($comment);

        $this->runHook($module, $page, $field, $comments);

        self::assertCount(2, $this->database()->prepared);
        self::assertStringContainsString('DELETE FROM fc_comments_queues', $this->database()->prepared[0]->sql);
        self::assertStringContainsString('_votes', $this->database()->prepared[1]->sql);
    }

    // -----------------------------------------------------------------
    // notificationStop=1
    // -----------------------------------------------------------------

    public function testCommentFlaggedNotificationStopHasItsQueueEmailsRemoved(): void
    {
        $module = $this->newModule();
        $page = new Page();
        $page->id = 1;
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        $comment = $this->newComment(id: 42, status: FieldtypeFrontendComments::approved, email: 'unsubscribing@example.com');
        $comment->set('notificationStop', 1);
        $this->attachExisting($comments, $comment);

        $this->runHook($module, $page, $field, $comments);

        self::assertCount(1, $this->database()->prepared, 'deleteEmailsInQueueTable() only, no queue-table or votes-table delete');
        $statement = $this->database()->prepared[0];
        self::assertStringContainsString('email=:email', $statement->sql);
        self::assertSame('unsubscribing@example.com', $statement->bound[':email']);
    }
}
