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

    use ProcessWire\FieldtypeFrontendComments;

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

        // markup of a single comment lives in frameworks/Pico2/templates/comment.php - the file MUST be
        // placed there under exactly that name (not e.g. "Pico2-comment-template.php"), see FieldtypeFrontendComments::resolveTemplateFile()
        $this->commentTemplateFile = FieldtypeFrontendComments::resolveTemplateFile(
            'comment.php',
            __DIR__ . '/templates/comment.php'
        );

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

    /**
     * Render a single comment using Pico 2 markup.
     * The actual HTML lives in templates/comment.php - this method only supplies the pre-rendered
     * building blocks (avatar, author, votes, ...) as template variables.
     * @param string $levelnumber
     * @param int $level
     * @return string
     * @throws \ProcessWire\WireException
     */
    public function ___renderComment(string $levelnumber, int $level = 0): string
    {
        return $this->renderCommentTemplate([
            'levelnumber' => $levelnumber,
            'level' => $level,
            'avatar' => $this->renderCommentAvatar(),
            'author' => $this->renderCommentAuthor(),
            'created' => $this->renderCommentCreated(),
            'rating' => $this->renderRating(),
            'websiteLink' => $this->renderWebsiteLink(),
            'commentText' => $this->renderCommentText(),
            'feedbackText' => $this->renderFeedbackText(),
            'noVoteAlert' => $this->renderNoVoteAlertbox(),
            'votes' => $this->renderVotes(),
            'replyLink' => $this->renderReplyLink($level),
            'replyForm' => $this->renderReplyForm(),
        ]);
    }
}
