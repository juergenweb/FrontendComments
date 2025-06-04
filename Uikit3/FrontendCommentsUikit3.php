<?php namespace FrontendComments;

/*
    * Class to create and render a single comment using Uikit 3 markup
    *
    * Created by Jürgen K.
    * https://github.com/juergenweb
    * File name: FrontendCommentUikit3.php
    * Created: 26.12.2024
*/

class FrontendCommentUikit3 extends FrontendComment
{

    /**
     * @param \FrontendComments\FrontendCommentArray $comments
     * @param array $comment
     * @param array $frontendFormsConfig
     * @throws \ProcessWire\WireException
     */
    public function __construct(FrontendCommentArray $comments, array $comment, array $frontendFormsConfig)
    {
        $this->imagesize = 50; // set the image size for the user image
        parent::__construct($comments, $comment, $frontendFormsConfig);

        $this->createCommentText();

        // User image
        if (!is_null($this->userImage)) {
            $this->avatar->setAttribute('class', 'uk-comment-avatar');
            $this->avatar->removeAttributeValue('class', 'avatar');
            $this->avatar->wrap()->setAttribute('class', 'uk-width-auto');
        }

        // Author name
        $this->commentAuthor->setTag('h4');
        $this->commentAuthor->removeAttribute('class');
        $this->commentAuthor->setAttribute('class', 'uk-comment-title uk-margin-remove');

        // Creation date
        $this->commentCreated->setTag('li');
        $this->commentCreated->removeAttribute('class');

        // Upvote link
        $this->upvote->setAttribute('class', 'uk-link-reset');
        $this->upvote->wrap()->setTag('span')->setAttribute('class', 'uk-badge uk-margin-small-left uk-alert-success');
        $this->upvote->getWrap()->append('</li>');

        // Downvote link
        $this->downvote->setAttribute('class', 'uk-link-reset');
        $this->downvote->wrap()->setTag('span')->setAttribute('class', 'uk-badge');
        $this->downvote->getWrap()->prepend('<li>');

        // Reply link
        $this->replyLink->wrap()->setTag('li')->removeAttribute('class');

        // Comment text
        $this->commentText->removeAttributeValue('class', 'fc-comment-text');
        $this->commentText->setAttribute('class', 'uk-comment-body');

        // Feedback text
        $this->feedbackText->setAttribute('class', 'uk-text-italic uk-background-default uk-padding-small uk-margin-top uk-border-rounded');

        // Website link
        $this->websiteLink->getWrap()->removeAttribute('class')->setAttribute('class', 'uk-text-right uk-text-small uk-text-muted');
        $this->websiteLink->removeAttribute('class')->
        setAttribute('class', 'uk-link-text');

    }


    /** Render a single comment using UIKit3 markup
     * @param string $levelnumber
     * @param int $level
     * @return string
     * @throws \ProcessWire\WireException
     */
    public function ___renderComment(string $levelnumber, int $level = 0): string
    {

        $out = $this->renderNoVoteAlertbox();
        $out .= '<article class="uk-comment uk-comment-primary uk-visible-toggle" tabindex="-1" role="comment">';
        $out .= '<header class="uk-comment-header uk-position-relative">';
        $out .= '<div class="uk-grid-medium uk-flex-middle" data-uk-grid>';
            $out .= $this->renderCommentAvatar();
            $out .= '<div class="uk-width-expand">';
            $out .= $this->renderCommentAuthor();
            $out .= '<ul class="uk-comment-meta uk-subnav uk-subnav-divider uk-margin-remove-top">';
            $out .= '<li class="comment-box">'.$this->renderRating().'</li>';
            $out .= $this->renderCommentCreated();
            $out .= $this->renderVotes();
            $out .= $this->renderReplyLink($level);
            $out .= '</div>';
        $out .= '</div>';
        $out .= '</header>';
        $out .= $this->renderCommentText();
        $out .= $this->renderFeedbackText();
        $out .= $this->renderWebsiteLink();
        $out .= $this->renderReplyForm();
        $out .= '</article>';

        return $out;
    }


}
