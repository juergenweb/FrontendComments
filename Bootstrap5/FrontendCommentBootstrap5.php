<?php
    declare(strict_types=1);
    namespace FrontendComments;

/*
    * Class to create and render a single comment using Bootstrap 5 markup
    *
    * Created by Jürgen K.
    * https://github.com/juergenweb
    * File name: FrontendCommentBootstrap5.php
    * Created: 12.05.2025
*/

class FrontendCommentBootstrap5 extends FrontendComment
{

    /**
     * @param \FrontendComments\FrontendCommentArray $comments
     * @param array $comment
     * @param array $frontendFormsConfig
     * @throws \ProcessWire\WireException
     */
    public function __construct(FrontendCommentArray $comments, array $comment, array $frontendFormsConfig)
    {
        $this->imagesize = 60; // set the image size for the user image
        parent::__construct($comments, $comment, $frontendFormsConfig);

        // User image
        if (!is_null($this->avatar)) {
            $this->avatar->removeWrap();
            $this->avatar->setAttribute('class', 'rounded-circle shadow-1-strong me-3');
            $this->avatar->removeAttributeValue('class', 'avatar');
        }

        // Author name
        $this->commentAuthor->setTag('h6');
        $this->commentAuthor->removeAttribute('class');
        $this->commentAuthor->setAttribute('class', 'fw-bold text-primary mb-1');

        // Creation date
        $this->commentCreated->setTag('p');
        $this->commentCreated->removeAttribute('class');
        $this->commentCreated->setAttribute('class', 'text-muted small mb-0');

        // Upvote link
        $this->upvote->removeAttributeValue('class', 'fc-upvote');
        $this->upvote->setLinkText('<span id="' . $this->field->name . '-' . $this->get('id') . '-votebadge-up" class="fc-votebadge badge bg-success">↑ ' . $this->get('upvotes') . '</span>');
        $this->upvote->removeAppend();
        $this->upvote->wrap()->setAttribute('class', 'd-flex align-items-center me-3');

        // Downvote link
        $this->downvote->removeAttributeValue('class', 'fc-downvote');
        $this->downvote->setLinkText('<span id="' . $this->field->name . '-' . $this->get('id') . '-votebadge-down" class="fc-votebadge badge bg-danger ">↓ ' . $this->get('downvotes') . '</span>');
        $this->downvote->removePrepend();
        $this->downvote->wrap()->setAttribute('class', 'd-flex align-items-center me-3');

        // Reply link
        $this->replyLink->wrap()->setAttribute('class', 'd-flex align-items-center me-3');

        // Comment text
        $this->commentText->removeAttribute('class')->removeWrap();

        // Feedback text
        $this->feedbackText->setAttribute('class', 'bg-light border rounded fs-6 text fst-italic p-2 mt-2');

        // Website link
        $this->websiteLink->getWrap()->removeAttribute('class')->setAttribute('class', 'small text-muted');
        $this->websiteLink->removeAttribute('class');

    }

    /** Render a single comment using UIKit3 markup
     * @param string $levelnumber
     * @param int $level
     * @return string
     * @throws \ProcessWire\WireException
     */
    public function ___renderComment(string $levelnumber, int $level = 0): string
    {
        $out = '<div class="card">'; //card start
        $out .= '<div class="card-body">';//card body start

        $out .= '<div class="d-flex flex-start align-items-center">';
        $out .= $this->renderCommentAvatar();// Avatar
        $out .= '<div>';
        $out .= $this->renderCommentAuthor();
        $out .= $this->renderCommentCreated();
        $out .= '<div class="fcm-comment-box">' . $this->renderRating() . '</div>';
        $out .= $this->renderWebsiteLink();
        $out .= '</div>';
        $out .= '</div>';

        // comment text
        $out .= '<div class="mt-3 mb-4 pb-2">';
        $out .= $this->renderCommentText();
        $out .= $this->renderFeedbackText();
        $out .= '</div>';

        $out .= $this->renderNoVoteAlertbox();
        // votes and reply link
        $out .= '<div class="small d-flex justify-content-end">';
        $out .= $this->renderVotes();
        $out .= $this->renderReplyLink($level);
        $out .= '</div>';
        $out .= $this->renderReplyForm();
        $out .= '</div>'; //card body end
        $out .= '</div>';//card end

        return $out;
    }

}
