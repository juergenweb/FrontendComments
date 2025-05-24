<?php
    declare(strict_types=1);
    namespace FrontendComments;

    /*
         * Class to create and render pagination using Pico2 CSS framework markup
         *
         * Created by Jürgen K.
         * https://github.com/juergenweb
         * File name: FrontendCommentPaginationPico2.php
         * Created: 21.05.2025
         */

    class FrontendCommentPaginationPico2 extends FrontendCommentPagination
    {

        public function __construct(FrontendCommentArray $comments)
        {
            parent::__construct($comments);
            $this->paginationWrapper->setTag('div');
        }

    }