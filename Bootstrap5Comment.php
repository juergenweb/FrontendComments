<?php

    namespace FrontendComments;

    use FrontendComments\Comment;

    class Bootstrap5Comment extends Comment
    {

        public function __construct(array $comment, CommentArray $comments)
        {
            parent::__construct($comment, $comments);
        }

        public function ___renderCommentMarkup(bool $levelStatus): string
        {
            $out = '<div class="test">Bootstrap 5</div>';
            return $out;
        }



    }