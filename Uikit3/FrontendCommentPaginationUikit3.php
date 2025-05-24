<?php
    declare(strict_types=1);
    namespace FrontendComments;

    /*
    * Class for rendering pagination in UiKit 3 markup
    *
    * Created by Jürgen K.
    * https://github.com/juergenweb
    * File name: Uikit3Pagination.php
    * Created: 14.03.2025
    *
    */

    use FrontendForms\TextElements;

    class FrontendCommentPaginationUikit3 extends FrontendCommentPagination
    {

        public function __construct(FrontendCommentArray $comments)
        {

            parent::__construct($comments);

            /** Manipulate the markup to be UIKit3 */

            // Nav wrapper
            $this->getPaginationWrapper()->removeAttribute('class')->setAttribute('class', 'pagination-wrapper');

            // Ul
            $this->getPaginationList()->setAttribute('class', 'uk-pagination')->removeAttributeValue('class', 'pagination')->setAttribute('data-uk-margin');

            // add the alignment class
            $alignClasses = [
                'left' => '',
                'center' => 'uk-flex-center',
                'right' => 'uk-flex-right'
            ];
            $this->getPaginationList()->setAttribute('class', $alignClasses[$this->alignment]);

            // Start page
            $this->getStartPageListitem()->removeAttribute('class');

            // Dots
            $this->getDotsListitem()->removeAttribute('class')->setAttribute('class', 'uk-disabled');

            // Current page
            $this->getCurrentPageListitem()->removeAttribute('class')->setAttribute('class', 'uk-active');
            $this->currentPageLink = new TextElements();
            $this->currentPageLink->setTag('span')->setContent((string)$this->currentPage);

            // Current page
            $this->getEndPageListitem()->removeAttribute('class');

        }

    }