<?php
    declare(strict_types=1);
    namespace FrontendComments;

    /*
    * Class for rendering the pagination in Bootstrap 5 markup
    *
    * Created by Jürgen K.
    * https://github.com/juergenweb
    * File name: FrontendCommentPaginationBootstrap5.php
    * Created: 13.05.2025
    *
    */

    class FrontendCommentPaginationBootstrap5 extends FrontendCommentPagination
    {

        public function __construct(FrontendCommentArray $comments)
        {

            parent::__construct($comments);

            /** Manipulate the markup to be Bootstrap 5 */

            // Nav wrapper
            $this->getPaginationWrapper()->setTag('nav')->removeAttribute('class')->setAttribute('class', 'pagination-wrapper')->setAttribute('aria-label', $this->_('Page navigation'));

            // add the alignment class
            $alignClasses = [
                'left' => '',
                'center' => 'justify-content-center',
                'right' => 'justify-content-end'
            ];



            $this->getPaginationList()->setAttribute('class', $alignClasses[$this->alignment]);

            // nav page list item
            $this->navPageLi->removeAttribute('class')->setAttribute('class', 'page-item');

            // nav page link
            $this->navPageLink->setAttribute('class', 'page-link');

            // Start page
            $this->getStartPageListitem()->removeAttribute('class');

            // Dots
            $this->getDotsListitem()->removeAttribute('class')->setAttribute('class', 'uk-disabled');

            // Current page
            $this->getCurrentPageListitem()->removeAttribute('class')->setAttribute('class', 'page-item active')->setAttribute('aria-current', 'page');
            $this->currentPageLink->setTag('span')->setAttribute('class', 'page-link');

            // End page
            $this->getEndPageListitem()->removeAttribute('class');

            $this->pageOfText->setAttribute('class', 'mb-3');

        }

    }
