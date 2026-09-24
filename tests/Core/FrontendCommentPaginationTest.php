<?php

declare(strict_types=1);

namespace Tests\Core;

use FrontendComments\FrontendCommentPagination;
use FrontendForms\Link;
use FrontendForms\TextElements;
use Tests\Support\TestCase;

/**
 * Unit tests for FrontendCommentPagination.php's pure calculation methods (page-count and
 * "Showing x to y of z" text) and for getPaginationItems()/renderNavItems(), the sliding-window
 * logic that decides which page-number links to actually show (always the current page +/- 2,
 * plus a "jump to page 1"/"jump to the last page" link and "…" separators once the window no
 * longer reaches that edge on its own). This class previously had zero test coverage. Its
 * constructor builds a large tree of markup elements that isn't run here - newPagination() below
 * rebuilds, by hand, exactly the handful of pre-configured Link/TextElements templates and int
 * properties these methods actually read (see FrontendCommentPagination::__construct() for the
 * real thing), all via reflection (newWithoutConstructor() + setProp()).
 */
final class FrontendCommentPaginationTest extends TestCase
{
    private function newPagination(
        int $totalComments,
        int $numCommentsPage,
        int $currentPage = 1,
        string $pageUrl = 'https://example.com/test-page/',
        string $pageNumPrefix = 'page',
        string $anchorID = 'comments-comments-container'
    ): FrontendCommentPagination {
        $pagination = $this->newWithoutConstructor(FrontendCommentPagination::class);
        $this->setProp($pagination, 'totalComments', $totalComments);
        $this->setProp($pagination, 'numCommentsPage', $numCommentsPage);
        $this->setProp($pagination, 'currentPage', $currentPage);
        $this->setProp($pagination, 'pageNumPrefix', $pageNumPrefix);
        $this->setProp($pagination, 'anchorID', $anchorID);

        // paginationPagesNumber() is already covered directly below - reuse it here so this helper
        // can never silently drift out of sync with that logic.
        $pages = $this->callMethod($pagination, 'paginationPagesNumber');
        $this->setProp($pagination, 'paginationPagesNumber', $pages);

        // Rebuild the same pre-configured Link/TextElements templates the real constructor sets up
        // (see FrontendCommentPagination::__construct()) - getPaginationItems()/renderNavItems()
        // only read these already-configured templates, they never build them from scratch.
        $this->setProp($pagination, 'startPageLink', (new Link())
            ->setLinkText('1')->setUrl($pageUrl)->setQueryString($pageNumPrefix . '=1')->setAnchor($anchorID));
        $this->setProp($pagination, 'startLi', (new TextElements())->setTag('li'));

        $this->setProp($pagination, 'dotsLi', (new TextElements())->setTag('li')->setContent('<span>…</span>'));

        $this->setProp($pagination, 'navPageLink', (new Link())->setAnchor($anchorID));
        $this->setProp($pagination, 'navPageLi', (new TextElements())->setTag('li'));

        $this->setProp($pagination, 'currentPageLink', (new Link())
            ->setLinkText((string)$currentPage)->setUrl($pageUrl)->setQueryString($pageNumPrefix . '=' . $currentPage)
            ->setAnchor($anchorID)->setAttribute('aria-current', 'page'));
        $this->setProp($pagination, 'currentPageLi', (new TextElements())->setTag('li'));

        $this->setProp($pagination, 'endPageLink', (new Link())
            ->setLinkText((string)$pages)->setUrl($pageUrl)->setQueryString($pageNumPrefix . '=' . $pages)->setAnchor($anchorID));
        $this->setProp($pagination, 'endPageLi', (new TextElements())->setTag('li'));

        return $pagination;
    }

    /** @return string[] rendered HTML of each item, in order */
    private function renderedItems(FrontendCommentPagination $pagination): array
    {
        return array_map(fn($item) => $item->render(), iterator_to_array($pagination->getPaginationItems()));
    }

    // -----------------------------------------------------------------
    // paginationPagesNumber()
    // -----------------------------------------------------------------

    public function testPaginationPagesNumberRoundsUpToTheNextFullPage(): void
    {
        $pagination = $this->newPagination(totalComments: 25, numCommentsPage: 10);

        self::assertSame(3, $this->callMethod($pagination, 'paginationPagesNumber'));
    }

    public function testPaginationPagesNumberIsExactWhenCommentsDivideEvenly(): void
    {
        $pagination = $this->newPagination(totalComments: 20, numCommentsPage: 10);

        self::assertSame(2, $this->callMethod($pagination, 'paginationPagesNumber'));
    }

    public function testPaginationPagesNumberFallsBackToTheExistingValueWhenPaginationIsDisabled(): void
    {
        $pagination = $this->newPagination(totalComments: 25, numCommentsPage: 0);
        $this->setProp($pagination, 'paginationPagesNumber', 0);

        self::assertSame(0, $this->callMethod($pagination, 'paginationPagesNumber'), 'a numCommentsPage of 0 (pagination disabled) must skip the division entirely, not divide by zero');
    }

    // -----------------------------------------------------------------
    // createPageOfPageText()
    // -----------------------------------------------------------------

    public function testCreatePageOfPageTextForTheFirstPage(): void
    {
        $pagination = $this->newPagination(totalComments: 25, numCommentsPage: 10, currentPage: 1);

        self::assertSame('Showing 1 to 10 of 25 comments', $this->callMethod($pagination, 'createPageOfPageText'));
    }

    public function testCreatePageOfPageTextForALaterPageClampsTheEndToTheTotal(): void
    {
        $pagination = $this->newPagination(totalComments: 25, numCommentsPage: 10, currentPage: 3);

        self::assertSame('Showing 21 to 25 of 25 comments', $this->callMethod($pagination, 'createPageOfPageText'));
    }

    public function testCreatePageOfPageTextForAMiddlePageUsesAFullPageWidth(): void
    {
        $pagination = $this->newPagination(totalComments: 25, numCommentsPage: 10, currentPage: 2);

        self::assertSame('Showing 11 to 20 of 25 comments', $this->callMethod($pagination, 'createPageOfPageText'));
    }

    public function testCreatePageOfPageTextIsEmptyWhenStartAndEndAreTheSame(): void
    {
        // exactly one comment total: start=1, end=1 - the method deliberately renders nothing at
        // all in that case, rather than something like "Showing 1 to 1 of 1 comments"
        $pagination = $this->newPagination(totalComments: 1, numCommentsPage: 10, currentPage: 1);

        self::assertSame('', $this->callMethod($pagination, 'createPageOfPageText'));
    }

    // -----------------------------------------------------------------
    // getPaginationItems() - which page-number links/dots/start-end links are shown
    // -----------------------------------------------------------------

    public function testOnlyACurrentPageIndicatorWhenThereIsOnlyOnePage(): void
    {
        // Real bug found and fixed: this used to assert 0 items here (the pre-fix behaviour), which
        // meant a single page of comments rendered a completely empty, invisible pagination-wrapper
        // in the real HTML output (the outer wrapper/nav/ul still got built by
        // ___renderPaginationMarkup()'s own, separate gate - only the <li> items themselves were
        // missing) - reported live as "pagination wird nicht angezeigt" with e.g. exactly 4 comments
        // and "4 per page" configured (4 total / 4 per page = 1 page). Now a single page still shows
        // its own "page 1" indicator, consistent with ___renderPaginationMarkup()'s gate of "show
        // whenever there is at least one page" (`ceil($totalComments / $numCommentsPage) > 0`).
        $pagination = $this->newPagination(totalComments: 5, numCommentsPage: 10);

        $items = $this->renderedItems($pagination);

        self::assertCount(1, $items, 'a single page must still show its own "page 1" indicator, not nothing at all');
        self::assertStringContainsString('aria-current="page"', $items[0], 'the lone page is marked as the current page');
    }

    public function testFewPagesAllFitInTheWindowWithoutAStartItemOrAnyDots(): void
    {
        // 3 pages total, all already inside currentPage-2..currentPage+2 - no need for a separate
        // "jump to page 1" link or "…" separators at all
        $pagination = $this->newPagination(totalComments: 30, numCommentsPage: 10, currentPage: 1);

        $items = $this->renderedItems($pagination);

        self::assertCount(3, $items, 'exactly one item per page, no start item, no dots');
        self::assertStringContainsString('aria-current="page"', $items[0], 'the current page (1) is marked current');
        self::assertStringContainsString('page=2', $items[1]);
        self::assertStringContainsString('page=3', $items[2]);
    }

    public function testManyPagesShowStartItemDotsTheWindowDotsAndAnEndItem(): void
    {
        // 20 pages, sitting on page 10: far enough from both edges that a separate start item,
        // both sets of dots, and a separate end item are all needed around the current window
        $pagination = $this->newPagination(totalComments: 200, numCommentsPage: 10, currentPage: 10);

        $items = $this->renderedItems($pagination);

        self::assertCount(9, $items, 'start + dots + 5 window pages (8..12) + dots + end');
        self::assertStringContainsString('page=1', $items[0], 'separate "jump to first page" item');
        self::assertStringContainsString('…', $items[1], 'dots before the window');
        self::assertStringContainsString('page=8', $items[2]);
        self::assertStringContainsString('page=9', $items[3]);
        self::assertStringContainsString('aria-current="page"', $items[4], 'the current page (10) sits in the middle of the window');
        self::assertStringContainsString('page=11', $items[5]);
        self::assertStringContainsString('page=12', $items[6]);
        self::assertStringContainsString('…', $items[7], 'dots after the window');
        self::assertStringContainsString('page=20', $items[8], 'separate "jump to last page" item');
    }

    public function testWindowAlreadyCoversPageOneSoNoStartItemOrLeadingDotsAreAdded(): void
    {
        // currentPage=3 with plenty of pages: the window (1..5) already reaches page 1 on its own,
        // so the extra start item/dots would just be redundant. Page 1 still shows up - just as a
        // normal window item instead of the dedicated start-item template.
        $pagination = $this->newPagination(totalComments: 200, numCommentsPage: 10, currentPage: 3);

        $items = $this->renderedItems($pagination);

        self::assertCount(7, $items, '5 window pages (1..5) + trailing dots + end, no separate start item, no leading dots');
        self::assertStringContainsString('page=1', $items[0]);
        self::assertStringNotContainsString('…', $items[0], 'page 1 is a normal window item here, not preceded by dots');
        self::assertStringContainsString('aria-current="page"', $items[2], 'current page (3) is the third window item');
        self::assertStringContainsString('page=5', $items[4], 'last window item');
        self::assertStringContainsString('…', $items[5], 'trailing dots before the separate end item');
        self::assertStringContainsString('page=20', $items[6], 'separate "jump to last page" item');
    }

    public function testWindowAlreadyCoversTheLastPageSoNoEndItemOrTrailingDotsAreAdded(): void
    {
        // currentPage=18 of 20: the window (16..20) already reaches the last page on its own. Page
        // 20 still shows up - just as a normal window item instead of the dedicated end-item template.
        $pagination = $this->newPagination(totalComments: 200, numCommentsPage: 10, currentPage: 18);

        $items = $this->renderedItems($pagination);

        self::assertCount(7, $items, 'start + leading dots + 5 window pages (16..20), no trailing dots, no separate end item');
        self::assertStringContainsString('page=1', $items[0], 'separate "jump to first page" item');
        self::assertStringContainsString('…', $items[1], 'leading dots before the window');
        self::assertStringContainsString('aria-current="page"', $items[4], 'current page (18) is the third window item');
        self::assertStringContainsString('page=20', $items[6], 'last window item');
        self::assertStringNotContainsString('…', $items[6], 'the window itself reaches the last page - no extra dots/end item needed');
    }

    public function testCurrentPageOfZeroIsTreatedAsPageOneForTheWindowMath(): void
    {
        // Defensive fallback right at the top of the method: "if (!$this->currentPage)
        // $this->currentPage = 1;" - without it, a currentPage of 0 (possible via a hand-crafted
        // ?page=0) would make the loop start at -2 and never match anything as "current". This only
        // corrects the window/loop math, not the pre-built currentPageLink's own text/href (those
        // were already set from the original, un-corrected currentPage before this method runs) -
        // so this test checks only what the fallback actually affects: exactly one item ends up
        // marked as the current page, and it is the first one (i.e. "page 1").
        $pagination = $this->newPagination(totalComments: 200, numCommentsPage: 10, currentPage: 0);

        $items = $this->renderedItems($pagination);

        self::assertStringContainsString('aria-current="page"', $items[0], 'the window must settle on page 1 as current, not fail to mark any page as current at all');
    }

    // -----------------------------------------------------------------
    // renderNavItems()
    // -----------------------------------------------------------------

    public function testRenderNavItemsConcatenatesEachPaginationItemInOrder(): void
    {
        $pagination = $this->newPagination(totalComments: 200, numCommentsPage: 10, currentPage: 10);

        $expected = implode('', $this->renderedItems($pagination));

        self::assertSame($expected, $this->callMethod($pagination, 'renderNavItems'));
    }
}
