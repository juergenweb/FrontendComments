<?php
    declare(strict_types=1);

    namespace FrontendComments;

    /*
         * Class to create and render a single comment including the reply form
         *
         * Created by Jürgen K.
         * https://github.com/juergenweb
         * File name: FrontendComment.php
         * Created: 24.12.2024
         */

    use FrontendForms\Button;
    use FrontendForms\Form;
    use FrontendForms\InputHidden;
    use FrontendForms\TextElements;
    use FrontendForms\Link;
    use FrontendForms\Image;
    use PDO;
    use ProcessWire\Page;
    use ProcessWire\Field;
    use ProcessWire\WireArray;
    use ProcessWire\WireData;
    use ProcessWire\PageImage;

    class FrontendComment extends WireData
    {

        const flagNotifyNone = 0; // Flag to indicate that the author of this comment does not want to be notified of replies
        const flagNotifyReply = 1; //Flag to indicate the author of this comment wants to be notified of replies to their comment
        const flagNotifyAll = 2; // Flag to indicate the author of this comment wants to be notified of all comments on the page

        protected Page $page; // the page object the comment lives on
        protected Field $field; // the field object the comment is part of
        protected array $frontendFormsConfig = []; // array containing all FrontendForms config values
        protected WireArray $comments;
        // default values
        protected int $imagesize = 80; // default height and width of the avatar image

        // Comment objects
        protected TextElements $commentAuthor;
        protected Link $upvote;
        protected Link $downvote;
        protected Image|null $avatar;
        protected TextElements $commentCreated;
        protected TextElements $commentText;
        protected TextElements $feedbackText;
        protected Link $replyLink;
        protected Link $websiteLink;

        /**
         * Create a new comment object
         * The construction contains as parameters the FrontendCommentsArray with all comments and the properties
         * the comment itself as an array
         * @param \FrontendComments\FrontendCommentArray $comments
         * @param array $comment
         * @param array $frontendFormsConfig
         * @throws \ProcessWire\WireException
         */
        public function __construct(FrontendCommentArray $comments, array $comment, array $frontendFormsConfig)
        {

            parent::__construct();

            $this->field = $comments->getField(); // Processwire comment field object
            $this->page = $comments->getPage(); // the current page object, which contains the comment field
            $this->comments = $comments;
            $this->frontendFormsConfig = $frontendFormsConfig;

            foreach ($comment as $name => $value) {
                if ($name === 'data') $name = 'text';
                // add "Guest" as the name if no name is entered
                if ($name === 'author') {
                    $value = ($value == '') ? $this->_('Guest') : $value;
                }
                $this->set($name, $value);
            }

            // Create all comment objects
            $this->createWebsiteLink();
            $this->createCommentAuthor();
            $this->createUpVoteElement();
            $this->createDownVoteElement();
            $this->createCommentAvatar();
            $this->createCommentCreated();
            $this->createCommentText();
            $this->createCommentFeedback();
            $this->createReplyLink();

        }

        /**
         * Get the creation date of the comment depending on the settings
         * @param int|string $date
         * @param int|null $format
         * @return \FrontendComments\FrontendComment
         * @throws \ProcessWire\WireException
         */
        protected function getFormattedCommentCreationDate(int|string $date, null|int $format): string
        {

            if ($format == 0) {
                $dateString = $this->wire('datetime')->date($this->frontendFormsConfig['input_dateformat'], $date);
                $timeString = $this->wire('datetime')->date($this->frontendFormsConfig['input_timeformat'], $date);
                $date = $dateString . ' ' . $timeString;
            } else {
                $date = $this->wire('datetime')->relativeTimeStr($date);
            }
            return $date;
        }

        /**
         * Get the user image object if present as PageImage
         * @return \ProcessWire\PageImage|null
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function getUserImage(): PageImage|null
        {

            $userimageField = $this->field->get('input_fc_userimage');

            if ($userimageField !== 'none') {
                $imageFieldName = $this->wire('fields')->get($userimageField)->name;
                // get the user which has written this comment
                $user = $this->wire('users')->get($this->get('user_id'));

                // check if the user image field exists for this user
                if (isset($user->$imageFieldName->name)) {
                    return $user->$imageFieldName;
                }
            }
            return null;
        }

        /**
         * Get the upvote text object
         * @return \FrontendForms\Link
         * @throws \ProcessWire\WireException
         */
        protected function createUpVoteElement(): Link
        {
            $this->upvote = $this->wire(new Link());
            $this->upvote->setUrl($this->page->url . '?vote=up&votecommentid=' . $this->get('id'));
            $this->upvote->setAttribute('class', 'fc-vote-link fc-upvote');
            $this->upvote->setAttribute('title', $this->_('Like the comment'));
            $this->upvote->setAttribute('data-field', $this->field->name);
            $this->upvote->setAttribute('data-commentid', $this->get('id'));
            //$this->upvote->setLinkText($this->_('Like'));
            //$this->upvote->wrap()->setTag('span')->setAttribute('id', $this->field->name . '-' . $this->get('id') . '-votebadge-up-wrapper')->setAttribute('class', 'badge-upvote');
            //$this->upvote->append('<span id="' . $this->field->name . '-' . $this->get('id') . '-votebadge-up" class="fc-votebadge upvote">' . $this->get('upvotes') . '</span>');
            $this->upvote->setLinkText('<span id="' . $this->field->name . '-' . $this->get('id') . '-votebadge-up" class="fc-votebadge upvote">↑ ' . $this->get('upvotes') . '</span>');
            return $this->upvote;
        }

        /**
         * Get the upvote element
         * @return \FrontendForms\Link
         */
        public function ___getUpVoteElement(): Link
        {
            return $this->upvote;
        }

        /**
         * Get the downvote text object
         * @return \FrontendForms\Link
         * @throws \ProcessWire\WireException
         */
        protected function createDownVoteElement(): Link
        {
            // Down-vote link
            $this->downvote = $this->wire(new Link());
            $this->downvote->setUrl($this->page->url . '?vote=down&votecommentid=' . $this->get('id'));
            $this->downvote->setAttribute('class', 'fc-vote-link fc-downvote');
            $this->downvote->setAttribute('title', $this->_('Dislike the comment'));
            $this->downvote->setAttribute('data-field', $this->field->name);
            $this->downvote->setAttribute('data-commentid', $this->get('id'));
            //$this->downvote->setLinkText($this->_('Dislike'));
            //$this->downvote->wrap()->setTag('span')->setAttribute('id', $this->field->name . '-' . $this->get('id') . '-votebadge-down-wrapper')->setAttribute('class', 'badge-downvote');
            //$this->downvote->append('<span id="' . $this->field->name . '-' . $this->get('id') . '-votebadge-down" class="fc-votebadge downvote">' . $this->get('downvotes') . '</span>');
            $this->downvote->setLinkText('<span id="' . $this->field->name . '-' . $this->get('id') . '-votebadge-down" class="fc-votebadge downvote">↓ ' . $this->get('downvotes') . '</span>');
            return $this->downvote;
        }

        /**
         * Get the downvote element
         * @return \FrontendForms\Link
         */
        public function ___getDownVoteElement(): Link
        {
            return $this->downvote;
        }

        /**
         * Get the reply comments of the comment
         * @return \FrontendComments\FrontendCommentArray
         */
        public function getReplies(): FrontendCommentArray
        {
            return $this->comments->find('parent_id=' . $this->get('id'));
        }

        /**
         * Get the no-vote alert object
         * @return \FrontendForms\TextElements
         */
        public function getNoVoteAlertbox(): TextElements
        {
            $noVoteAlertbox = new TextElements();
            $noVoteAlertbox->setAttribute('id', $this->field->name . '-' . $this->get('id') . '-novote');
            $noVoteAlertbox->setAttribute('class', 'fc-novote');
            return $noVoteAlertbox;
        }

        /**
         * Output wrapper for the no-vote alert box - will be filled via Ajax
         * @return string
         */
        protected function renderNoVoteAlertbox(): string
        {
            return $this->getNoVoteAlertbox()->renderNonSelfclosingTag($this->getNoVoteAlertbox()->getTag(), true);
        }

        /**
         * Create the comment author object
         * @return \FrontendForms\TextElements
         * @throws \ProcessWire\WireException
         */
        protected function createCommentAuthor(): TextElements
        {
            $this->commentAuthor = $this->wire(new TextElements());
            $this->commentAuthor->setTag('h6');
            $this->commentAuthor->setContent($this->get('author'));
            $this->commentAuthor->setAttribute('class', 'comment-name by-author');
            return $this->commentAuthor;
        }

        /**
         * Get the comment author name object
         * @return \FrontendForms\TextElements
         */
        public function ___getCommentAuthor(): TextElements
        {
            return $this->commentAuthor;
        }

        /**
         * Output the comment author name markup
         * @throws \ProcessWire\WireException
         */
        protected function ___renderCommentAuthor(): string
        {
            return $this->getCommentAuthor()->render();
        }

        /**
         * Get the avatar image object of the user if an image exists
         * @return \FrontendForms\Image|null
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function createCommentAvatar(): Image|null
        {
            $avatar = $this->getUserImage();
            $this->avatar = null;
            if (!is_null($avatar)) {

                // create the cropped and resized image first
                $imgWidth = $this->imagesize;
                $thumb = $avatar->size($imgWidth, $imgWidth);

                $this->avatar = $this->wire(new Image());
                $this->avatar->setAttribute('width', $this->imagesize);
                $this->avatar->setAttribute('height', $this->imagesize);
                $this->avatar->setAttribute('alt', sprintf($this->_('Profile image of %s'), $this->get('author')));
                $this->avatar->setAttribute('src', $thumb->url);
                $this->avatar->setAttribute('class', 'avatar');
                $this->avatar->wrap()->setTag('span')->setAttribute('class', 'comment-avatar');

            }
            return $this->avatar;
        }

        /**
         * Get the avatar object
         * @return \FrontendForms\Image|null
         */
        public function ___getCommentAvatar(): Image|null
        {
            return $this->avatar;
        }

        /**
         * Render the avatar image
         * @return string
         * @throws \ProcessWire\WireException
         */
        protected function ___renderCommentAvatar(): string
        {
            $out = '';
            if ($this->getCommentAvatar())
                $out = $this->getCommentAvatar()->render();
            return $out;
        }

        /**
         * Get the creation date object
         * @return \FrontendForms\TextElements
         * @throws \ProcessWire\WireException
         */
        protected function createCommentCreated(): TextElements
        {
            $this->commentCreated = $this->wire(new TextElements());
            $this->commentCreated->setTag('span')->setAttribute('class', 'comment-created');

            if (!$this->get('created'))
                $this->set('created', time());

            $this->commentCreated->setContent($this->getFormattedCommentCreationDate($this->get('created'), $this->field->get('input_fc_dateformat')));

            return $this->commentCreated;
        }

        /**
         * Get the creation date object
         * @return \FrontendForms\TextElements
         */
        public function ___getCommentCreated(): TextElements
        {
            return $this->commentCreated;
        }

        /**
         * Render the creation date markup
         * @return string
         * @throws \ProcessWire\WireException
         */
        protected function ___renderCommentCreated(): string
        {
            return $this->getCommentCreated()->render();
        }

        /**
         * Render stars in a half-step number
         * @param float|int|string|null $stars
         * @param int|null $show
         * @param bool $showNull
         * @return string
         */
        public static function ___renderStarsOnly(float|int|null|string $stars, int|null $show, bool $showNull = false): string
        {
            $out = '';

            if (!$show) return $out;

            if ($showNull && $stars == null) {
                $stars = 0;
            }
            if (!is_null($stars)) {

                $stars = (float)$stars;
                $out = '<div class="star-rating-result">';

                $fullStars = round($stars, 0, PHP_ROUND_HALF_DOWN);

                $halfStars = (($stars - $fullStars) === 0.0) ? 0 : 1;
                $emptyStars = 5 - $fullStars - $halfStars;
                // full stars
                if ($fullStars) {
                    for ($x = 1; $x <= $fullStars; $x++) {
                        $out .= '<span class="star on"></span>';
                    }
                }
                if ($halfStars) {
                    $out .= '<span class="star half"></span>';
                }
                if ($emptyStars) {
                    for ($x = 1; $x <= $emptyStars; $x++) {
                        $out .= '<span class="star"></span>';
                    }
                }
                $out .= '</div>';
            }
            return $out;
        }

        /**
         * Render the star rating markup
         * @return string
         */
        public function ___renderRating(): string
        {
            $out = '';

            $showStarRating = $this->field->get('input_fc_stars');
            if ($showStarRating > 0) {
                $out = self::___renderStarsOnly($this->get('stars'), $showStarRating, true);
            }
            return $out;
        }

        /**
         * Render the vote markup
         * @return string
         * @throws \ProcessWire\WireException
         */
        public function ___renderVotes(): string
        {
            $out = '';

            if ($this->field->get('input_fc_vote')) {
                $out .= $this->getDownVoteElement()->render();
                $out .= $this->getUpVoteElement()->render();
            }
            return $out;
        }

        /**
         * Create the object for the comment text
         * @throws \ProcessWire\WireException
         */
        protected function createCommentText(): TextElements
        {
            $this->commentText = $this->wire(new TextElements());
            $this->commentText->setContent($this->get('text'));
            $this->commentText->setAttribute('class', 'comment-content');
            if ($this->get('status') === 3) {
                $this->commentText->setContent('<div class="comment-spam">' . $this->_('Sorry, this post has been removed by moderators for violating community guidelines.') . '</div>');
            }

            return $this->commentText;
        }

        /**
         * Get the comment text object
         * @return \FrontendForms\TextElements
         */
        public function ___getCommentText(): TextElements
        {
            return $this->commentText;
        }

        /**
         * Render the comment text markup
         * @return string
         * @throws \ProcessWire\WireException
         */
        public function renderCommentText(): string
        {
            return $this->getCommentText()->render();
        }

        /**
         * Create the object for the feedback text
         * @return \FrontendForms\TextElements
         * @throws \ProcessWire\WireException
         */
        public function createCommentFeedback(): TextElements
        {
            $this->feedbackText = $this->wire(new TextElements());
            $this->feedbackText->setContent($this->get('moderation_feedback'));
            $this->feedbackText->setAttribute('class', 'comment-feedback');
            return $this->feedbackText;
        }

        /**
         * Get the feedback text object
         * @return \FrontendForms\TextElements
         */
        public function ___getCommentFeedback(): TextElements
        {
            return $this->feedbackText;
        }

        /**
         * Render the feedback text markup
         * @return string
         * @throws \ProcessWire\WireException
         */
        public function ___renderFeedbackText(): string
        {
            return $this->getCommentFeedback()->render();
        }

        /**
         * Get the reply-link object
         * @return \FrontendForms\Link
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function createReplyLink(): Link
        {
            $this->replyLink = $this->wire(new Link($this->field->name . '-reply-' . $this->get('id')));
            $this->replyLink->setUrl($this->wire('input')->url(['withQueryString' => true]));
            $this->replyLink->setAnchor('reply-comment-form-' . $this->field->name . '-reply-' . $this->get('id'));
            $this->replyLink->setAttribute('class', 'fc-comment-reply');
            $this->replyLink->setAttribute('title', $this->_('Reply to this comment'));
            $this->replyLink->setAttribute('data-field', $this->field->name);
            $this->replyLink->setAttribute('data-parent_id', $this->get('parent_id'));
            $this->replyLink->setAttribute('data-id', $this->get('id'));
            $this->replyLink->setAttribute('data-ajax', $this->submitAjax ?? '0');
            $this->replyLink->setLinkText($this->_('Reply'));
            //$this->replyLink->wrap()->setTag('span')->setAttribute('class', 'icon-box');
            return $this->replyLink;
        }

        /**
         * Get the reply link object
         * @return \FrontendForms\Link
         */
        public function ___getReplyLink(): Link
        {
            return $this->replyLink;
        }

        /**
         * Output the reply link markup
         * @param int $level
         * @return string#
         */
        public function ___renderReplyLink(int $level): string
        {

            $out = '';

            // check if the reply link should be shown or not
            if ($level < $this->field->get('input_fc_reply_depth')) {

                // check if only logged-in users are allowed to write comments
                if (!$this->field->get('input_fc_loggedin_only')) {
                    $out = $this->getReplyLink()->render();
                } else {
                    if ($this->user->isLoggedin()) {
                        $out = $this->getReplyLink()->render();
                    }
                }
            }
            return $out;
        }

        /**
         * Get the website link object
         * @return \FrontendForms\Link
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function createWebsiteLink(): Link
        {
            // Website Link
            $this->websiteLink = $this->wire(new Link($this->field->name . '-website-' . $this->get('id')));
            $this->websiteLink->setUrl($this->get('website'));
            $this->websiteLink->setAttribute('class', 'fc-comment-website');
            $this->websiteLink->setAttribute('title', $this->_('To the homepage of the author'));
            $this->websiteLink->setAttribute('rel', 'nofollow');
            $this->websiteLink->prepend('<span class="author-homepagelink-label">' . $this->_('Homepage of the author:') . '</span> ');
            $this->websiteLink->wrap()->setAttribute('class', 'fc-website-link');
            return $this->websiteLink;
        }

        /**
         * Get the website link object
         * @return \FrontendForms\Link
         */
        public function ___getWebsiteLink(): Link
        {
            return $this->websiteLink;
        }

        /**
         * Render the website link object
         * @return string
         */
        public function ___renderWebsiteLink(): string
        {
            return $this->getWebsiteLink()->render();
        }

        /**
         * Check if the given reply form has been submitted
         * @return bool
         */
        protected function formIsSubmitted(): bool
        {
            return array_key_exists('reply-form-' . $this->get('id') . '-ajax-' . $this->page->id . '-comments-' . $this->get('id'), $_POST);
        }

        /**
         * Check if this reply form should be loaded via Ajax call
         * @param int $commentID
         * @return bool
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function formIsAjaxLoaded(int $commentID): bool
        {
            $id = (int)$this->wire('input')->get('commentid');
            return (($this->wire('config')->ajax) && ($commentID === $id));
        }

        /**
         * Get the cancel button object
         * @return \FrontendForms\Button
         */
        public function ___getCancelButton(): Button
        {
            // add a cancel button to cancel the reply
            $cancelButton = new Button('cancel');
            $cancelButton->setAttribute('type', 'button');
            $cancelButton->setAttribute('data-id', $this->get('id'));
            $cancelButton->setAttribute('data-field', $this->field->name);
            $cancelButton->setAttribute('class', 'fc-cancel-button');
            $cancelButton->setAttribute('value', $this->_('Cancel'));
            return $cancelButton;
        }

        /**
         * Get the hidden inputfield object for ajax requests
         * @return \FrontendForms\InputHidden
         * @throws \Exception
         */
        public function ___getAjaxHiddenField(): InputHidden
        {
            // add a special input field for ajax request to identify the form which has been submitted
            $ajaxRequest = new InputHidden('ajax-' . $this->page->id . '-' . $this->field->name . '-' . $this->get('id'));
            $ajaxRequest->setAttribute('name', 'ajax-' . $this->page->id . '-' . $this->field->name . '-' . $this->get('id'));
            $ajaxRequest->setAttribute('value', $this->get('id'));
            return $ajaxRequest;
        }

        /**
         * Get the form object for the reply form
         * @return \FrontendForms\Form
         * @throws \ProcessWire\WireException
         * @throws \Exception
         */
        public function ___getReplyForm(): Form
        {

            // Reply form
            $form = new FrontendCommentForm($this->comments, 'reply-form-' . $this->get('id'), $this->get('id'));
            $form->setAttribute('class', 'reply-form');
            $form->setLevel($this->get('level') + 1);
            $form->setAttribute('action', $this->wire('input')->url(['withQueryString' => true]) . '&formid=reply-form-' . $this->get('id') . '#reply-comment-form-' . $this->field->name . '-reply-' . $this->get('id'));
            $form->add($this->getCancelButton());
            $form->add($this->getAjaxHiddenField());
            // set a new headline for the reply form
            $headline = $form->getHeadline();
            $headline->setContent($this->_('Write a reply to this comment'));
            $headline->setTag('h4');
            return $form;
        }

        /**
         * Render the markup for the reply form
         * Will be loaded via Ajax if the reply link is clicked
         * @return string
         * @throws \ProcessWire\WireException
         */
        public function ___renderReplyForm(): string
        {
            $out = '<div id="reply-comment-form-' . $this->field->name . '-reply-' . $this->get('id') . '" class="fc-reply-form-wrapper" data-id="' . $this->get('id') . '">';

            // load the reply form only if it is called via Ajax or submitted via POST
            if ($this->formIsAjaxLoaded($this->get('id')) || $this->formIsSubmitted()) {
                $out .= '<div id="reply-comment-form-' . $this->field->name . '-' . $this->get('id') . '" class="fc-reply-form">';
                $out .= $this->getReplyForm()->render();
                $out .= '</div>';
            }
            $out .= '</div>';
            return $out;
        }

        /**
         * Get all children as an array of FrontendComment objects
         * @return array|null
         */
        public function getChildren(): null|FrontendCommentArray
        {
            return $this->comments->find('parent_id=' . $this->get('id'));
        }

        /**
         * Get the number of children
         * @return int
         */
        public function numberOfChildren(): int
        {
            return $this->getChildren()->count();
        }

        /**
         * Check if comment has children and return true or false
         * @return bool
         */
        public function hasChildren(): bool
        {
            return $this->numberOfChildren() ? true : false;
        }

        /**
         * General method to update multiple comment values inside the database
         * @param array $propValues
         * @return bool
         * @throws \ProcessWire\WireException
         */
        public function updateProperties(array $propValues): bool
        {

            $field = $this->field;
            $fieldTable = $field->get('table');
            $database = $this->wire('database');

            $updateStringArray = [];
            foreach ($propValues as $propertyName => $propertyValues) {
                $updateStringArray[] = $propertyName . '=:' . $propertyName;
            }
            $updateString = implode(', ', $updateStringArray);

            $statement = "UPDATE $fieldTable SET $updateString WHERE id=:id";

            try {
                $query = $database->prepare($statement);
                foreach ($propValues as $propertyName => $propertyValues) {
                    $query->bindValue(':'.$propertyName, $propertyValues['value'], $propertyValues['param']);
                }
                $query->bindValue(":id", $this->get('id'), PDO::PARAM_INT);
                return $query->execute();
            } catch (Exception) {
                $msg = ['alert_errorClass' => $this->_('Unfortunately an error occurred during the saving process.')];
                return false;
            }
        }

        /**
         * Simple method to save a new status value to the database
         * @param int $status
         * @return bool
         */
        public function updateStatus(int $status): bool
        {
            return $this->updateProperties(['status' => ['value' => $status, 'param' => PDO::PARAM_INT]]);
        }


        /**
         * Render the comment without using a framework markup
         * @param string $levelnumber
         * @param int $level
         * @return string
         * @throws \ProcessWire\WireException
         */
        public function ___renderComment(string $levelnumber, int $level = 0): string
        {
            $out = '<div class="comment-box"><div class="comment-head">';
            $out .= $this->renderNoVoteAlertbox();
            $out .= $this->renderCommentAvatar();
            $out .= $this->renderCommentAuthor();
            $out .= $this->renderCommentCreated();
            $out .= $this->renderRating();
            $out .= $this->renderReplyLink($level);
            $out .= '<div class="votes">'.$this->renderVotes().'</div>';
            $out .= '</div>';
            $out .= $this->renderCommentText();
            $out .= $this->renderFeedbackText();
            $out .= $this->renderWebsiteLink();
            $out .= $this->renderReplyForm();
            $out .= '</div>';
            return $out;
        }

    }
