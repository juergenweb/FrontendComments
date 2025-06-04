<?php
    declare(strict_types=1);

    namespace FrontendComments;

    /*
     * Class for creating an extended WireArray containing all comments
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: FrontendCommentArray.php
     * Created: 24.12.2024
     *
     */

    use Exception;
    use FrontendForms\Alert;
    use FrontendForms\Link;
    use ProcessWire\Field;
    use ProcessWire\Page;
    use ProcessWire\PaginatedArray;
    use ProcessWire\WirePaginatable;
    use ProcessWire\FieldtypeFrontendComments;
    use PDO;

    class FrontendCommentArray extends PaginatedArray implements WirePaginatable
    {

        protected Page|null $page = null;
        protected Field|null $field = null;
        protected FrontendComments|FrontendCommentsUikit3 $comments;
        protected FrontendCommentForm $form;
        protected array $frontendFormsConfig = [];
        protected array $userdata = [];
        protected Alert $alert;

        /**
         * @throws \ProcessWire\WireException
         */
        public function __construct()
        {
            include_once(__DIR__ . '/FrontendCommentForm.php');

            // load the Notifications class
            require_once(__DIR__ . '/Notifications.php');

            parent::__construct();

            // put user data inside an array for later usage by votes
            $this->userdata = [
                'user_id' => $this->wire('user')->id,
                'ip' => $this->wire('session')->getIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ];

            // grab configuration values from the FrontendForms module
            $this->frontendFormsConfig = FieldtypeFrontendComments::getFrontendFormsConfigValues();
            // instantiate the alert object
            $this->alert = new Alert();
        }

        /**
         * Set the page that these comments are on
         * @param \ProcessWire\Page $page
         * @return void
         */
        public function setPage(Page $page): void
        {
            $this->page = $page;
        }

        /**
         * Get the page object for the comments
         * @return \ProcessWire\Page
         * @throws \ProcessWire\WireException
         */
        public function getPage(): Page
        {
            $page = $this->page;
            $page->set('useCommentCSS', []);
            return $page;
        }

        /**
         * Set the Field that these comments are on
         * @param \ProcessWire\Field $field
         * @return void
         */
        public function setField(Field $field): void
        {
            $this->field = $field;
        }

        /**
         * Get the field object of containing the comments
         * For usage in other classes
         * @return \ProcessWire\Field
         */
        public function getField(): Field
        {
            return $this->field;
        }

        /**
         * Get the total number of comments
         * @return int
         */
        public function getTotalComments(): int
        {
            return $this->count();
        }

        /**
         * Set moderation type (0,1 or 2)
         * @param int $moderation
         * @return $this
         * @throws \Exception
         */
        public function setModeration(int $moderation): self
        {
            if ($moderation >= 0 && $moderation < 3) {
                $this->getField()->set('input_fc_moderate', $moderation);
            } else {
                throw new Exception("Value must be 0, 1 or 2");
            }
            return $this;
        }

        /**
         * Whether only logged-in users can comment or not
         *
         * @param bool $loggedIn
         * @return $this
         */
        public function setLoginRequired(bool $loggedIn): self
        {
            $this->getField()->set('input_fc_loggedin_only', $loggedIn);
            return $this;
        }

        /**
         * Show the website field inside the form or not
         * @param bool $showWebsiteField
         * @return self
         */
        public function showWebsiteField(bool $showWebsiteField): self
        {
            $this->getField()->set('input_fc_showWebsite', $showWebsiteField);
            return $this;
        }

        /**
         * Set the email template on the field base
         * @param string $template
         * @return $this
         */
        public function setEmailTemplate(string $template): self
        {
            $this->getField()->set('input_fc_emailTemplate', $template);
            return $this;
        }

        /**
         * Set the email addresses for the moderators
         * @param string $recipients
         * @return $this
         */
        public function setModerationEmail(string $recipients): self
        {
            $this->getField()->set('input_fc_emailtype', 'custom');
            $this->getField()->set('mod_emails', explode(',', $recipients));
            return $this;
        }

        /**
         * Get the value of the "input_fc_default_to" or "input_fc_emailfield" or the "custom" config setting
         * Returns the default value(s) or the overwritten value(s) on per field base
         * @return array|null
         */
        public function getModerationEmail(): array|null
        {
            if ($this->getField()->get('input_fc_emailtype') === 'custom')
                return $this->getField()->get('mod_emails');

            $field_name = ($this->getField()->get('input_fc_emailtype') === 'text') ? 'input_fc_default_to' : 'input_fc_emailfield';
            $values = $this->getField()->get($field_name);
            if ($field_name === 'input_fc_default_to') {
                $values = preg_split('/\r\n|[\r\n]/', $values);
            }
            return $values;
        }

        /**
         * Set the value if the commenter should be informed about new replies or not
         * @param int $replyNotification
         * @return $this
         * @throws \Exception
         */
        public function setReplyNotification(int $replyNotification): self
        {
            if ($replyNotification >= 0 && $replyNotification < 3) {
                $this->getField()->set('input_fc_comment_notification', $replyNotification);
            } else {
                throw new Exception("Value must be 0, 1 or 2");
            }
            return $this;
        }

        /**
         * Set the notification values for status changes
         * @param array $statusChangeNotification
         * @return $this
         * @throws \Exception
         */
        public function setStatusChangeNotification(array $statusChangeNotification): self
        {
            if (min($statusChangeNotification) > 0 && max($statusChangeNotification) < 3) {
                $this->getField()->set('input_fc_status_change_notification', $statusChangeNotification);
            } else {
                throw new Exception("Allowed values in the array are 1 and/or 2");
            }
            return $this;
        }

        /**
         * Set the tag for the form headline string
         * @param string $tag
         * @return $this
         */
        public function setFormHeadlineTag(string $tag): self
        {
            $this->getField()->set('input_fc_form_tag_headline', $tag);
            return $this;
        }

        /**
         * Show or hide the star rating inside the form
         * @param int $show
         * @return $this
         * @throws \ProcessWire\WireException
         */
        public function showStarRating(int $show): self
        {
            $this->getField()->set('input_fc_stars', $show);
            $page = $this->getPage();
            $fieldName = $this->field->name;
            $propName = 'useCommentStars' . $fieldName;
            $page->$propName = $show;
            return $this;
        }

        /**
         * Show the tooltip next to the star rating
         * @param bool $show
         * @return $this
         */
        public function disableTooltip(bool $show = true): self
        {
            $this->getField()->set('input_fc_showtooltip', $show);
            return $this;
        }

        /**
         * Hide the character counter below the textarea field or not
         * @param bool $hide
         * @return $this
         */
        public function hideCharacterCounter(bool $hide = false): self
        {
            $this->getField()->set('input_fc_counter', $hide);
            return $this;
        }

        /**
         * Enable/disable privacy text and set type of privacy
         * @param int $privacy
         * @return $this
         */
        public
        function setPrivacyType(int $privacy = 0): self
        {
            $this->getField()->set('input_fc_privacy_show', $privacy);
            return $this;
        }

        /**
         * Set the tag for the list headline
         * @param string $tag
         * @return $this
         */
        public function setListHeadlineTag(string $tag): self
        {
            $this->getField()->set('input_fc_comments_tag_headline', $tag);
            return $this;
        }

        /**
         * Set the reply depth
         * @param int $replyDepth -> 0 or higher
         * @return self
         * @throws \Exception
         */
        public function setReplyDepth(int $replyDepth): self
        {
            if ($replyDepth < 0)
                throw new Exception("Value must a positive number (0 or higher)");
            $this->getField()->set('input_fc_reply_depth', $replyDepth);
            return $this;
        }

        /**
         * Set the sort order
         * @param bool $sort
         * @return $this
         */
        public function sortNewestToOldest(bool $sort): self
        {
            $this->getField()->set('input_fc_sort', $sort);
            return $this;
        }

        /**
         * Set the date format for the comments
         * @param int $dateFormat -> 0 = full date format, 1 = relative date format
         * @return $this
         * @throws \Exception
         */
        public function setDateFormat(int $dateFormat): self
        {
            if (!in_array($dateFormat, [0, 1]))
                throw new Exception("Value must be 0 (full date) or 1 (relative date)");
            $this->getField()->set('input_fc_dateformat', $dateFormat);
            return $this;
        }

        /**
         * Set the Captcha on per field base
         * @param string $captchaType
         * @return self
         */
        public function setCaptchaType(string $captchaType): self
        {
            $this->getField()->set('input_fc_captcha', $captchaType);
            return $this;
        }

        /**
         * Set the pagination number on per field base
         * @param int $number
         * @return $this
         */
        public function setPaginationNumber(int $number): self
        {
            $this->getField()->set('input_fc_pagnumber', $number);
            return $this;
        }

        /**
         * Set the pagination alignment on per field base
         * @param string $paginationAlignment
         * @return $this
         */
        public function setPaginationAlignment(string $paginationAlignment): self
        {
            $allowedValues = ['left', 'right', 'center'];
            if (!in_array($paginationAlignment, $allowedValues)) {
                trigger_error(sprintf("Value for pagination alignment must be left, center or right. The value you have set is %s, which is not a valid value. Please correct your value, otherwise the pagination will be centered.", $paginationAlignment), E_USER_WARNING);
                $paginationAlignment = 'center';
            }
            $this->getField()->set('input_fc_pagorientation', $paginationAlignment);
            return $this;
        }

        /**
         * Set the sender email address, which will be displayed as the sender of the emails
         * @param string $senderEmailAddress
         * @return $this
         */
        public function setSenderEmailAddress(string $senderEmailAddress): self
        {
            $this->getField()->set('input_fc_from', $senderEmailAddress);
            return $this;
        }

        /**
         * Set the name of the sender of the mails
         * @param string $senderName
         * @return $this
         */
        public function setSenderName(string $senderName): self
        {
            $this->getField()->set('input_fc_from_name', $senderName);
            return $this;
        }

        /**
         * Set the position of the form on per field base
         * @param bool $showAfter - true: form will be displayed after the comments; false: form will be displayed
         *     before Comments
         * @return $this
         */
        public function showFormAfterComments(bool $showAfter = false): self
        {
            $this->getField()->set('input_fc_reverse_output', $showAfter);
            return $this;
        }

        /**
         * Set the embedding/removing of the CSS files on per comments' base
         * @param bool $removeCSS -> false: embedding, true: removing
         * @return $this
         * @throws \ProcessWire\WireException
         */
        public function removeCSS(bool $removeCSS): self
        {
            $this->getField()->set('input_removeFrontendCommentsJS', (int)$removeCSS);
            $page = $this->getPage();
            $fieldName = $this->field->name;
            $propName = 'useCommentCSS' . $fieldName;
            $page->$propName = !$removeCSS;
            return $this;
        }

        /**
         * Set the embedding/removing of the JS files on per comments' base
         * @param bool $removeJS -> false: embedding, true: removing
         * @return $this
         * @throws \ProcessWire\WireException
         */
        public function removeJS(bool $removeJS): self
        {
            $this->getField()->set('input_removeFrontendCommentsJS', (int)$removeJS);
            $page = $this->getPage();
            $fieldName = $this->field->name;
            $propName = 'useCommentJS' . $fieldName;
            $page->$propName = !$removeJS;
            return $this;
        }

        /**
         * Set the text for the list headline
         * @param string $text
         * @return $this
         */
        public function setListHeadlineText(string $text): self
        {
            $this->getField()->set('input_fc_comments_headline', $text);
            return $this;
        }

        /**
         * Set the text for the form headline
         * Alias method for $this->getHeadline()->setContent($text)
         * @param string $text
         * @return \FrontendComments\FrontendCommentArray
         */
        public function setFormHeadlineText(string $text): self
        {
            $this->getField()->set('input_fc_form_headline', $text);
            return $this;
        }

        /**
         * Enable/disable the up- and downvotes inside the comments
         * @param bool $showVoting
         * @return $this
         */
        public function showVoting(bool $showVoting): self
        {
            $this->getField()->set('input_fc_vote', $showVoting);
            return $this;
        }

        /**
         * Get the pagination object depending on the framework set
         * @return \FrontendComments\FrontendCommentPagination
         * @throws \ProcessWire\WireException
         */
        public
        function getPagination(): FrontendCommentPagination
        {
            $filename = 'FrontendCommentPagination' . FieldtypeFrontendComments::getFrameWork();
            $class = __NAMESPACE__ . '\\' . $filename;
            $pagination = $this->wire(new FrontendCommentPagination($this));
            if (class_exists($class)) {
                $pagination = $this->wire(new $class($this));
            }
            return $pagination;
        }

        /**
         * Render the pagination markup
         * @return string
         * @throws \ProcessWire\WireException
         */
        public
        function ___renderPagination(): string
        {
            $pagination = $this->getPagination();
            return $pagination->render();
        }

        /**
         * Get the form object
         * @return \FrontendComments\FrontendCommentForm
         * @throws \ProcessWire\WireException
         */
        public
        function getForm(): FrontendCommentForm
        {
            $form = new FrontendCommentForm($this);

            // enable or disable character counter depending on settings
            $form->getFormelementByName('text')->useCharacterCounter(!$this->field->get('input_fc_counter'));

            // enable of disable the privacy field
            $form->setPrivacyType($this->field->get('input_fc_privacy_show'));

            return $form;
        }

       // public function get

        /**
         * Render the comment form on the frontend
         * @return string
         * @throws \ProcessWire\WireException
         */
        public
        function ___renderForm(): string
        {
            $form = $this->getForm();
            return $form->render();
        }

        /**
         * Render the comments depending on the framework set
         * @param \FrontendComments\FrontendCommentArray $commentsarray
         * @return string
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        public
        function ___renderComments(FrontendCommentArray $commentsarray): string
        {
            $classname = 'FrontendComments' . FieldtypeFrontendComments::getFrameWork();

            $class = __NAMESPACE__ . '\\' . $classname;
            $comments = $this->wire(new FrontendComments($commentsarray));
            if (class_exists($class)) {
                $comments = $this->wire(new $class($commentsarray));
            }
            return $comments->renderCommentsDiv();
        }

        /**
         * Cancel the receiving of notification mails on new comments by clicking the remote link inside the email
         * @return array
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function saveReplyNotificationRemote(): array
        {
            // Get and sanitize get parameters
            $code = $this->wire('input')->get('code', 'string');
            $notification = $this->wire('input')->get('notification', 'string');

            $msg = [];
            $table = 'field_' . $this->field->name;

            if ((!is_null($code)) && ($notification === '0')) {

                // check if a comment with this code exists inside the table
                $comment = $this->get('code=' . $code);

                if ($comment) {

                    if ($comment->get('notification') === 0) {
                        $msg = ['alert_warningClass' => $this->_('You have already canceled the receiving of reply notification mails for this comment.')];
                    } else {
                        if ($comment->updateProperties(['notification' => ['value' => $notification, 'param' => PDO::PARAM_INT]])) {
                            $msg = ['alert_successClass' => $this->_('You have successfully canceled the receiving of reply notification mails.')];
                        }
                    }

                } else {
                    // output a message that comment with this code was not found
                    $msg = ['alert_warningClass' => $this->_('Unfortunately, no matching comment was found for this code.')];

                }

            }
            return $msg;
        }

        /**
         * Create the redirect link for a comment
         * This link forces a redirect to the page where the comment lives
         * @param int $commentid
         * @return \FrontendForms\Link
         * @throws \ProcessWire\WireException
         */
        protected function ___getCommentRedirectPaginationLink(int $commentid): Link
        {
            $link = new Link();
            $link->setUrl($this->wire('page')->url);
            $link->setQueryString('comment-redirect=' . $commentid);
            $link->setLinkText($this->_('View the comment'));
            return $link;
        }

        /**
         * Save a new status for a comment via a remote link from the email
         * Send mail about the status change to the comment author if set
         *
         * @return array|string[]
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function saveStatusRemote(): array
        {
            $msg = [];

            // Get the parameters
            $code = $this->wire('input')->get('code');
            $status = $this->wire('input')->get('status');

            $table = $this->field->getTable();

            if (!is_null($code) && !is_null($status)) {

                // sanitize the code, status and user id
                $code = $this->wire('sanitizer')->string120($code);
                $status = $this->wire('sanitizer')->int($status);

                // check if a comment with this code exists inside the database table
                $comment = $this->get('code=' . $code);

                if ($comment) {

                    // check if the comment status has been changed in the past via a remote link
                    if ($comment->remote_flag) {
                        $text = $this->_('Unfortunately, the status of this comment has already been changed via remote link.');
                        $text .= '<br>' . $this->_('For security reasons, the status of a comment can only be changed once via remote link. You will need to log in to the backend to change the status of this comment again.');
                        return ['alert_warningClass' => $text];
                    }

                    // set the table name to the comment
                    $comment->set('page', $this->page);
                    $comment->set('field', $this->field);

                    // comment status should be changed to "spam" (2) or to 3 if the comment has children and therefore could not be deleted
                    if (($status === 2) && ($comment->getReplies()->count)) {
                        $status = 3;
                    }

                    if ($status === 2) {
                        // add timestamp to the database
                        $spamTS = time();
                    } else {
                        $spamTS = null;
                    }

                    $updateValues = [
                        'status' => ['value' => $status, 'param' => PDO::PARAM_INT],
                        'remote_flag' => ['value' => 1, 'param' => PDO::PARAM_INT],
                        'spam_update' => ['value' => $spamTS, 'param' => PDO::PARAM_INT]
                    ];

                    if ($comment->updateProperties($updateValues)) {

                        // output a message that status has been changed
                        $statusCodes = [
                            FieldtypeFrontendComments::pendingApproval => $this->_('waiting for approval'),
                            FieldtypeFrontendComments::approved => $this->_('approved'),
                            FieldtypeFrontendComments::spam => $this->_('spam'),
                            FieldtypeFrontendComments::spamReplies => $this->_('spam')
                        ];

                        $alertText = sprintf($this->_('The status of the comment has been updated to "%s".'), $statusCodes[$status]);

                        $statusChangeNotification = $this->field->get('input_fc_status_change_notification');
                        // check if mail should be sent on status change to the commenter
                        switch ($status) {
                            case FieldtypeFrontendComments::approved:
                                $send = in_array('1', $this->field->get('input_fc_status_change_notification'));
                                // add information text that mail has been sent to the commenter to inform him about the status change
                                if (in_array('1', $statusChangeNotification)) {
                                    $alertText .= '<br>' . $this->_('In addition, an email was sent to the commenter to inform him of the status change.');
                                }

                                $alertText .= '<br>' . $this->getCommentRedirectPaginationLink($comment->id)->render();
                                break;
                            case FieldtypeFrontendComments::spam:
                            case FieldtypeFrontendComments::spamReplies:
                                if (in_array('2', $statusChangeNotification)) {
                                    $alertText .= '<br>' . $this->_('In addition, an email was sent to the commenter to inform him of the status change.');
                                }
                                $send = in_array('2', $this->field->get('input_fc_status_change_notification'));
                                break;
                            default:
                                $send = false;
                        }

                        // send the notification mail
                        if ($send) {
                            $notification = new Notifications($this, $this->field, $this->page);
                            $notification->sendStatusChangeEmail($comment, $this->field, $this->frontendFormsConfig, $status);
                        }

                        $msg = ['alert_successClass' => $alertText];
                        // change the queue table on status change
                        FieldtypeFrontendComments::updateQueueTableOnStatusChange($comment, $status, $this->page, $this->field);

                    } else {
                        $msg = ['alert_errorClass' => $this->_('Unfortunately an error occurred during the update process of the status.')];
                    }

                } else {
                    // output a message that comment with this code was not found
                    $msg = ['alert_warningClass' => $this->_('Unfortunately, no matching comment was found for this code.')];
                }
            }
            return $msg;
        }

        /**
         * Save votes via Ajax call to the votes' table
         * @return void
         * @throws \ProcessWire\WireException
         */
        protected function saveVotes(): void
        {
            // check if the rating is enabled;
            $field = $this->field;
            if (!is_null($field->get('input_fc_vote'))) {

                if ($this->wire('config')->ajax) {

                    // check if the querystring votecommentid is present for adding a vote to a comment
                    $queryString = $this->wire('input')->queryString();
                    parse_str($queryString, $queryParams);

                    if (array_key_exists('votecommentid', $queryParams)) {
                        if (array_key_exists('vote', $queryParams)) {

                            $vote = $queryParams['vote'];
                            $database = $this->wire('database');
                            $fieldTableName = 'field_' . $this->field->name;
                            $votesTableName = 'field_' . $this->field->name . '_votes';
                            $comment = $this->find('id=' . $queryParams['votecommentid'])->first();

                            // 1) check first if the user has not voted for this comment within a certain number of days

                            $statement = "SELECT id 
		                        FROM $votesTableName
                                WHERE comment_id = :comment_id
                                AND user_id = :user_id
                                AND user_agent = :user_agent
                                AND field_id = :field_id
                                AND page_id = :page_id
                                AND ip = :ip";

                            $query = $database->prepare($statement);
                            $query->bindValue(':comment_id', $queryParams['votecommentid'], PDO::PARAM_INT);
                            $query->bindValue(':user_id', $this->userdata['user_id'], PDO::PARAM_INT);
                            $query->bindValue(':field_id', $this->field->id, PDO::PARAM_INT);
                            $query->bindValue(':page_id', $this->page->id, PDO::PARAM_INT);
                            $query->bindValue(':user_agent', $this->userdata['user_agent']);
                            $query->bindValue(':ip', $this->userdata['ip']);

                            $rowsnumber = 0;

                            try {
                                $query->execute();
                                $rowsnumber = $query->rowCount();
                                $query->closeCursor();
                                $result = true;
                            } catch (Exception) {
                                $result = false;
                            }

                            if ($result && ($rowsnumber === 0)) {

                                //2) save data to the "votes" table first
                                $statement = "INSERT INTO $votesTableName (comment_id, user_id, user_agent, ip, vote, field_id, page_id)" .
                                    " VALUES (:comment_id, :user_id, :user_agent, :ip, :vote, :field_id, :page_id)";

                                $query = $database->prepare($statement);
                                $query->bindValue(':comment_id', $queryParams['votecommentid'], PDO::PARAM_INT);
                                $query->bindValue(':ip', $this->userdata['ip']);
                                $query->bindValue(':user_id', $this->userdata['user_id'], PDO::PARAM_INT);
                                $query->bindValue(':user_agent', $this->userdata['user_agent']);
                                $query->bindValue(':field_id', $this->field->id);
                                $query->bindValue(':page_id', $this->page->id);

                                $value = ($vote === 'up') ? 1 : -1;

                                $query->bindValue(':vote', $value, PDO::PARAM_INT);

                                $result = 0;

                                try {
                                    $query->execute();
                                    $result = $query->rowCount();
                                    $query->closeCursor();
                                } catch (Exception) {
                                    // not used at the moment
                                }

                                if ($result) {

                                    // 3) increase the upvotes or downvotes in the comments' field table
                                    $commentId = $queryParams['votecommentid'];
                                    $pageId = $this->wire('page')->id;

                                    // update the field table by incrementing up or downloads
                                    $updateCol = ($value === 1) ? 'upvotes' : 'downvotes';

                                    $statement = "UPDATE $fieldTableName 
                                SET $updateCol = :$updateCol
                                WHERE  pages_id=$pageId AND id=$commentId
                                ";

                                    $query = $database->prepare($statement);

                                    $newValue = $comment->{$updateCol} + 1;
                                    $query->bindValue(':' . $updateCol, $newValue, PDO::PARAM_INT);

                                    try {
                                        $query->execute();
                                        $query->closeCursor();
                                    } catch (Exception) {
                                        // not used at the moment
                                    }

                                    // finally, add the new value to the result div
                                    echo '<div id="fc-ajax-vote-result" data-votetype="' . $vote . '">' . $newValue . '</div>';
                                }
                            } else {
                                // not allowed to vote -> create the alert box
                                $alert = new Alert();
                                // grab configuration values from the FrontendComments inputfield
                                $dayslocked = $field->get('input_fc_voting_lock');
                                $timePeriod = $dayslocked . ' ' . $this->_n($this->_('day'),
                                        $this->_('days'), $dayslocked);
                                $alert->setContent(sprintf($this->_('It looks like you have already rated this comment within the last %s. In this case you are not allowed to vote again.'), $timePeriod));
                                $alert->setCSSClass('alert_warningClass');

                                echo '<div id="fc-ajax-noVote">' . $alert->___render() . '</div>';
                            }
                        }
                    }
                }
            }

        }

        /**
         * Get/calculate the number of the pagination page, where the comment lives on
         * Outputs the pagination page where the comment is displayed
         * @param int $commentID
         * @return int
         * @throws \ProcessWire\WireException
         */
        protected function getCommentPage(int $commentID): int
        {
            $commentsPerPage = $this->field->get('input_fc_pagnumber');

            if ($commentsPerPage === 0)
                return 1; // pagination hasn't been enabled -> return 1 (first page)

            // get the comment object
            $comment = $this->get('id=' . $commentID);

            // get the sort order of the comments
            $reverse = $this->field->get('input_fc_sort') ?? 0;

            // sort the array
            $comments = FrontendComments::getCommentListArray($this, 0, null, 0, $reverse); // get the sorted commentArray

            // get the current comment position and calculate the pagination page
            $currentCommentPosition = $comments->getItemKey($comment) + 1; // add 1, because the array starts with 0
            return (int)ceil($currentCommentPosition / $commentsPerPage);
        }

        /**
         * Redirect to the comment on a certain pagination page if the query string "comment-redirect" is present
         * @return void
         * @throws \ProcessWire\WireException
         */
        protected function redirectToComment(): void
        {
            // redirect to comment if query string "comment-redirect" is present
            $id = (int)$this->wire('input')->get('comment-redirect');

            if ($id) {

                // check if a comment with this id exists
                if (!$this->find('id=' . $id)->count()) {

                    $this->alert->setContent($this->_('We are sorry, but this comment was not found. It is possible that this comment has already been deleted or has not been published yet.'));
                    $this->alert->setCSSClass('alert_warningClass');
                    $this->alert->setAttribute('id', $this->field . '-' . $this->page->id . '-redirect-alert');

                } else {

                    $pagNum = $this->field->get('input_fc_pagnumber');

                    if ($pagNum === 0) {
                        // no pagination -> redirect to the current page
                        $redirectUrl = $this->page->url . '#comment-' . $id;
                    } else {
                        // pagination enabled -> redirect to the specific pagination page
                        $redirectUrl = $this->page->url . '?' . $this->wire('config')->pageNumUrlPrefix . '=' . $this->getCommentPage($id) . '#comment-' . $id;
                    }

                    // make the redirect
                    $this->wire('session')->redirect($redirectUrl);

                }
            }
        }

        /**
         * Function to render the comment form only if the settings applied are fulfilled
         * @param bool|null $loggedIn
         * @param \FrontendForms\Alert $loggedInAlert
         * @return string
         * @throws \ProcessWire\WireException
         */
        protected function renderLoggedInForm(bool|null $loggedIn, Alert $loggedInAlert): string
        {

            $out = '';

            if ($loggedIn) {
                if ($this->wire('user')->isLoggedin()) {
                    $out .= $this->renderForm();
                } else {
                    $out = $loggedInAlert->render();
                }
            } else {
                $out = $this->renderForm();
            }
            return $out;
        }

        /**
         * Alias method for the renderComments() method
         * @return string
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        public
        function render(): string
        {
            $out = '';

            // check if at least one moderation email is set, otherwise output only a message instead of the comments
            $result = ($this->field->get('input_fc_emailtype') === 'text') ? $this->field->get('input_fc_default_to') : $this->field->get('input_fc_emailfield');

            if (!$result) {
                $alert = new Alert();
                $alert->setContent($this->_('Please set at least one moderation email address for this comments field (go to the backend configuration or set it via API). Otherwise the comments will not be displayed.'));
                return $alert->render();
            }

            // redirect to the comment (takes care about the pagination if enabled)
            $this->redirectToComment();

            // save upvotes and downvotes in the database
            $this->saveVotes();

            // set a unique id for the remote alert box that can be used as the jump link target
            $this->alert->setAttribute('id', 'remote-change');

            // change comment notification per remote link
            $notificationMsg = $this->saveReplyNotificationRemote();
            if ($notificationMsg) {
                $this->alert->setContent($notificationMsg[key($notificationMsg)]);
                $this->alert->setCSSClass(key($notificationMsg));
            }

            // change comment status per remote link
            $statusChangeMsg = $this->saveStatusRemote();
            if ($statusChangeMsg) {
                $this->alert->setContent($statusChangeMsg[key($statusChangeMsg)]);
                $this->alert->setCSSClass(key($statusChangeMsg));
            }

            // output alert if a text was set
            if ($this->alert->getContent()) {
                $out .= $this->alert->render();
            }

            // get comment form position bottom value
            $bottom = $this->field->get('input_fc_reverse_output');

            // check if only logged-in users are allowed to post comments
            $onlyLoggedIn = $this->field->get('input_fc_loggedin_only');

            $loggedInAlert = new Alert();
            $loggedInAlert->setContent($this->_('Please log in to post a comment.'));
            $loggedInAlert->setCSSClass('alert_warningClass');

            // render Form before the comments
            if (!$bottom) {
                $out .= $this->renderLoggedInForm($onlyLoggedIn, $loggedInAlert);
            }

            // render the comment list
            $out .= $this->renderComments($this);

            // render the pagination
            $out .= $this->renderPagination();

            // render Form after the comments
            if ($bottom) {
                $out .= $this->renderLoggedInForm($onlyLoggedIn, $loggedInAlert);
            }

            return $out;
        }

    }
