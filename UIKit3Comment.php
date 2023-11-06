<?php

    namespace FrontendComments;

    use FrontendComments\Comment;

    class UIKit3Comment extends Comment
    {

        public function __construct(array $comment, CommentArray $comments)
        {
            parent::__construct($comment, $comments);
        }

        public function ___renderCommentMarkup(bool $levelStatus): string
        {
            $out = '<div class="test">Uikit 3</div>';
            return $out;
        }


    }