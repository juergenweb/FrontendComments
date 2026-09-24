<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use FrontendComments\FrontendCommentArray;
use FrontendComments\FrontendComments;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\Page;
use Tests\Support\TestCase;

/**
 * Regression tests for a second, independent occurrence of the same "pagination not shown" bug
 * class already fixed in FrontendCommentPagination.php: on a comment field that has never had
 * "input_fc_pagnumber" explicitly saved (e.g. created before this setting existed), Field::get()
 * returns null here too, not the configured default of 10 (see
 * FieldtypeFrontendComments::getDefaultData()) - that default only applies inside getConfigValue(),
 * used to render the admin config form, never by a direct field->get() read.
 *
 * FrontendComments.php reads this same setting TWICE, independently of FrontendCommentPagination.php:
 *  1) In its own constructor, into the non-nullable `int|string $num_comments_on_page` property -
 *     assigning null there throws a TypeError on every single comment render for such a field.
 *  2) Again in getCommentsForDisplay() (used to slice the comment list itself for the current page) -
 *     same TypeError, same property.
 *
 * Both are fixed identically to FrontendCommentPagination.php's own fix: fall back to
 * FieldtypeFrontendComments::getDefaultData()['input_fc_pagnumber'] when the field value is null.
 */
final class FrontendCommentsPaginationNumberTest extends TestCase
{
    /** A field that deliberately never had "input_fc_pagnumber" explicitly saved. */
    private function unconfiguredField(): \ProcessWire\Field
    {
        return $this->newField(1, 'comments');
    }

    private function newArrayWithApprovedComments(\ProcessWire\Field $field, int $count): FrontendCommentArray
    {
        $array = $this->newWithoutConstructor(FrontendCommentArray::class);
        $this->setProp($array, 'field', $field);

        $page = new Page();
        $page->id = 1;
        $this->setProp($array, 'page', $page);

        for ($i = 1; $i <= $count; $i++) {
            $comment = $this->newWithoutConstructor(FrontendComment::class);
            $comment->set('id', $i);
            $comment->set('parent_id', 0);
            $comment->set('status', FieldtypeFrontendComments::approved);
            $comment->set('sort', $i);
            $this->setProp($comment, 'comments', $array);
            $array->add($comment);
        }

        return $array;
    }

    public function testConstructorDoesNotFatalWhenPaginationNumberWasNeverConfigured(): void
    {
        $array = $this->newArrayWithApprovedComments($this->unconfiguredField(), 15);

        // The real constructor - this must not throw a TypeError.
        $comments = new FrontendComments($array);

        self::assertSame(10, $this->getProp($comments, 'num_comments_on_page'), 'an unconfigured field must fall back to the documented default of 10 comments per page');
    }

    public function testGetCommentsForDisplaySlicesToTheDefaultPageSizeWhenNeverConfigured(): void
    {
        // 15 approved, top-level comments with the default of 10 per page: page 1 must show exactly
        // the first 10, not fatal and not silently show all 15 on one page.
        $array = $this->newArrayWithApprovedComments($this->unconfiguredField(), 15);
        $comments = new FrontendComments($array);

        $display = $this->callMethod($comments, 'getCommentsForDisplay');

        self::assertSame(10, $display->count(), 'an unconfigured field must fall back to the documented default of 10 comments per page when slicing for display');
    }
}
