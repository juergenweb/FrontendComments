<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use FrontendComments\FrontendComments;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\Page;
use ProcessWire\WireArray;
use Tests\Support\TestCase;

/**
 * Regression test for a real, live-reported bug: pagination showed "Showing 1 to 4 of 9 comments"
 * although only 8 comments actually had status "approved" (no "featured" comments existed either) -
 * the reported expectation being that only approved/featured comments are ever counted or shown,
 * never pending or spam ones.
 *
 * Root cause, in FrontendComments::getCommentsForDisplay(): $this->comments->filter(...) is
 * deliberately kept to approved+featured+SPAM (not just approved+featured), because a spam comment
 * that still has approved/featured replies underneath it must stay reachable so
 * getCommentListArray()'s recursion can still find and render those replies. getCommentListArray()
 * then prunes a spam comment back out - per candidate, at every tree level - the moment it turns out
 * to have no visible (approved/featured) replies of its own (see FrontendComment::hasVisibleReplies()).
 * That pruning, however, only ever happened on getCommentListArray()'s own LOCAL copy of the
 * candidates at each level, never on $this->comments itself. FrontendCommentPagination reads its
 * totalComments straight from that same, shared $this->comments array (via
 * FrontendCommentArray::getPagination() -> getTotalComments() -> count()), so a spam comment with no
 * visible replies stayed counted in the pagination total forever, even though it is never actually
 * rendered anywhere.
 *
 * Fix: getCommentsForDisplay() now prunes the exact same "spam without visible replies" comments
 * from $this->comments right after the filter() call, so the count later read for pagination matches
 * what actually gets displayed.
 */
final class FrontendCommentsTotalCountExcludesInvisibleSpamTest extends TestCase
{
    private function newArray(): FrontendCommentArray
    {
        // Deliberately larger than the comment counts used below, so slicing for "page 1" never
        // interferes with the counts these tests are actually about.
        $field = $this->newField(1, 'comments')->setArray([
            'input_fc_pagnumber' => 20,
        ]);

        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $this->setProp($array, 'field', $field);

        $page = new Page();
        $page->id = 1;
        $this->setProp($array, 'page', $page);

        return $array;
    }

    private function addComment(FrontendCommentArray $array, int $id, int $status, int $parentId = 0): FrontendComment
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('id', $id);
        $comment->set('parent_id', $parentId);
        $comment->set('status', $status);
        $comment->set('sort', $id);
        $this->setProp($comment, 'comments', $array);
        $array->add($comment);

        return $comment;
    }

    public function testSpamCommentWithoutVisibleRepliesIsExcludedFromTheTotalCount(): void
    {
        // Exactly the reported scenario: 8 approved, top-level comments, plus 1 more comment marked
        // as spam with no replies at all - reported total must be 8, not 9.
        $array = $this->newArray();
        for ($i = 1; $i <= 8; $i++) {
            $this->addComment($array, $i, FieldtypeFrontendComments::approved);
        }
        $this->addComment($array, 9, FieldtypeFrontendComments::spam);

        $comments = new FrontendComments($array);
        $ref = new \ReflectionMethod($comments, 'getCommentsForDisplay');
        $ref->setAccessible(true);
        $ref->invoke($comments);

        self::assertSame(8, $array->getTotalComments(), 'a spam comment with no visible replies must not inflate the total comment count used for pagination');
    }

    public function testSpamCommentWithVisibleApprovedReplyIsStillCountedAndReachable(): void
    {
        // Control case: a spam comment that DOES have an approved reply underneath it must stay
        // reachable (so the reply itself is still rendered) and must still be counted, since its
        // own "invisible" status doesn't apply once it has visible children the tree needs to reach.
        $array = $this->newArray();
        for ($i = 1; $i <= 8; $i++) {
            $this->addComment($array, $i, FieldtypeFrontendComments::approved);
        }
        $this->addComment($array, 9, FieldtypeFrontendComments::spam);
        $this->addComment($array, 10, FieldtypeFrontendComments::approved, parentId: 9);

        $comments = new FrontendComments($array);
        $ref = new \ReflectionMethod($comments, 'getCommentsForDisplay');
        $ref->setAccessible(true);
        $displayed = $ref->invoke($comments);

        self::assertSame(10, $array->getTotalComments(), 'a spam comment with a visible reply must stay counted, since it stays in the tree');
        // 8 approved top-level + the spam comment itself (kept as a placeholder because it has a
        // visible reply, see FrontendComment::isSpamPlaceholder()) + its 1 approved reply = 10.
        self::assertSame(10, $displayed->count(), 'the spam placeholder and the reply underneath it must both still be reachable in the displayed tree');
    }
}
