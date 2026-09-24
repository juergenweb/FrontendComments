<?php

    declare(strict_types=1);

    namespace FrontendComments;
    /*
 * Class to create and render the pagination for the comments
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: FrontendCommentPagination.php
 * Created: 12.03.2025
 *
 */

    namespace FrontendComments;

    use FrontendForms\Link;
    use FrontendForms\TextElements;
    use ProcessWire\Page;
    use ProcessWire\Field;
    use ProcessWire\Wire;
    use ProcessWire\WireArray;
    use ProcessWire\FieldtypeFrontendComments;

class FrontendCommentPagination extends Wire
{
    protected FrontendCommentArray $comments;
    protected array $frontendFormsConfig = [];
    protected int $totalComments = 0;
    protected int $currentPage = 0;
    // the query-string key used for the pagination page number. Was hardcoded to the literal
    // string 'page' everywhere below (in reading $_GET as well as in every generated pagination
    // link), while FrontendCommentArray::redirectToComment() already builds its "jump to
    // comment" link using the site's configurable $config->pageNumUrlPrefix - so on any site
    // that changes that setting away from "page", this class would never recognize the
    // resulting query string and pagination would silently stay stuck on page 1. Now resolved
    // once in the constructor and reused everywhere a page-number query string is read or built.
    protected string $pageNumPrefix = 'page';

    protected int $numCommentsPage = 10; // set it default to 10
    protected string $alignment = 'center';
    protected int|string|bool $show_pages_of_text = true;
    protected string $anchorID = '';
    protected int $paginationPagesNumber = 0;
    protected Field $field;
    protected Page $page;

    protected TextElements $outerWrapper;
    protected TextElements $paginationWrapper;
    protected TextElements $paginationList;
    protected TextElements $paginationListitem;

    /** Pagination elements */

    protected TextElements $startLi;
    protected Link|TextElements $startPageLink;
    protected TextElements $dotsLi;
    protected TextElements $navPageLi;
    protected Link|TextElements $navPageLink;
    protected TextElements $currentPageLi;
    protected Link|TextElements $currentPageLink;
    protected Link|TextElements $endPageLink;
    protected TextElements $endPageLi;

    protected TextElements $pageOfText;

    protected Link $prevPageLink;
    protected TextElements $prevLi;
    protected Link $nextPageLink;
    protected TextElements $nextPageLi;

    /**
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    public function __construct(FrontendCommentArray $comments)
    {

        parent::__construct();

        $this->comments = $comments;
        $this->field = $comments->getField(); // Processwire comment field object
        $this->page = $comments->getPage(); // the current page object, which contains the comment field
        $this->pageNumPrefix = $this->wire('config')->pageNumUrlPrefix;

        // get configuration values from the FrontendForms module
        $this->frontendFormsConfig = FieldtypeFrontendComments::getFrontendFormsConfigValues();

        // set values
        $this->totalComments = $comments->getTotalComments();
        $this->numCommentsPage = $this->numCommentsPage();

        // A field that has never had this setting explicitly saved (e.g. created before it
        // existed) returns null here, not the configured default of 'center' (see
        // FieldtypeFrontendComments::getDefaultData()) - that default only applies inside
        // getConfigValue(), used to render the admin config form, not by this direct read.
        // $alignment is a non-nullable `string` property, so assigning null directly used to
        // throw a TypeError right here in the constructor.
        $this->alignment = $this->field->get('input_fc_pagorientation') ?? FieldtypeFrontendComments::getDefaultData()['input_fc_pagorientation'];
        $this->paginationPagesNumber = $this->paginationPagesNumber();
        // Check if the page number is specified and check if it's a number, if not, return the default page number which is 1.
        $this->currentPage = isset($_GET[$this->pageNumPrefix]) && is_numeric($_GET[$this->pageNumPrefix]) ? (int)$_GET[$this->pageNumPrefix] : 1;

        /** create all elements for the pagination markup **/

        $this->anchorID = $this->field->name . '-comments-container';

        // outer wrapper (div)
        $this->outerWrapper = new TextElements($this->field->name . '-pagination');
        $this->outerWrapper->setTag('div');
        $this->outerWrapper->setAttribute('class', 'outer-pagination-wrapper');

        // "Show x to y of n pages" text element
        $this->pageOfText = new TextElements();
        $this->pageOfText->setAttribute('class', 'page-of-text');
        $this->pageOfText->setText($this->createPageOfPageText());

        // pagination wrapper (nav)
        $this->paginationWrapper = new TextElements();
        $this->paginationWrapper->setTag('nav');
        $this->paginationWrapper->setAttribute('class', 'pagination-wrapper pagination-' . $this->alignment);
        $this->paginationWrapper->setAttribute('aria-label', $this->_('Comment pagination'));

        // pagination list
        $this->paginationList = new TextElements();
        $this->paginationList->setTag('ul');
        $this->paginationList->setAttribute('class', 'pagination');

        // start the page link
        $this->startPageLink = new Link();
        $this->startPageLink->setLinkText('1');
        $this->startPageLink->setUrl($this->page->url);
        $this->startPageLink->setQueryString($this->pageNumPrefix . '=1');
        $this->startPageLink->setAttribute('title', $this->_('To the first page'));
        $this->startPageLink->setAnchor($this->anchorID);

        // start page list item
        $this->startLi = new TextElements();
        $this->startLi->setTag('li');
        $this->startLi->setAttribute('class', 'start');

        // dots list item
        $this->dotsLi = new TextElements();
        $this->dotsLi->setTag('li');
        $this->dotsLi->setAttribute('class', 'dots');
        $this->dotsLi->setContent('<span>…</span>');

        /* navpage link */
        $this->navPageLink = new Link();
        $this->navPageLink->setAnchor($this->anchorID);

        // nav page list item
        $this->navPageLi = new TextElements();
        $this->navPageLi->setTag('li');
        $this->navPageLi->setAttribute('class', 'page');

        /** current page */

        // current page link
        $this->currentPageLink = new Link();
        $this->currentPageLink->setLinkText((string)$this->currentPage);
        $this->currentPageLink->setUrl($this->page->url);
        $this->currentPageLink->setQueryString($this->pageNumPrefix . '=' . $this->currentPage);
        $this->currentPageLink->setAnchor($this->anchorID);
        $this->currentPageLink->setAttribute('aria-current', 'page');

        // current page list item
        $this->currentPageLi = new TextElements();
        $this->currentPageLi->setTag('li');
        $this->currentPageLi->setAttribute('class', 'currentpage');

        // end page link
        $this->endPageLink = new Link();
        $this->endPageLink->setLinkText((string)$this->paginationPagesNumber);
        $this->endPageLink->setUrl($this->page->url);
        $this->endPageLink->setQueryString($this->pageNumPrefix . '=' . $this->paginationPagesNumber);
        $this->endPageLink->setAttribute('title', $this->_('This is the last page'));
        $this->endPageLink->setAnchor($this->anchorID);

        // end page list item
        $this->endPageLi = new TextElements();
        $this->endPageLi->setTag('li');
        $this->endPageLi->setAttribute('class', 'endpage');
    }

    /**
     * Get the number of comments per page
     *
     * Real bug found and fixed in this session: a field that has never had
     * "input_fc_pagnumber" explicitly saved (e.g. created before this setting existed) returns
     * null from field->get() here, not the configured default of 10 (see
     * FieldtypeFrontendComments::getDefaultData()) - that default only applies inside
     * getConfigValue(), used to render the admin config form, not by this direct read. This
     * method has a non-nullable `int` return type, so returning that null directly used to throw
     * a TypeError right here - and since the constructor calls this on every instantiation
     * (`$this->numCommentsPage = $this->numCommentsPage();`), it fataled on EVERY comment render
     * for such a field. FrontendCommentArray::render() concatenates renderPagination() straight
     * after renderComments() with no try/catch, so that uncaught TypeError took the whole
     * comments + pagination output down with it - which is exactly how "the pagination isn't
     * shown" was reported: the field in question had simply never had its Pagination fieldset
     * saved since upgrading to a module version that introduced input_fc_pagnumber.
     * @return int
     */
    protected function numCommentsPage(): int
    {
        $value = $this->field->get('input_fc_pagnumber');
        return $value === null ? (int)FieldtypeFrontendComments::getDefaultData()['input_fc_pagnumber'] : (int)$value;
    }

    /**
     * Get the alignment value of the pagination
     *
     * Same "never explicitly saved" null-safety as numCommentsPage() above, for the sibling
     * "input_fc_pagorientation" setting - this method's non-nullable `string` return type would
     * otherwise throw a TypeError for the same reason.
     * @return string
     */
    protected function paginationAlignment(): string
    {
        return $this->field->get('input_fc_pagorientation') ?? FieldtypeFrontendComments::getDefaultData()['input_fc_pagorientation'];
    }

    /**
     * Get the number of pagination pages
     * @return int
     */
    protected function paginationPagesNumber(): int
    {
        $pagNumber = $this->paginationPagesNumber;
        if ($this->numCommentsPage) {
            $pagNumber = (int)ceil($this->totalComments / $this->numCommentsPage);
        }
        return $pagNumber;
    }

    /**
     * SETTER methods
     */

    /**
     * Select whether to show or to hide the "show page x to y of n pages" text
     * @param bool $show
     * @return $this
     */
    public function showPagesOfText(bool $show = true): self
    {
        $this->show_pages_of_text = $show;
        return $this;
    }

    /**
     * Set the alignment of the pagination
     * @param string $align
     * @return $this
     */
    public function setAlignment(string $align): self
    {
        $this->alignment = $align;
        return $this;
    }

    /**
     * GETTER methods for pagination elements
     * Can be used for further customization and manipulation
     */

    /**
     * Get the total number of published comments
     * @return int
     */
    public function getTotalComments(): int
    {
        return $this->totalComments;
    }

    /**
     * Get the total number of all pagination pages
     * @return int
     */
    public function getNumberOfPages(): int
    {
        return $this->paginationPagesNumber;
    }

    /**
     * Get the "show page of page" object
     * @return \FrontendForms\TextElements
     */
    public function getPageOfPageText(): TextElements
    {
        return $this->pageOfText;
    }

    /**
     * Get the outer wrapper object
     * @return \FrontendForms\TextElements
     */
    public function getOuterWrapper(): TextElements
    {
        return $this->outerWrapper;
    }

    /**
     * Get the pagination wrapper object
     * @return \FrontendForms\TextElements
     */
    public function getPaginationWrapper(): TextElements
    {
        return $this->paginationWrapper;
    }

    /**
     * Get the pagination list object
     * @return \FrontendForms\TextElements
     */
    public function getPaginationList(): TextElements
    {
        return $this->paginationList;
    }

    /**
     * Get the previous page list item
     * @return \FrontendForms\TextElements
     */
    public function getPrevPageListitem(): TextElements
    {
        return $this->prevLi;
    }

    /**
     * Get the previous page link object
     * @return \FrontendForms\Link
     */
    public function getPrevPageLink(): Link
    {
        return $this->prevPageLink;
    }

    /**
     * Get the start page list item
     * @return \FrontendForms\TextElements
     */
    public function getStartPageListitem(): TextElements
    {
        return $this->startLi;
    }

    /**
     * Get the start page link object
     * @return \FrontendForms\Link
     */
    public function getStartPageLink(): Link
    {
        return $this->startPageLink;
    }

    /**
     * Get the dots' list item
     * @return \FrontendForms\TextElements
     */
    public function getDotsListitem(): TextElements
    {
        return $this->dotsLi;
    }

    /**
     * Get the nav page link object
     * @return \FrontendForms\Link
     */
    public function getNavPageLink(): Link
    {
        return $this->navPageLink;
    }

    /**
     * Get the navpage list item
     * @return \FrontendForms\TextElements
     */
    public function getNavPageListitem(): TextElements
    {
        return $this->navPageLi;
    }

    /**
     * Get the current page link object
     * @return \FrontendForms\Link
     */
    public function getCurrentPageLink(): Link
    {
        return $this->currentPageLink;
    }

    /**
     * Get the current page list item
     * @return \FrontendForms\TextElements
     */
    public function getCurrentPageListitem(): TextElements
    {
        return $this->currentPageLi;
    }

    /**
     * Get the end page link object
     * @return \FrontendForms\Link
     */
    public function getEndPageLink(): Link
    {
        return $this->endPageLink;
    }

    /**
     * Get the "end page" list item
     * @return \FrontendForms\TextElements
     */
    public function getEndPageListitem(): TextElements
    {
        return $this->endPageLi;
    }

    /**
     * Get the next page link object
     * @return \FrontendForms\Link
     */
    public function getNextPageLink(): Link
    {
        return $this->nextPageLink;
    }

    /**
     * Get the next page list item
     * @return \FrontendForms\TextElements
     */
    public function getNextPageListitem(): TextElements
    {
        return $this->nextPageLi;
    }

    /**
     * Get the pagination items as li element objects inside a WireArray
     * @return WireArray
     * @throws \ProcessWire\WireException
     */
    public function getPaginationItems(): WireArray
    {

        $items = new WireArray();

        if (!$this->currentPage) {
            $this->currentPage = 1;
        }

        $pages = 1;
        if ($this->numCommentsPage != 0) {
            $pages = (int)ceil($this->totalComments / $this->numCommentsPage);
        }

        // Real bug found and fixed here: this used to be "if ($pages > 1)", so with EXACTLY one
        // page of comments (total comments <= comments-per-page, e.g. 4 comments with "4 per
        // page" configured) not a single <li> was ever added - not even a "page 1" indicator.
        // ___renderPaginationMarkup()'s own gate (`ceil($this->totalComments / $this->numCommentsPage) > 0`)
        // still let the surrounding wrapper/nav/ul markup through in that case, so the result was
        // an empty, completely invisible pagination-wrapper in the HTML: no error, no missing
        // markup to search for (the wrapper element IS there), just nothing visible inside it -
        // exactly the "pagination wird nicht angezeigt" symptom reported live, and reproduced
        // here with the exact 4 comments / 4-per-page scenario from the field config. Changed to
        // "$pages >= 1" so a single page of comments still shows its "page 1" indicator,
        // consistent with the outer gate's own intent of showing pagination whenever there is at
        // least one comment.
        if ($pages >= 1) {
            // first item
            if (($this->currentPage - 3) > 0) {
                $this->startLi->setContent($this->startPageLink->render());
                $items->add($this->startLi);
            }

            // dots
            if (($this->currentPage - 3) > 1) {
                $items->add($this->dotsLi);
            }

            // Loop for provides links for 2 pages before and after the current page
            for ($i = ($this->currentPage - 2); $i <= ($this->currentPage + 2); $i++) {
                if ($i < 1) {
                    continue;
                }
                if ($i > $pages) {
                    break;
                }
                if ($this->currentPage == $i) {
                    // current page
                    $this->currentPageLink->setAttribute('aria-label', sprintf($this->_('Page %s'), $this->currentPage));
                    $this->currentPageLi->setContent($this->currentPageLink->render());
                    $items->add($this->currentPageLi);
                } else {
                    // normal pages before and after the current page
                    $copy_navPageLi = clone $this->navPageLi;
                    $copy_navPageLink = clone $this->navPageLink;
                    $copy_navPageLink->setLinkText((string)$i);
                    $copy_navPageLink->setQueryString($this->pageNumPrefix . '=' . $i);
                    $copy_navPageLink->setAnchor($this->anchorID);
                    $copy_navPageLink->setAttribute('title', sprintf($this->_('To page %s'), $i));
                    $copy_navPageLi->setContent($copy_navPageLink->render());
                    $items->add($copy_navPageLi);
                }
            }

            // if pages exist after loop's upper limit
            if (($pages - ($this->currentPage + 2)) > 1) {
                // dots
                $items->add($this->dotsLi);
            }
            if (($pages - ($this->currentPage + 2)) > 0) {
                if ($this->currentPage == $pages) {
                    // current page
                    $this->currentPageLink->setAttribute('aria-label', sprintf($this->_('Page %s'), $this->currentPage));
                    $this->currentPageLi->setContent($this->currentPageLink->render());
                    $items->add($this->currentPageLi);
                } else {
                    // last page
                    $this->endPageLi->setContent($this->endPageLink->render());
                    $items->add($this->endPageLi);
                }
            }
        }

        return $items;
    }

    /**
     * Renders all li tags including anchors for the pagination items out of the WireArray
     * @return string
     * @throws \ProcessWire\WireException
     */
    protected function renderNavItems(): string
    {
        $out = '';

        foreach ($this->getPaginationItems() as $element) {
            $out .= $element->render();
        }
        return $out;
    }

    /**
     * Create the text "Showing x to y of z", which should be displayed near the pagination
     * Example output: Showing 1 to 5 of 18
     * @return string
     */
    protected function createPageOfPageText(): string
    {
        $out = '';

        // from
        $start = 1;

        if ($this->currentPage > 1) {
            $start = (($this->currentPage - 1) * $this->numCommentsPage) + 1;
        }

        // end
        $end = $this->totalComments;
        if ($this->totalComments > ($start + $this->numCommentsPage - 1)) {
            $end = $start + $this->numCommentsPage - 1;
        }

        if ($start !== $end) {
            $out = sprintf($this->_('Showing %s to %s of %s comments'), $start, $end, $this->totalComments);
        }

        return $out;
    }

    /**
     * Returns the complete markup of the pagination
     * @return string
     * @throws \ProcessWire\WireException
     */
    public function ___renderPaginationMarkup(): string
    {
        $out = '';

        if (($this->numCommentsPage) && (ceil($this->totalComments / $this->numCommentsPage) > 0)) {
            $li = $this->renderNavItems();
            $ul = $this->paginationList;
            $ul->setContent($li);
            $content = $ul->render();
            $this->paginationWrapper->setContent($content);

            // Show or hide "Show page x of y pages" text above the pagination
            $showPagesOf = '';
            if ($this->show_pages_of_text) {
                $showPagesOf = $this->pageOfText->render();
            }

            $this->outerWrapper->setContent($showPagesOf . $this->paginationWrapper->render());
            $out = $this->outerWrapper->render();
        }
        return $out;
    }

    /**
     * Render the default pagination markup
     *
     * Two real bugs found and fixed in this session, together the actual cause of "the
     * pagination isn't shown at all" on a field that has never had its Pagination fieldset
     * explicitly saved:
     *
     * 1) This method used to re-read $this->field->get('input_fc_pagnumber') directly instead of
     *    reusing the already-defaulted $this->numCommentsPage (see numCommentsPage()'s own
     *    docblock) and compared the raw value with "< 1". For an unconfigured field that value is
     *    null, and "null < 1" is true in PHP, so pagination was silently suppressed here even
     *    after fixing the constructor's own null-safety - the constructor's sensible default of
     *    10 was computed but then never consulted by this gate.
     * 2) The framework-specific subclass name was built as '<Framework>Pagination' (e.g.
     *    "Bootstrap5Pagination"), which never matches any real class - the actual classes are
     *    named 'FrontendCommentPagination<Framework>' (e.g. "FrontendCommentPaginationBootstrap5",
     *    see frameworks/*.php and the correct construction already used elsewhere, e.g.
     *    FrontendCommentArray::getPagination()). class_exists() therefore always returned false
     *    here, silently falling back to the base (unthemed) markup instead of the configured
     *    framework's.
     * @return string
     */
    public function ___render(): string
    {
        if ($this->numCommentsPage < 1) {
            return ''; // do not show the pagination
        }

        // render the comment markup depending on the CSS framework set in the configuration
        $frameWork = FieldtypeFrontendComments::getFrameWork();
        $className = 'FrontendComments\\FrontendCommentPagination' . $frameWork;

        if (class_exists($className)) {
            $class = new $className($this->comments);
        } else {
            $class = $this;
        }

        // overwrite the pagination number if set on per field base
        if ($this->field->get('input_fc_pagnumber') > 0) {
            $this->numCommentsPage = $this->field->get('input_fc_pagnumber');
        }

        return $class->renderPaginationMarkup();
    }
}
