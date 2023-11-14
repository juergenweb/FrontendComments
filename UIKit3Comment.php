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
            $this->avatar->removeAttribute('class');
            $this->avatar->setAttribute('class', 'uk-comment-avatar');
            $this->avatar->setAttribute('width', '80');
            $this->avatar->setAttribute('height', '80');
            $this->frontendCommentsConfig['input_fc_imagesize'] = 80;
            $this->avatar->wrap()->setAttribute('class','uk-width-auto');

            // Author name
            $this->commentAuthor->setTag('h4');
            $this->commentAuthor->removeAttribute('class');
            $this->commentAuthor->setAttribute('class', 'uk-comment-title uk-margin-remove');

            // Comment created
            $this->commentCreated->wrap()->setTag('li');

            // Reply Link
            $this->replyLink->wrap()->setTag('li');

            // Up-votes
            $this->upvote->removePrepend();
            $this->upvote->removeAppend();
            $this->upvote->prepend('<li>')->append('<span id="' . $this->field->name . '-' . $this->id . '-votebadge-up" class="uk-badge uk-margin-small-right">' . $this->upvotes . '</span></li>');

            // Down-votes
            $this->downvote->removePrepend();
            $this->downvote->removeAppend();
            $this->downvote->prepend('<li>')->append('<span id="' . $this->field->name . '-' . $this->id . '-votebadge-down" class="uk-badge uk-margin-small-right">' . $this->downvotes . '</span></li>');

        }

        /**
         * Render method for the comment - outputs Uikit 3 comment markup
         * @param bool $levelStatus
         * @return string
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        public function ___renderCommentMarkup(bool $levelStatus): string
        {
            return '<header class="uk-comment-header">
                        <div class="uk-grid-medium uk-flex-middle" data-uk-grid>'
                            .$this->___renderImage().
                            '<div class="uk-width-expand">'
                                .$this->___renderAuthor().
                                '<ul class="uk-comment-meta uk-subnav uk-subnav-divider uk-margin-remove-top">'
                                    .$this->___renderCreated()
                                    .$this->___renderReply($levelStatus)
                                    .$this->___renderVotes().
                                    '<li>'.$this->___renderRating().'</li>
                                </ul>
                            </div>
                        </div>
                    </header>
                    <div class="uk-comment-body">'
                        .$this->___renderText().
                    '</div>';
        }

    }
