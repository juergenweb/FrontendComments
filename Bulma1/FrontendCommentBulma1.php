<?php
declare(strict_types=1);

namespace FrontendComments;

/*
    * Class to create and render a single comment using Bulma 1 markup
    *
    * Created by Jürgen K.
    * https://github.com/juergenweb
    * File name: FrontendCommentBulma1.php
    * Created: 12.03.2026
*/

class FrontendCommentBulma1 extends FrontendComment
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
            $this->avatar->prepend('<div class="media-left"><div class="image is-64x64 p-1 has-background-grey-light">');
            $this->avatar->append('</div></div>');
            $this->avatar->removeAttributeValue('class', 'avatar');
        }

        // Author name
        $this->commentAuthor->setTag('strong');
        $this->commentAuthor->removeAttribute('class');
        $this->commentAuthor->setAttribute('class', 'mr-1');

        // Creation date
        $this->commentCreated->setTag('small');
        $this->commentCreated->removeAttribute('class');
        $this->commentCreated->setAttribute('class', 'mr-1');

        // Upvote link
        $this->upvote->removeAttributeValue('class', 'fc-upvote');
        $this->upvote->setLinkText('<span id="' . $this->field->name . '-' . $this->get('id') . '-votebadge-up" class="fc-votebadge level-item tag is-success is-hoverable">↑ ' . $this->get('upvotes') . '</span>');
        $this->upvote->removeAppend();
        $this->upvote->wrap()->setAttribute('class', 'level-item mr-1 ');

        // Downvote link
        $this->downvote->removeAttributeValue('class', 'fc-downvote');
        $this->downvote->setLinkText('<span id="' . $this->field->name . '-' . $this->get('id') . '-votebadge-down" class="fc-votebadge level-item tag is-danger is-hoverable">↓ ' . $this->get('downvotes') . '</span>');
        $this->downvote->removePrepend();
        $this->downvote->wrap()->setAttribute('class', 'level-item mr-1 ');

        // Reply link
        $this->replyLink->wrap()->setAttribute('class', 'level-item');

        // Comment text
        $this->commentText->removeAttribute('class')->removeWrap();

        // Feedback text
        $this->feedbackText->setAttribute('class', 'notification mt-3 mb-3');

        // Website link
        $this->websiteLink->getWrap()->removeAttribute('class');
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

        $out = '<div class="box"><article class="media">
      ' . $this->renderCommentAvatar() . '
  <div class="media-content">
    <div class="content">
      <div class="pb-3">
        <div class="b-head">
            ' . $this->renderCommentAuthor()
            . $this->renderCommentCreated()
            . $this->renderWebsiteLink() . '
        </div>
        <div class="fcm-comment-box mb-2">' . $this->renderRating() . '</div>
        <div>' .
            $this->renderCommentText() .
            $this->renderFeedbackText() .
            $this->renderNoVoteAlertbox() . '
      </div>
    </div>
    <nav class="level">
      <div class="level-left">
      <span class="level-item">' . $this->renderVotes() . '</span>
      </div>
      <div class="level-right">
      ' . $this->renderReplyLink($level) . '
      </div>
    </nav>
  </div>
</article>
' . $this->renderReplyForm() . '
</div>';

        return $out;
    }

}
