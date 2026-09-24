<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendComment;
use ProcessWire\Config;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\Page;
use ProcessWire\TestServices;
use ProcessWire\WireInput;
use Tests\Support\TestCase;

/**
 * Unit tests for FrontendComment.php helper methods that don't already have dedicated coverage
 * elsewhere (FrontendCommentXssTest.php covers the escaping fixes, TombstoneRenderingTest.php
 * covers the spam-placeholder/tree logic).
 */
final class FrontendCommentTest extends TestCase
{
    private function newComment(int $status = 0): FrontendComment
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('status', $status);
        return $comment;
    }

    // -----------------------------------------------------------------
    // isPublished()
    // -----------------------------------------------------------------

    public function testIsPublishedIsTrueForApprovedAndFeaturedOnly(): void
    {
        self::assertTrue($this->newComment(FieldtypeFrontendComments::approved)->isPublished());
        self::assertTrue($this->newComment(FieldtypeFrontendComments::featured)->isPublished());
        self::assertFalse($this->newComment(FieldtypeFrontendComments::pendingApproval)->isPublished());
        self::assertFalse($this->newComment(FieldtypeFrontendComments::spam)->isPublished());
    }

    // -----------------------------------------------------------------
    // getPreviousCommentStatus()
    //
    // Locks in a previous bugfix documented directly in the method's own docblock: it used to read
    // Wire::getChanges(true)['status'][0], which never actually returned a real previous value
    // because this module never enables trackChangesValues - it now reads the separately-maintained
    // 'old_status' property instead. A regression here would silently bring that dead code path back.
    // -----------------------------------------------------------------

    public function testGetPreviousCommentStatusReadsTheOldStatusProperty(): void
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('old_status', FieldtypeFrontendComments::pendingApproval);

        self::assertSame(FieldtypeFrontendComments::pendingApproval, $comment->getPreviousCommentStatus());
    }

    public function testGetPreviousCommentStatusIsNullWhenNeverSet(): void
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);

        self::assertNull($comment->getPreviousCommentStatus());
    }

    // -----------------------------------------------------------------
    // formIsAjaxLoaded() / formIsSubmitted()
    // -----------------------------------------------------------------

    public function testFormIsAjaxLoadedOnlyWhenAjaxAndCommentIdMatch(): void
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);

        /** @var Config $config */
        $config = TestServices::get('config');
        /** @var WireInput $input */
        $input = TestServices::get('input');

        $config->ajax = true;
        $input->setGet(['commentid' => '42']);
        self::assertTrue($this->callMethod($comment, 'formIsAjaxLoaded', [42]));
        self::assertFalse($this->callMethod($comment, 'formIsAjaxLoaded', [43]), 'a different comment id must not match');

        $config->ajax = false;
        self::assertFalse($this->callMethod($comment, 'formIsAjaxLoaded', [42]), 'must be false outside of an ajax request even if the id matches');
    }

    public function testFormIsSubmittedChecksForTheExpectedPostKey(): void
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $comment->set('id', 7);
        $page = new Page();
        $page->id = 3;
        $this->setProp($comment, 'page', $page);

        $originalPost = $_POST;
        try {
            $_POST = [];
            self::assertFalse($this->callMethod($comment, 'formIsSubmitted'));

            $_POST = ['reply-form-7-ajax-3-comments-7' => 'something'];
            self::assertTrue($this->callMethod($comment, 'formIsSubmitted'));

            $_POST = ['reply-form-999-ajax-3-comments-7' => 'something']; // a different comment's key
            self::assertFalse($this->callMethod($comment, 'formIsSubmitted'));
        } finally {
            $_POST = $originalPost;
        }
    }

    // -----------------------------------------------------------------
    // getFormattedCommentCreationDate()
    // -----------------------------------------------------------------

    public function testGetFormattedCommentCreationDateUsesAbsoluteFormatWhenFormatIsZero(): void
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $this->setProp($comment, 'frontendFormsConfig', [
            'input_dateformat' => 'Y-m-d',
            'input_timeformat' => 'H:i',
        ]);

        $ts = mktime(14, 30, 0, 6, 15, 2026);
        $result = $this->callMethod($comment, 'getFormattedCommentCreationDate', [$ts, 0]);

        self::assertSame('2026-06-15 14:30', $result);
    }

    public function testGetFormattedCommentCreationDateUsesRelativeFormatOtherwise(): void
    {
        $comment = $this->newWithoutConstructor(FrontendComment::class);
        $this->setProp($comment, 'frontendFormsConfig', [
            'input_dateformat' => 'Y-m-d',
            'input_timeformat' => 'H:i',
        ]);

        $ts = mktime(14, 30, 0, 6, 15, 2026);
        $result = $this->callMethod($comment, 'getFormattedCommentCreationDate', [$ts, 1]);

        self::assertSame('RELATIVE(' . $ts . ')', $result, 'a non-zero format must go through the relative-time branch, not the absolute date+time one');
    }

    // -----------------------------------------------------------------
    // ___renderStarsOnly() - a pure, static, self-contained method (no ProcessWire dependency at
    // all), so this exercises the REAL method directly with no stubs involved.
    // -----------------------------------------------------------------

    public function testRenderStarsOnlyReturnsNothingWhenRatingDisplayIsOff(): void
    {
        self::assertSame('', FrontendComment::___renderStarsOnly(4.5, 0));
        self::assertSame('', FrontendComment::___renderStarsOnly(4.5, null));
    }

    public function testRenderStarsOnlyRendersNothingForNullStarsUnlessShowNullIsSet(): void
    {
        self::assertSame('', FrontendComment::___renderStarsOnly(null, 1, false));
    }

    public function testRenderStarsOnlyTreatsNullAsZeroWhenShowNullIsSet(): void
    {
        $html = FrontendComment::___renderStarsOnly(null, 1, true);

        self::assertSame(5, substr_count($html, 'class="fcm-star"'), 'null with showNull=true must render as zero stars: 5 empty, 0 full, 0 half');
        self::assertStringNotContainsString('fcm-star on', $html);
        self::assertStringNotContainsString('fcm-star half', $html);
    }

    public function testRenderStarsOnlyRendersWholeNumberOfFullStars(): void
    {
        $html = FrontendComment::___renderStarsOnly(3, 1);

        self::assertSame(3, substr_count($html, 'fcm-star on'));
        self::assertStringNotContainsString('fcm-star half', $html);
        self::assertSame(2, substr_count($html, 'class="fcm-star"'), '3 full leaves 2 empty out of 5');
    }

    public function testRenderStarsOnlyRendersAHalfStarForAHalfRating(): void
    {
        $html = FrontendComment::___renderStarsOnly(3.5, 1);

        self::assertSame(3, substr_count($html, 'fcm-star on'));
        self::assertSame(1, substr_count($html, 'fcm-star half'));
        self::assertSame(1, substr_count($html, 'class="fcm-star"'));
    }

    public function testRenderStarsOnlyRendersAFullFiveStars(): void
    {
        $html = FrontendComment::___renderStarsOnly(5, 1);

        self::assertSame(5, substr_count($html, 'fcm-star on'));
        self::assertStringNotContainsString('fcm-star half', $html);
        self::assertSame(0, substr_count($html, 'class="fcm-star"'));
    }
}
