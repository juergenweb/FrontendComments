<?php

declare(strict_types=1);

namespace Tests\Core;

use Exception;
use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use ProcessWire\Field;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\Page;
use Tests\Support\TestCase;

/**
 * Unit tests for FrontendCommentArray.php configuration/helper methods and the delete guard, that
 * don't already have dedicated coverage elsewhere (FrontendCommentArraySelectorInjectionTest.php and
 * FrontendCommentArrayVoteEchoTest.php cover the security-relevant selector/escaping fixes).
 */
final class FrontendCommentArrayTest extends TestCase
{
    private function newArrayWithField(): FrontendCommentArray
    {
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $this->setProp($array, 'field', $this->newField(1, 'comments'));
        return $array;
    }

    // -----------------------------------------------------------------
    // setModeration()
    // -----------------------------------------------------------------

    public function testSetModerationAcceptsZeroOneOrTwo(): void
    {
        $array = $this->newArrayWithField();

        foreach ([0, 1, 2] as $moderation) {
            $array->setModeration($moderation);
            self::assertSame($moderation, $array->getField()->get('input_fc_moderate'));
        }
    }

    public function testSetModerationRejectsAnythingOutsideZeroToTwo(): void
    {
        $array = $this->newArrayWithField();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Value must be 0, 1 or 2');
        $array->setModeration(3);
    }

    public function testSetModerationRejectsNegativeValues(): void
    {
        $array = $this->newArrayWithField();

        $this->expectException(Exception::class);
        $array->setModeration(-1);
    }

    // -----------------------------------------------------------------
    // getModerationEmail()
    //
    // Three branches, selected by the field's "input_fc_emailtype" config value.
    // -----------------------------------------------------------------

    public function testGetModerationEmailReturnsTheCustomListVerbatimWhenEmailTypeIsCustom(): void
    {
        $array = $this->newArrayWithField();
        $array->getField()->set('input_fc_emailtype', 'custom');
        $array->getField()->set('mod_emails', ['a@example.com', 'b@example.com']);

        self::assertSame(['a@example.com', 'b@example.com'], $array->getModerationEmail());
    }

    public function testGetModerationEmailSplitsTheTextFieldByLineBreaks(): void
    {
        $array = $this->newArrayWithField();
        $array->getField()->set('input_fc_emailtype', 'text');
        $array->getField()->set('input_fc_default_to', "a@example.com\r\nb@example.com");

        self::assertSame(['a@example.com', 'b@example.com'], $array->getModerationEmail());
    }

    public function testGetModerationEmailReturnsTheEmailFieldValueUnchangedForAnyOtherType(): void
    {
        $array = $this->newArrayWithField();
        $array->getField()->set('input_fc_emailtype', 'select');
        $array->getField()->set('input_fc_emailfield', ['admin@example.com']);

        self::assertSame(['admin@example.com'], $array->getModerationEmail(), 'unlike the "text" branch, this value must NOT be preg_split - it is already an array');
    }

    // -----------------------------------------------------------------
    // deleteComment()
    //
    // Guard documented directly at the call site: a comment that still has replies must never be
    // deleted (its replies would become orphaned), so deleteComment() must bail out via hasReplies()
    // before touching the database or removing anything from the array.
    // -----------------------------------------------------------------

    private function newComment(FrontendCommentArray $array, int $id, int $parentId, Field $field, Page $page): FrontendComment
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('id', $id);
        $comment->set('parent_id', $parentId);
        $comment->set('status', FieldtypeFrontendComments::approved);
        $comment->set('field', $field);
        $comment->set('page', $page);
        $this->setProp($comment, 'field', $field);
        $this->setProp($comment, 'page', $page);
        $this->setProp($comment, 'comments', $array);
        $array->add($comment);
        return $comment;
    }

    public function testDeleteCommentRefusesToDeleteACommentThatStillHasReplies(): void
    {
        $array = $this->newArrayWithField();
        $field = $array->getField();
        $page = new Page();
        $page->id = 1;

        $parent = $this->newComment($array, 1, 0, $field, $page);
        $this->newComment($array, 2, 1, $field, $page); // reply to $parent

        self::assertNull($array->deleteComment($parent), 'must refuse (return null) instead of deleting, because $parent still has a reply');
        self::assertSame(2, $array->count(), 'nothing must have been removed from the array');
    }

    public function testDeleteCommentRemovesALeafCommentWithoutReplies(): void
    {
        $array = $this->newArrayWithField();
        $field = $array->getField();
        $page = new Page();
        $page->id = 1;

        $this->newComment($array, 1, 0, $field, $page);
        $leaf = $this->newComment($array, 2, 1, $field, $page); // no replies of its own

        self::assertTrue($array->deleteComment($leaf));
        self::assertSame(1, $array->count(), 'the leaf comment must have been removed, its parent must remain');
    }
}
