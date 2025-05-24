<?php
    declare(strict_types=1);
    namespace FrontendComments;

/*
 * Class to create the comment list using Uikit3 markup
 *
 * Created by Jürgen K.
 * https://github.com/juergenweb
 * File name: FrontendCommentsUikit3.php
 * Created: 26.12.2025
 */

class FrontendCommentsUikit3 extends FrontendComments
{

    /**
     * @param \FrontendComments\FrontendCommentArray $comments
     * @throws \ProcessWire\WireException
     * @throws \ProcessWire\WirePermissionException
     */
    public function __construct(FrontendCommentArray $comments)
    {
        parent::__construct($comments);
        $this->ulClass = 'uk-comment-list';
        $this->replyUlClass = '';
    }

}