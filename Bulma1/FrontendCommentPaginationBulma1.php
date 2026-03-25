<?php
    declare(strict_types=1);
    namespace FrontendComments;

    /*
    * Class for rendering the pagination in Bulma 1 markup
    *
    * Created by Jürgen K.
    * https://github.com/juergenweb
    * File name: FrontendCommentPaginationBulma1.php
    * Created: 13.03.2026
    *
    */

    class FrontendCommentPaginationBulma1 extends FrontendCommentPagination
    {

        public function __construct(FrontendCommentArray $comments)
        {

            parent::__construct($comments);

            /** Manipulate the markup to be Bulma 1 */

            // Nav wrapper
            $this->getPaginationWrapper()->setTag('nav')->removeAttribute('class')->setAttribute('class', 'pagination')->setAttribute('aria-label', $this->_('Page navigation'))->setAttribute('role', 'navigation');

            // add the alignment class
            $alignClasses = [
                'left' => '',
                'center' => 'is-centered',
                'right' => 'is-right'
            ];

            $this->getPaginationWrapper()->setAttribute('class', $alignClasses[$this->alignment])->setAttribute('class', 'mb-3');

            $this->getPaginationList()->setAttribute('class', 'pagination-list');

            // nav page list item
            $this->navPageLi->removeAttribute('class');

            // nav page link
            $this->navPageLink->setAttribute('class', 'pagination-link');

            // Start page
            $this->getStartPageListitem()->removeAttribute('class');
            $this->startPageLink->setAttribute('class', 'pagination-link');

            // Dots
            $this->getDotsListitem()->removeAttribute('class')->setAttribute('class', 'pagination-ellipsis');

            // Current page
            $this->getCurrentPageListitem()->removeAttribute('class');
            $this->currentPageLink->setTag('span')->setAttribute('class', 'pagination-link is-current')->setAttribute('aria-current', 'page');

            // End page
            $this->getEndPageListitem()->removeAttribute('class');
            $this->endPageLink->setAttribute('class', 'pagination-link');


            $this->pageOfText->setAttribute('class', 'mb-3');

        }

    }
