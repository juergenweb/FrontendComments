<?php
    declare(strict_types=1);
    namespace FrontendComments;

    /*
     * Class to create and render a single comment including the reply form using Pico2 CSS framework markup
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: FrontendCommentPico2.php
     * Created: 21.05.2025
     */

    class FrontendCommentPico2 extends FrontendComment
    {

        /**
         * @param \FrontendComments\FrontendCommentArray $comments
         * @param array $comment
         * @param array $frontendFormsConfig
         * @throws \ProcessWire\WireException
         */
        public function __construct(FrontendCommentArray $comments, array $comment, array $frontendFormsConfig)
        {
            $this->imagesize = 70; // set the image size for the user image
            parent::__construct($comments, $comment, $frontendFormsConfig);

            // User image
            if (!is_null($this->avatar)) {
                $this->avatar->wrap()->setAttribute('class', 'fcm-avatar');
                $this->avatar->setAttribute('class', 'rounded-circle shadow-1-strong me-3');
                $this->avatar->removeAttributeValue('class', 'avatar');
            }

            // Author name
            $this->commentAuthor->setTag('p');
            $this->commentAuthor->removeAttribute('class');
            $this->commentAuthor->setAttribute('class', 'comment-author');

            // Creation date
            $this->commentCreated->setTag('p');
            $this->commentCreated->removeAttribute('class');
            $this->commentCreated->setAttribute('class', 'creation-date');

            // Upvote link
            $this->upvote->setAttribute('class', 'd-flex align-items-center me-3');

            // Downvote link
            $this->downvote->setAttribute('class', 'd-flex align-items-center me-3');

            // Reply link
            $this->replyLink->setAttribute('class', 'd-flex align-items-center me-3');

            // Comment text
            $this->commentText->removeAttribute('class')->removeWrap();

            // Feedback text
            $this->feedbackText->setTag('article');

            // Website link
            $this->websiteLink->getWrap()->removeAttribute('class')->setAttribute('class', 'website-link');
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
            $out = '<article class="pico-comment">'; //card start

            $out .= '<header>';//card body start
            $out .= $this->renderCommentAvatar();// Avatar
            $out .= '<div class="meta">';
            $out .= $this->renderCommentAuthor();
            $out .= $this->renderCommentCreated();

            $out .= '<div class="fcm-comment-box">' . $this->renderRating() . '</div>';
            $out .= $this->renderWebsiteLink();

            $out .= '</div>';
            $out .= '</header>';

            // comment text
            $out .= '<p class="comment-text">';
            $out .= $this->renderCommentText();
            $out .= $this->renderFeedbackText();
            $out .= '</p>';

            // votes and reply link
            $out .= '<footer>';
            $out .= $this->renderNoVoteAlertbox();
            $out .= $this->renderVotes();
            $out .= $this->renderReplyLink($level);
            $out .= $this->renderReplyForm();
            $out .= '</footer>'; //card body end
            $out .= '</article>';//card end

            return $out;
        }

    }
