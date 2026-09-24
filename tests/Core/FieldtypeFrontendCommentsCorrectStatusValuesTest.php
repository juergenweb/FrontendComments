<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use ProcessWire\Field;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\HookEvent;
use ProcessWire\Page;
use Tests\Support\TestCase;

/**
 * Unit tests for FieldtypeFrontendComments::correctStatusValues() - the hook that runs before a
 * comment field is saved and rejects two specific admin-initiated status changes that would
 * otherwise corrupt the rendered tree or the database. The module's own inline comment documents
 * a real past bug this test locks in: both cases used to share a single "has any replies" guard,
 * but that shared check only ever counted approved replies, so a comment whose replies existed but
 * were only pending/spam (for the revert-to-pending case) or only featured (for the delete case)
 * silently slipped through - neither blocked nor bookkept. The fix splits this into two guards,
 * each using the right reply-visibility check for what it protects against.
 */
final class FieldtypeFrontendCommentsCorrectStatusValuesTest extends TestCase
{
    private function newModule(): FieldtypeFrontendComments
    {
        return $this->newWithoutConstructor(FieldtypeFrontendComments::class);
    }

    private function newPage(): Page
    {
        // note: the "edited via wire's admin tree" remote_flag branch is driven by
        // wire('page')->rootParent (the globally current page, defaulted to non-admin in
        // TestCase::setUp()) - NOT by this $page, which is the page whose comment field is being
        // saved (the hook's own first argument).
        $page = new Page();
        $page->id = 10;
        return $page;
    }

    private function newComment(int $id, int $parentId, int $oldStatus, $newStatus): FrontendComment
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('id', $id);
        $comment->set('parent_id', $parentId);
        $comment->set('old_status', $oldStatus);
        $comment->set('status', $newStatus);
        $comment->trackChange('status'); // mark this specific comment's status as changed
        return $comment;
    }

    private function attach(FrontendComment $comment, FrontendCommentArray $array): void
    {
        $this->setProp($comment, 'comments', $array);
        $array->add($comment);
    }

    private function runHook(FieldtypeFrontendComments $module, Page $page, Field $field, FrontendCommentArray $comments): void
    {
        $fieldName = $field->name;
        $page->set($fieldName, $comments);
        $comments->trackChange('statuschange'); // marks "at least one comment's status changed" for the top-level gate

        $event = new HookEvent($module, [$page, $field]);
        $module->correctStatusValues($event);
    }

    // -----------------------------------------------------------------
    // reverting to "pending approval" must be blocked when doing so would orphan a visible reply
    // -----------------------------------------------------------------

    public function testRevertingToPendingApprovalIsBlockedWhenAnApprovedReplyExists(): void
    {
        $module = $this->newModule();
        $page = $this->newPage();
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        $parent = $this->newComment(1, 0, FieldtypeFrontendComments::approved, FieldtypeFrontendComments::pendingApproval);
        $this->attach($parent, $comments);
        $reply = $this->newComment(2, 1, FieldtypeFrontendComments::approved, FieldtypeFrontendComments::approved);
        $this->attach($reply, $comments);

        $this->runHook($module, $page, $field, $comments);

        self::assertSame(FieldtypeFrontendComments::approved, $parent->get('status'), 'the status change must have been reverted back to the old status');
        self::assertNotEmpty(array_filter($module->notices, fn($n) => $n['type'] === 'warning'));
        self::assertSame(1, \ProcessWire\TestServices::get('session')->get('statuswarning-1'));
    }

    public function testRevertingToPendingApprovalIsAllowedWhenThereIsNoVisibleReplyToOrphan(): void
    {
        $module = $this->newModule();
        $page = $this->newPage();
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        // a reply exists, but it's only pending - not visible on the frontend either way, so
        // reverting the parent to pending has nothing left to orphan
        $parent = $this->newComment(1, 0, FieldtypeFrontendComments::approved, FieldtypeFrontendComments::pendingApproval);
        $this->attach($parent, $comments);
        $reply = $this->newComment(2, 1, FieldtypeFrontendComments::pendingApproval, FieldtypeFrontendComments::pendingApproval);
        $this->attach($reply, $comments);

        $this->runHook($module, $page, $field, $comments);

        self::assertSame(FieldtypeFrontendComments::pendingApproval, $parent->get('status'), 'nothing to orphan - the status change must be allowed to stand');
        self::assertSame(FieldtypeFrontendComments::pendingApproval, $parent->get('statusChange'));
        self::assertSame(1, $parent->get('updateQueue'));
    }

    // -----------------------------------------------------------------
    // reverting to "pending approval" must reset "reminder_sent" back to 0, so
    // FieldtypeFrontendComments::sendPendingReminders() (see
    // tests/Core/FieldtypeFrontendCommentsPendingReminderTest.php) treats a reopened comment as
    // eligible for a fresh reminder cycle rather than silently skipping it forever just because it
    // happened to be pending - and already reminded about - once before.
    // -----------------------------------------------------------------

    public function testReopeningForModerationResetsTheReminderSentFlag(): void
    {
        $module = $this->newModule();
        $page = $this->newPage();
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        // no reply at all -> nothing to orphan, the revert-to-pending is allowed to stand
        $parent = $this->newComment(1, 0, FieldtypeFrontendComments::approved, FieldtypeFrontendComments::pendingApproval);
        $parent->set('reminder_sent', 1); // was already reminded about during its earlier pending phase
        $this->attach($parent, $comments);

        $this->runHook($module, $page, $field, $comments);

        self::assertSame(0, $parent->get('reminder_sent'), 'reopening for moderation must make the comment eligible for a reminder again');
    }

    public function testReminderSentIsLeftUntouchedForStatusChangesOtherThanPendingApproval(): void
    {
        $module = $this->newModule();
        $page = $this->newPage();
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        $parent = $this->newComment(1, 0, FieldtypeFrontendComments::pendingApproval, FieldtypeFrontendComments::approved);
        $parent->set('reminder_sent', 1);
        $this->attach($parent, $comments);

        $this->runHook($module, $page, $field, $comments);

        self::assertSame(1, $parent->get('reminder_sent'), 'only a change TO pending approval resets the flag - approving a comment must not touch it');
    }

    // -----------------------------------------------------------------
    // deleting must be blocked whenever ANY reply still references this comment, regardless of
    // that reply's own status (stricter than the pending-approval guard above, on purpose - see
    // the module's own inline comment: deleting physically removes the database row)
    // -----------------------------------------------------------------

    public function testDeletingIsBlockedWhenAnyReplyStillExistsEvenAMerelyPendingOne(): void
    {
        $module = $this->newModule();
        $page = $this->newPage();
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        $parent = $this->newComment(1, 0, FieldtypeFrontendComments::approved, 'delete');
        $this->attach($parent, $comments);
        // only a pending (not-yet-visible) reply - the pending-approval guard above would treat
        // this as "nothing to orphan", but deleting still must not be allowed to orphan the row
        $reply = $this->newComment(2, 1, FieldtypeFrontendComments::pendingApproval, FieldtypeFrontendComments::pendingApproval);
        $this->attach($reply, $comments);

        $countBefore = $comments->count();
        $this->runHook($module, $page, $field, $comments);

        self::assertSame(FieldtypeFrontendComments::approved, $parent->get('status'), 'the delete must have been reverted back to the old status');
        self::assertSame($countBefore, $comments->count(), 'the comment must still be in the array, not removed');
        self::assertNotEmpty(array_filter($module->notices, fn($n) => $n['type'] === 'warning'));
    }

    public function testDeletingIsAllowedWhenThereAreNoRepliesAtAll(): void
    {
        $module = $this->newModule();
        $page = $this->newPage();
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        $lonelyComment = $this->newComment(1, 0, FieldtypeFrontendComments::approved, 'delete');
        $this->attach($lonelyComment, $comments);

        $this->runHook($module, $page, $field, $comments);

        self::assertSame(0, $comments->count(), 'with no replies to lose, the delete must actually go through');
    }

    // -----------------------------------------------------------------
    // SPAM is deliberately never blocked, regardless of replies (see the module's own inline
    // comment: a spam comment stays in the tree as a placeholder, so its status change never risks
    // orphaning anything)
    // -----------------------------------------------------------------

    public function testMarkingAsSpamIsNeverBlockedEvenWithAnApprovedReply(): void
    {
        $module = $this->newModule();
        $page = $this->newPage();
        $field = $this->newField(1, 'comments');
        $comments = $this->newWithoutConstructor(FrontendCommentArray::class);

        $parent = $this->newComment(1, 0, FieldtypeFrontendComments::approved, FieldtypeFrontendComments::spam);
        $this->attach($parent, $comments);
        $reply = $this->newComment(2, 1, FieldtypeFrontendComments::approved, FieldtypeFrontendComments::approved);
        $this->attach($reply, $comments);

        $this->runHook($module, $page, $field, $comments);

        self::assertSame(FieldtypeFrontendComments::spam, $parent->get('status'));
        self::assertSame(FieldtypeFrontendComments::spam, $parent->get('statusChange'));
        self::assertEmpty(array_filter($module->notices, fn($n) => $n['type'] === 'warning'));
    }
}
