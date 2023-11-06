<?php

    namespace FrontendComments;

    use FrontendComments\Comment;

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
            $this->avatar->prepend('<div class="uk-width-auto">')->append('</div>');

            // Author name
            $this->commentAuthor->setTag('h4');
            $this->commentAuthor->removeAttribute('class');
            $this->commentAuthor->setAttribute('class', 'uk-comment-title uk-margin-remove');

            // Comment created
            $this->commentCreated->prepend('<li>')->append('</li>');

            // Reply Link
            $this->replyLink->setLinkText($this->_('Reply'));
            $this->replyLink->prepend('<li>')->append('</li>');

            // Up and down votes
        }

        public function ___renderCommentMarkup(bool $levelStatus): string
        {
            $out = '<article class="uk-comment uk-comment-primary" role="comment">
                    <header class="uk-comment-header">
                        <div class="uk-grid-medium uk-flex-middle" data-uk-grid>'
                            .$this->___renderImage().
                            '<div class="uk-width-expand">'
                                .$this->___renderAuthor().
                                '<ul class="uk-comment-meta uk-subnav uk-subnav-divider uk-margin-remove-top">'
                                    .$this->___renderCreated().
                                    '<li><a href="#">Reply</a></li>
                                </ul>
                            </div>
                        </div>
                    </header>
                    <div class="uk-comment-body">'
                        .$this->___renderText().
                    '</div>
                </article>';
            return $out;
        }


    }
