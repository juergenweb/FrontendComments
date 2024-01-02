<?php
    declare(strict_types=1);

    namespace FrontendComments;

    /**
     * Class to render the comment with UiKit 3 markup
     */
    class Uikit3Comment extends Comment
    {

        public function __construct(array $comment, CommentArray $comments)
        {
            parent::__construct($comment, $comments);

            // Avatar image
            if (!is_null($this->userImage)) {
                $this->avatar->removeAttribute('class');
                $this->avatar->setAttribute('class', 'uk-comment-avatar');
                $this->avatar->setAttribute('width', '80');
                $this->avatar->setAttribute('height', '80');
                $this->frontendCommentsConfig['input_fc_imagesize'] = 80;
                $this->avatar->wrap()->setAttribute('class', 'uk-width-auto');
            }

            // Author name
            $this->commentAuthor->setTag('h4');
            $this->commentAuthor->removeAttribute('class');
            $this->commentAuthor->setAttribute('class', 'uk-comment-title uk-margin-remove');

            // Comment created
            $this->commentCreated->wrap()->setTag('li');
            $this->commentCreated->setAttribute('class', 'uk-text-small uk-text-muted');

            // Reply Link
            $this->replyLink->wrap()->setTag('li');

            // Up-votes
            $this->upvote->removePrepend();
            $this->upvote->removeAppend();
            $this->upvote->prepend('<li>')->append('<span id="' . $this->field->name . '-' . $this->id . '-votebadge-up" class="uk-badge uk-margin-small-left">' . $this->upvotes . '</span></li>');

            // Down-votes
            $this->downvote->removePrepend();
            $this->downvote->removeAppend();
            $this->downvote->prepend('<li>')->append('<span id="' . $this->field->name . '-' . $this->id . '-votebadge-down" class="uk-badge uk-margin-small-left">' . $this->downvotes . '</span></li>');

            $this->replayFormHeadline->setAttribute('class', 'uk-margin-medium-top');

            $this->commentCreated->setTag('span');

        }

        /**
         * Render method for the comment - outputs Uikit 3 comment markup
         * @param bool $levelStatus
         * @return string
         */
        public function ___renderCommentMarkup(bool $levelStatus): string
        {
            return '<header class="uk-comment-header uk-position-relative">
                        <div class="uk-grid-medium uk-flex-middle" data-uk-grid>'
                . $this->___renderImage() .
                '<div class="uk-width-expand">'
                . $this->___renderAuthor()
                . $this->___renderCreated() .
                '</div>
                             <div class="uk-position-top-right uk-position-small">'
                . $this->___renderReply($levelStatus) .
                '</div> 
                                     <div class="uk-width-1-1 uk-margin-small-top"><ul class="uk-comment-meta uk-subnav uk-subnav-divider uk-margin-remove-top">'
                . $this->___renderRating()
                . $this->___renderVotes() .
                '</ul></div>                   
                        </div>                      
                    </header>
                    <div class="uk-comment-body">'
                . $this->___renderText() .
                '</div>';
        }

    }
