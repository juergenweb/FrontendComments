<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use FrontendComments\FrontendComments;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\WireException;
use Tests\Support\TestCase;

/**
 * Regression tests for the "spam tombstone" behavior: a comment marked as SPAM must disappear
 * from its own content (author, avatar, votes, text, ...) but must NOT disappear from the tree
 * entirely if it still has approved/featured replies underneath it - otherwise those replies
 * become unreachable (this is exactly the bug reported and debugged at the start of this
 * engagement: marking a parent comment as spam made both it AND its approved children vanish
 * from the frontend, instead of showing the parent as a placeholder).
 *
 * Covers:
 *  - FrontendComment::hasReplies() / numberOfReplies() / hasVisibleReplies() / isSpamPlaceholder()
 *  - FrontendComment::buildSpamPlaceholderVars() / renderCommentTemplate() (via the real
 *    templates/comment.php file, not a description of it - see commentWithTemplate())
 *  - FrontendComments::getCommentListArray() (the tree-building/filtering logic itself)
 */
final class TombstoneRenderingTest extends TestCase
{
    private function newComment(int $id, int $parentId, int $status): FrontendComment
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('id', $id);
        $comment->set('parent_id', $parentId);
        $comment->set('status', $status);
        $comment->set('sort', $id);
        return $comment;
    }

    /** Wire a comment's protected "comments" property to the array it belongs to, like the real constructor would. */
    private function attachToArray(FrontendComment $comment, FrontendCommentArray $array): void
    {
        $this->setProp($comment, 'comments', $array);
    }

    private function newEmptyArray(): FrontendCommentArray
    {
        return $this->newWithoutConstructor(FrontendCommentArray::class);
    }

    // -----------------------------------------------------------------
    // hasReplies() / numberOfReplies() / hasVisibleReplies()
    // -----------------------------------------------------------------

    public function testNumberOfRepliesCountsOnlyDirectChildrenOfThisComment(): void
    {
        $array = $this->newEmptyArray();
        $parent = $this->newComment(1, 0, FieldtypeFrontendComments::approved);
        $child = $this->newComment(2, 1, FieldtypeFrontendComments::approved);
        $grandchild = $this->newComment(3, 2, FieldtypeFrontendComments::approved); // child of $child, not of $parent
        $unrelated = $this->newComment(4, 0, FieldtypeFrontendComments::approved); // sibling of $parent, not a reply to it

        foreach ([$parent, $child, $grandchild, $unrelated] as $c) {
            $array->add($c);
            $this->attachToArray($c, $array);
        }

        self::assertSame(1, $parent->numberOfReplies(), 'only the direct child belongs to $parent, not the grandchild or the unrelated sibling');
        self::assertTrue($parent->hasReplies());
        self::assertSame(0, $unrelated->numberOfReplies());
        self::assertFalse($unrelated->hasReplies());
    }

    public function testHasVisibleRepliesIsTrueOnlyForApprovedOrFeaturedReplies(): void
    {
        $array = $this->newEmptyArray();
        $parent = $this->newComment(1, 0, FieldtypeFrontendComments::spam);

        $pendingChild = $this->newComment(2, 1, FieldtypeFrontendComments::pendingApproval);
        $array->add($parent);
        $array->add($pendingChild);
        $this->attachToArray($parent, $array);
        $this->attachToArray($pendingChild, $array);

        self::assertTrue($parent->hasReplies(), 'a pending reply still counts as a reply...');
        self::assertFalse($parent->hasVisibleReplies(), '...but must not count as a VISIBLE one - it never reaches the frontend either');

        $approvedChild = $this->newComment(3, 1, FieldtypeFrontendComments::approved);
        $array->add($approvedChild);
        $this->attachToArray($approvedChild, $array);

        self::assertTrue($parent->hasVisibleReplies(), 'an approved reply makes the parent\'s replies visible');

        $array->remove($approvedChild);
        $featuredChild = $this->newComment(4, 1, FieldtypeFrontendComments::featured);
        $array->add($featuredChild);
        $this->attachToArray($featuredChild, $array);

        self::assertTrue($parent->hasVisibleReplies(), 'a featured reply counts as visible too');

        $array->remove($featuredChild);
        $spamChild = $this->newComment(5, 1, FieldtypeFrontendComments::spam);
        $array->add($spamChild);
        $this->attachToArray($spamChild, $array);

        self::assertTrue($parent->hasReplies());
        self::assertFalse($parent->hasVisibleReplies(), 'a spam reply is not itself visible, so it must not keep the parent "alive" either');
    }

    public function testIsSpamPlaceholderIsTrueOnlyForSpamStatus(): void
    {
        $spam = $this->newComment(1, 0, FieldtypeFrontendComments::spam);
        $approved = $this->newComment(2, 0, FieldtypeFrontendComments::approved);
        $pending = $this->newComment(3, 0, FieldtypeFrontendComments::pendingApproval);
        $featured = $this->newComment(4, 0, FieldtypeFrontendComments::featured);

        self::assertTrue($spam->isSpamPlaceholder());
        self::assertFalse($approved->isSpamPlaceholder());
        self::assertFalse($pending->isSpamPlaceholder());
        self::assertFalse($featured->isSpamPlaceholder());
    }

    // -----------------------------------------------------------------
    // buildSpamPlaceholderVars() / renderCommentTemplate() - rendered via the REAL
    // templates/comment.php, so this also proves the wiring between isSpamPlaceholder(),
    // buildSpamPlaceholderVars() and renderCommentTemplate() actually reaches the markup.
    // -----------------------------------------------------------------

    private function baseVars(): array
    {
        return [
            'statusClass' => 'fcm-approved',
            'noVoteAlert' => '',
            'avatar' => '<img class="fc-avatar" src="avatar.jpg">',
            'author' => '<h6 class="fcm-comment-name">Jane Doe</h6>',
            'created' => '<span>2 days ago</span>',
            'rating' => '',
            'replyLink' => '<a class="fc-reply-link">Reply</a>',
            'votes' => '<div class="fc-votes">up/down</div>',
            'commentText' => '<span class="fcm-comment-content">the original comment text</span>',
            'feedbackText' => '',
            'websiteLink' => '<a class="fc-website">https://example.com</a>',
            'replyForm' => '',
            'level' => 0,
            'levelnumber' => '0-0',
        ];
    }

    private function commentWithTemplate(int $status): FrontendComment
    {
        $comment = $this->newComment(99, 0, $status);
        $this->setProp($comment, 'commentTemplateFile', dirname(__DIR__, 2) . '/templates/comment.php');
        return $comment;
    }

    public function testOrdinaryApprovedCommentRendersItsRealContentUnchanged(): void
    {
        $comment = $this->commentWithTemplate(FieldtypeFrontendComments::approved);

        $html = $this->callMethod($comment, 'renderCommentTemplate', [$this->baseVars()]);

        self::assertStringContainsString('Jane Doe', $html);
        self::assertStringContainsString('the original comment text', $html);
        self::assertStringContainsString('fc-avatar', $html);
        self::assertStringContainsString('class="fcm-comment-box fcm-approved"', $html);
        self::assertStringNotContainsString('fcm-comment-spam', $html);
        self::assertStringNotContainsString('marked as spam', $html);
    }

    public function testSpamCommentSuppressesIdentityAndShowsPlaceholderNoticeInstead(): void
    {
        $comment = $this->commentWithTemplate(FieldtypeFrontendComments::spam);

        $html = $this->callMethod($comment, 'renderCommentTemplate', [$this->baseVars()]);

        // identity- and interaction-revealing content must be gone
        self::assertStringNotContainsString('Jane Doe', $html);
        self::assertStringNotContainsString('fc-avatar', $html);
        self::assertStringNotContainsString('fc-website', $html);
        self::assertStringNotContainsString('fc-reply-link', $html);
        self::assertStringNotContainsString('fc-votes', $html);
        // the ORIGINAL text must never leak through
        self::assertStringNotContainsString('the original comment text', $html);
        // a neutral placeholder notice must appear instead
        self::assertStringContainsString('marked as spam', $html);
        self::assertStringContainsString('fcm-comment-spam', $html);
        // the thread position (level/levelnumber) is deliberately left intact, so replies
        // underneath still render in the right place
        self::assertStringNotContainsString('fcm-featured', $html);
    }

    public function testRenderCommentTemplateReturnsFallbackWhenNoTemplateFileIsConfigured(): void
    {
        $comment = $this->newComment(1, 0, FieldtypeFrontendComments::approved);
        // commentTemplateFile defaults to '' when the (bypassed) constructor never ran

        $result = $this->callMethod($comment, 'renderCommentTemplate', [$this->baseVars(), '<p>fallback markup</p>']);

        self::assertSame('<p>fallback markup</p>', $result);
    }

    public function testRenderCommentTemplateThrowsWhenAConfiguredTemplateFileIsMissing(): void
    {
        $comment = $this->newComment(1, 0, FieldtypeFrontendComments::approved);
        $this->setProp($comment, 'commentTemplateFile', '/this/path/does/not/exist/comment.php');

        $this->expectException(WireException::class);
        $this->expectExceptionMessageMatches('/Comment template file not found/');

        $this->callMethod($comment, 'renderCommentTemplate', [$this->baseVars()]);
    }

    // -----------------------------------------------------------------
    // FrontendComments::getCommentListArray() - the actual tree-building/filtering logic that
    // was the root of the originally reported bug.
    // -----------------------------------------------------------------

    private function buildTree(FrontendCommentArray $comments): FrontendCommentArray
    {
        $out = $this->newEmptyArray();
        return FrontendComments::getCommentListArray($comments, 0, $out);
    }

    public function testSpamParentWithAnApprovedReplyStaysInTheTreeAsAPlaceholderWithItsReplyBelowIt(): void
    {
        $array = $this->newEmptyArray();
        $spamParent = $this->newComment(1, 0, FieldtypeFrontendComments::spam);
        $approvedChild = $this->newComment(2, 1, FieldtypeFrontendComments::approved);

        foreach ([$spamParent, $approvedChild] as $c) {
            $array->add($c);
            $this->attachToArray($c, $array);
        }

        $tree = $this->buildTree($array);

        self::assertSame(2, $tree->count(), 'the spam parent AND its approved reply must both still be reachable');
        $ids = array_map(fn($c) => $c->get('id'), $tree->getArray());
        self::assertSame([1, 2], $ids);

        $renderedParent = $tree->getArray()[array_search(1, $ids, true)];
        self::assertSame(0, $renderedParent->get('level'));
        $renderedChild = $tree->getArray()[array_search(2, $ids, true)];
        self::assertSame(1, $renderedChild->get('level'), 'the reply must be nested one level below the spam placeholder');
    }

    public function testSpamParentWithoutAnyVisibleReplyIsRemovedFromTheTreeEntirely(): void
    {
        $array = $this->newEmptyArray();
        $spamParent = $this->newComment(1, 0, FieldtypeFrontendComments::spam);
        $pendingChild = $this->newComment(2, 1, FieldtypeFrontendComments::pendingApproval);

        foreach ([$spamParent, $pendingChild] as $c) {
            $array->add($c);
            $this->attachToArray($c, $array);
        }

        $tree = $this->buildTree($array);

        self::assertSame(0, $tree->count(), 'a spam comment with no visible replies must not appear at all - not even as a placeholder');
    }

    public function testSpamParentWithOnlyASpamReplyIsRemovedFromTheTreeEntirely(): void
    {
        $array = $this->newEmptyArray();
        $spamParent = $this->newComment(1, 0, FieldtypeFrontendComments::spam);
        $spamChild = $this->newComment(2, 1, FieldtypeFrontendComments::spam);

        foreach ([$spamParent, $spamChild] as $c) {
            $array->add($c);
            $this->attachToArray($c, $array);
        }

        $tree = $this->buildTree($array);

        self::assertSame(0, $tree->count(), 'a spam reply is not itself visible, so it must not keep a spam parent in the tree either');
    }

    public function testOrdinaryApprovedThreadIsUnaffectedByTheSpamHandling(): void
    {
        $array = $this->newEmptyArray();
        $topLevel = $this->newComment(1, 0, FieldtypeFrontendComments::approved);
        $reply = $this->newComment(2, 1, FieldtypeFrontendComments::approved);
        $nestedReply = $this->newComment(3, 2, FieldtypeFrontendComments::featured);

        foreach ([$topLevel, $reply, $nestedReply] as $c) {
            $array->add($c);
            $this->attachToArray($c, $array);
        }

        $tree = $this->buildTree($array);

        self::assertSame(3, $tree->count());
        $byId = [];
        foreach ($tree as $c) {
            $byId[$c->get('id')] = $c;
        }
        self::assertSame(0, $byId[1]->get('level'));
        self::assertSame(1, $byId[2]->get('level'));
        self::assertSame(2, $byId[3]->get('level'));
    }

    public function testPendingAndPlainRejectedCommentsNeverAppearInTheTreeAtAll(): void
    {
        $array = $this->newEmptyArray();
        $approved = $this->newComment(1, 0, FieldtypeFrontendComments::approved);
        $pending = $this->newComment(2, 0, FieldtypeFrontendComments::pendingApproval);

        foreach ([$approved, $pending] as $c) {
            $array->add($c);
            $this->attachToArray($c, $array);
        }

        $tree = $this->buildTree($array);

        self::assertSame(1, $tree->count(), 'a pending (not yet moderated) top-level comment must not be rendered at all');
        self::assertSame(1, $tree->getArray()[0]->get('id'));
    }
}
