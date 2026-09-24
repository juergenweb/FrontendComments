<?php

namespace FrontendComments;

/*
 * Class to create and render a single comment using Uikit 3 markup
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: FrontendCommentUikit3.php
 * Created: 26.12.2024
 */

use ProcessWire\FieldtypeFrontendComments;

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

        // markup of a single comment lives in frameworks/Uikit3/templates/comment.php - the file MUST be
        // placed there under exactly that name (not e.g. "Uikit3-comment-template.php"), see FieldtypeFrontendComments::resolveTemplateFile()
        $this->commentTemplateFile = FieldtypeFrontendComments::resolveTemplateFile(
            'comment.php',
            __DIR__ . '/templates/comment.php'
        );

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

        // Downvote link
        $this->downvote->removePrepend();
        $this->downvote->setAttribute('class', 'uk-link-reset');
        $linktext = str_replace('class="', 'class="uk-badge uk-downvote ', $this->downvote->getLinkText());
        $this->downvote->setLinkText($linktext);
        $this->downvote->prepend('<li><div>');

        // Upvote link
        $this->upvote->removeAppend();
        $this->upvote->setAttribute('class', 'uk-link-reset');
        $linktext = str_replace('class="', 'class="uk-badge uk-upvote ', $this->upvote->getLinkText());
        $this->upvote->setLinkText($linktext);
        $this->upvote->append('</div></li>');

        // Reply link
        $this->replyLink->wrap()->setTag('li')->removeAttribute('class');

        // Comment text
        $this->commentText->removeAttributeValue('class', 'fc-comment-text');
        $this->commentText->setAttribute('class', 'uk-comment-body');

        // Feedback text
        $this->feedbackText->setAttribute('class', 'uk-text-italic uk-background-default uk-padding-small uk-margin-top uk-border-rounded');

        // Website link
        $this->websiteLink->getWrap()->removeAttribute('class')->setAttribute('class', 'uk-margin-small-top uk-text-right@s uk-text-small uk-text-muted');
        $this->websiteLink->removeAttribute('class')->
        setAttribute('class', 'uk-link-text');
    }


    /**
     * Render a single comment using Uikit 3 markup.
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
            'noVoteAlert' => $this->renderNoVoteAlertbox(),
            'avatar' => $this->renderCommentAvatar(),
            'author' => $this->renderCommentAuthor(),
            'rating' => $this->renderRating(),
            'created' => $this->renderCommentCreated(),
            'votes' => $this->renderVotes(),
            'replyLink' => $this->renderReplyLink($level),
            'commentText' => $this->renderCommentText(),
            'feedbackText' => $this->renderFeedbackText(),
            'websiteLink' => $this->renderWebsiteLink(),
            'replyForm' => $this->renderReplyForm(),
        ]);
    }
}
