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
 * Regression test for a real, live-reported bug: "Anzahl der Kommentare pro Seite ist auf 4
 * eingestellt. Es werden nur 4 Kommentare angezeigt, aber die Pagination nicht" - i.e. with
 * "comments per page" explicitly configured (not the null-safety bug fixed elsewhere) AND the
 * total comment count landing exactly on one full page (4 comments, 4 per page here), the
 * pagination controls did not appear at all.
 *
 * Root cause, found in FrontendCommentPagination::getPaginationItems(): it only added any <li>
 * items at all when `$pages > 1`. ___renderPaginationMarkup()'s own gate is more permissive
 * (`ceil($totalComments / $numCommentsPage) > 0`, true for any totalComments > 0), so for exactly
 * one page the surrounding <div class="outer-pagination-wrapper">...<nav class="pagination-wrapper">
 * <ul class="pagination"></ul></nav></div> markup WAS still built and output - just with a
 * completely empty <ul>, and (if "Showing x to y of z" is switched off) no visible text either.
 * The result was pagination markup that technically exists in the HTML (so grepping the page
 * source for e.g. "pagination-wrapper" does NOT reveal the bug) but renders as literally nothing
 * on the page - exactly matching "die Pagination wird nicht angezeigt" with no PHP error at all.
 *
 * Fix: getPaginationItems() now uses `$pages >= 1`, so a single page still gets its own "page 1"
 * indicator - consistent with the outer gate's evident intent of showing pagination whenever there
 * is at least one comment. Multi-page behaviour (2+ pages) is unchanged; see
 * FrontendCommentPaginationTest.php for that coverage.
 */
final class FrontendCommentPaginationSinglePageTest extends TestCase
{
    private function newArrayWithComments(int $pagnumber, int $totalTopLevelComments): FrontendCommentArray
    {
        $field = $this->newField(1, 'comments')->setArray([
            'input_fc_pagnumber' => $pagnumber,
            'input_fc_pagorientation' => 'center',
            'input_fc_emailtype' => 'text',
            'input_fc_default_to' => 'admin@example.com',
        ]);

        $array = new FrontendCommentArray();
        $this->setProp($array, 'field', $field);

        $page = new Page();
        $page->id = 1;
        $page->httpUrl = 'https://example.com/test-page/';
        $page->url = '/test-page/';
        $this->setProp($array, 'page', $page);

        for ($i = 1; $i <= $totalTopLevelComments; $i++) {
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

    /** Mirrors render()'s own sequence: renderComments() (which filters $array in place via
     *  getCommentsForDisplay()) followed by renderPagination() on the same array object. */
    private function renderPaginationAfterComments(FrontendCommentArray $array): string
    {
        $fc = new FrontendComments($array);
        $ref = new \ReflectionMethod($fc, 'getCommentsForDisplay');
        $ref->setAccessible(true);
        $ref->invoke($fc); // triggers the same filter() mutation renderComments() would

        return $array->renderPagination();
    }

    public function testPaginationIsVisibleWhenCommentCountExactlyFillsOnePage(): void
    {
        // Exactly the reported scenario: 4 comments, "4 per page" configured -> exactly one page.
        $array = $this->newArrayWithComments(pagnumber: 4, totalTopLevelComments: 4);

        $out = $this->renderPaginationAfterComments($array);

        self::assertStringContainsString('pagination-wrapper', $out, 'the pagination wrapper markup must be present');
        self::assertStringContainsString('currentpage', $out, 'a single page must still show a "page 1" indicator, not an empty <ul>');
        self::assertStringContainsString('aria-current="page"', $out);
    }

    public function testPaginationStillShowsBothPagesWhenCommentCountExceedsOnePage(): void
    {
        // Control case: one more comment than fits on a page -> 2 pages, already-existing behaviour
        // must be unaffected by the fix.
        $array = $this->newArrayWithComments(pagnumber: 4, totalTopLevelComments: 5);

        $out = $this->renderPaginationAfterComments($array);

        self::assertStringContainsString('page=2', $out, 'a second page link must still be shown when there genuinely are 2 pages');
    }
}
