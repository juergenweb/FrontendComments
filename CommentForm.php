<?php
    declare(strict_types=1);

    /*
     * File for creating, saving and rendering the comment form
     * and to change the status of a comment via remote link from the mail
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: CommentForm.php
     * Created: 24.06.2023
     *
     * @property protected array $statusTexts: array containing the text for the different status
     * @property protected array $frontendFormsConfig:  array containing all configuration settings of the FrontendForms module
     * @property protected array frontendCommentsConfig: array containing all configuration settings of the FrontendComments input field
     * @property protected CommentArray $comments: contains all comments as a CommentArray
     * @property protected Page $page: the page object the comment field is part of
     * @property protected Field $field: the field object for the comment field
     * @property protected WireDatabasePDO $database: the database object
     * @property protected Email $email: the field object for the email field
     * @property protected InputText $author: the field object for the author field
     * @property protected Textarea $comment: the field object for the comment field
     * @property protected InputSelect $stars: the field object for the star rating number field
     * @property protected InputRadioMultiple $notify: the field object for email notification about new comments
     * @property protected Privacy $privacy: the field object for the privacy field
     * @property protected PrivacyText $privacyText: the field object for the privacytext field
     * @property protected InputHidden $pageid: the field object for the hidden pageid field
     * @property protected InputHidden $parentid: the field object for the hidden parentid field
     * @property protected Alert $alert: The alert object
     * @property protected Link $cancel: the link object to cancel the reply comment
     * @property protected string|int $parent_id: the id of the parent comment
     * @property protected string|null $input_fc_email: the email address that should be displayed as the sender of the emails
     * @property protected string|null $input_fc_sender: the name that should be displayed as the sender of the emails
     * @property protected string|null $input_fc_subject: the subject of the mail
     * @property protected string|null $input_fc_title: the title value of the mail
     * @property protected int|null|bool $input_fc_stars: whether to show star rating or not
     * @property protected int|null|bool $input_fc_stars: whether to a textarea counter or not
     * @property protected string|null $input_fc_captcha: whether to use a CAPTCHA or not
     *
     * @method string renderButton(): Render buttons for the email template to publish a comment or mark it as SPAM
     * @method string render(): Output the form markup
     * @method void setMailTo(): Set the notification mail receivers to the WireMail object
     * @method Link getCancelLink(): Method to get the cancel link object for further manipulations
     *
     */

    namespace FrontendComments;

    use Exception;
    use FrontendForms\Email;
    use FrontendForms\Form;
    use FrontendForms\InputHidden;
    use FrontendForms\InputText;
    use FrontendForms\Select;
    use FrontendForms\Textarea;
    use FrontendForms\InputRadioMultiple;
    use FrontendForms\Privacy;
    use FrontendForms\PrivacyText;
    use FrontendForms\Button;
    use FrontendForms\Link;
    use FrontendForms\Alert;
    use PDO;
    use ProcessWire\FieldtypeFrontendComments;
    use ProcessWire\InputfieldFrontendComments;
    use ProcessWire\Page;
    use ProcessWire\Field;
    use ProcessWire\WireDatabasePDO;
    use ProcessWire\WireException;
    use ProcessWire\WireMail;
    use ProcessWire\WireRandom;

    class CommentForm extends Form
    {

        use configValues;

        protected array $frontendFormsConfig = [];
        protected array $frontendCommentsConfig = [];
        protected string $redirectUrl = '';
        protected string|int $parent_id = 0;
        protected string|null $input_fc_email = null;
        protected string|null $input_fc_sender = null;
        protected string|null $input_fc_subject = null;
        protected string|null $input_fc_title = null;
        protected int|bool|null $input_fc_stars = false;
        protected int|bool|null $input_fc_counter = false;
        protected string|null $input_fc_captcha = 'inherit';

        /** class objects */
        protected Email $email; // the email field object
        protected InputText $author; // the author field object
        protected Textarea $comment; // the comment text field object
        protected Select $stars; // the number field for star rating
        protected InputRadioMultiple $notify; // the notify me about new comments field object
        protected Privacy $privacy; // the accept privacy checkbox object
        protected PrivacyText $privacyText; // the accept privacy text object
        protected Button $button; // the submit button object
        protected InputHidden $pageId; // the hidden page id input object
        protected InputHidden $parentId; // the hidden parent page id input object
        protected CommentArray $comments; // the array containing all comments of this page
        protected Page $page; // the page where the form is embedded/displayed
        protected Field $field; // the field of the FrontendComments Fieldtype
        protected WireDatabasePDO $database; // the ProcessWire database object
        protected Alert $alert; // The alert object of the form for further manipulations
        protected Link $cancel; // The cancel link object for replies
        protected Notifications $notifications;

        /**
         * @param CommentArray $comments - needed for getting the field for storage of the values inside the database
         * @param string|null $id
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         * @throws \Exception
         */
        public function __construct(CommentArray $comments, string $id = null, int $parentId = 0)
        {

            if ($this->wire('session')->get('comment') == 'saved') {
                $this->wire('session')->set('comment', 'ready');
            }

            $this->parent_id = $parentId;

            // set default values
            $this->comments = $comments; // the comment text object
            $this->page = $comments->getPage(); // the current page object, which contains the comment field
            $this->field = $comments->getField(); // Processwire comment field object
            $this->database = $this->wire('database'); // the database object

            // set the comment field name as id of the form if id is not present
            if ($id === null) {
                $id = $this->field->name;
            }

            parent::__construct($id);

            // grab configuration values from the FrontendForms module
            $this->frontendFormsConfig = $this->getFrontendFormsConfigValues();

            // grab configuration values from the FrontendComments input field
            $this->frontendCommentsConfig = $this->getFrontendCommentsInputfieldConfigValues();

            // create properties of FrontendComments configuration values
            $properties = ['input_fc_email', 'input_fc_sender', 'input_fc_title', 'input_fc_subject', 'input_fc_stars', 'input_fc_counter', 'input_fc_captcha'];
            $this->createPropertiesOfArray($this->frontendCommentsConfig, $properties);

            // get the redirect url
            $this->redirectUrl = $this->wire('pages')->request()->getRedirectUrl();

            // add internal anchor to the form action attribute to jump directly to the form after submission
            $this->setAttribute('action', $this->page->url . '?formid=' . $this->getID() . '/#' . $this->getID() . '-form-wrapper');

            // redirect to the same page after form passes validation (including anchor)
            $this->setRedirectUrlAfterAjax($this->page->url . '#' . $this->field->name . '-form-wrapper');

            // TODO: delete or set new values afterwards - only for dev purposes set to 0
            $this->setMaxAttempts(0);
            $this->setMinTime(0);
            $this->setMaxTime(0);

            // overwrite currenturllabel
            $this->setMailPlaceholder('currenturllabel', $this->_('Comment page'));

            // Create all form fields

            // create privacy objects and add them to the form object
            $this->privacy = new Privacy('privacy');
            $this->add($this->privacy);
            $this->privacyText = new PrivacyText('privacy-text');
            $this->add($this->privacyText);

            // 1) email
            $this->email = new Email('email');
            if ($this->user->isLoggedin()) {
                $this->email->setDefaultValue($this->user->email);
                $this->email->setAttribute('readonly');
            }
            $this->add($this->email);

            // 2) author
            $this->author = new InputText('author');
            $this->author->setLabel($this->_('Name'));
            $this->author->setRule('firstAndLastname');
            $this->add($this->author);

            // 3) comment
            $this->comment = new Textarea('text');
            $this->comment->setLabel($this->_('Comment'));
            $this->comment->setRule('required');
            $this->comment->setRule('lengthMax', 1024);
            if ($this->input_fc_counter) {
                $this->comment->useCharacterCounter();
            }
            $this->comment->setSanitizer('maxLength'); // limit the length of the comment
            $this->comment->setNotes($this->_('HTML is not allowed.'));
            $this->add($this->comment);

            // 4) star rating
            if ($this->input_fc_stars) {
                $this->stars = new Select('stars');
                $this->stars->useInputWrapper(false);
                $this->stars->useFieldWrapper(false);
                $this->stars->setLabel($this->_('Rating'));
                $this->stars->addOption($this->_('Select a rating'), '');
                $this->stars->addOption($this->_('Excellent'), '5');
                $this->stars->addOption($this->_('Very Good'), '4');
                $this->stars->addOption($this->_('Average'), '3');
                $this->stars->addOption($this->_('Poor'), '2');
                $this->stars->addOption($this->_('Terrible'), '1');
                $this->stars->setAttribute('class', 'star-rating');

                // create data-options string
                $options = [];
                if(isset($this->frontendCommentsConfig['input_fc_showtooltip'])){
                    // disable tooltip
                    $options[] = '&quot;tooltip&quot;:false';
                }
                // set clear-able to true
                $options[]= '&quot;clearable&quot;:true';

                if($options){
                    // create the data-options attribute
                    $optionString = implode(',', $options);
                    $this->stars->setAttribute('data-options', '{'.$optionString.'}');
                }

                if($this->frontendCommentsConfig['input_fc_stars'] == 2){
                    $this->stars->setRule('required');
                }
                // add the post value of the star rating to the star rating render function after form submission
                $number = array_key_exists($this->field->name . '-stars', $_POST) ? $_POST[$this->field->name . '-stars'] : '0';
                $this->add($this->stars);
            }

            // 5) email notification about new comments
            if (array_key_exists('input_fc_comment_notification', $this->frontendCommentsConfig) && ($this->frontendCommentsConfig['input_fc_comment_notification'] !== 0)) {
                $this->notify = new InputRadioMultiple('notification');
                $this->notify->setlabel($this->_('Notify me about new replies'));
                $this->notify->setRule('required');
                $this->notify->setRule('integer');
                $allowedValues = ($this->frontendCommentsConfig['input_fc_comment_notification'] === 1) ? ['0', '1'] : ['0', '1', '2'];
                $this->notify->setRule('in', $allowedValues);
                $this->notify->setNotes($this->_('You can cancel the receiving of notification emails everytime by clicking the link inside the notification email.'));
                $this->notify->setDefaultValue('0');
                $this->notify->addOption($this->_('No notification'), '0');
                $this->notify->addOption($this->_('Notify me about replies to this comment only'), (string)Comment::flagNotifyReply);
                if ($this->frontendCommentsConfig['input_fc_comment_notification'] === 2) {
                    $this->notify->addOption($this->_('Notify me about replies to all comments'), (string)Comment::flagNotifyAll);
                }
                $this->notify->alignVertical();
                $this->add($this->notify);
            }

            // 7) submit button
            $this->button = new Button('submit');
            $this->button->setAttribute('class', 'fc-comment-button');

            $this->button->setAttribute('data-formid', $id);
            $this->add($this->button);

            // 8) hidden field for parent id
            $this->parentId = new InputHidden('parent_id');
            $this->parentId->setAttribute('value', $parentId); // set 0 as default
            $this->add($this->parentId);

            // cancel link
            $this->cancel = new Link($this->field->name . '-cancel');
            $this->cancel->setAttribute('class', 'fc-cancel-link');
            $this->cancel->setAttribute('data-field', $this->field->name);
            $this->cancel->setAttribute('data-id', $this->parent_id);
            $this->cancel->setLinkText($this->_('Cancel'));
            $this->cancel->setAttribute('title', $this->_('Click to cancel the reply'));
            $this->cancel->wrap()->setAttribute('class', 'fc-cancel-link-wrapper');
            $this->cancel->setUrl('#comment-' . $this->parent_id);

            // set the alert object for further manipulations later on
            $this->alert = $this->getAlert();

            // CAPTCHA settings
            // add FrontendForms settings for the CAPTCHA if "inherit" has been chosen
            if ($this->input_fc_captcha === 'inherit') {
                $this->input_fc_captcha = $this->frontendFormsConfig['input_captchaType'];
            }
            $this->setCaptchaType($this->input_fc_captcha);

            // instantiate the Notifications object for creating the mail body text
            $this->notifications = new Notifications($this->comments);

        }

        /**
         * Get the submit button object for further manipulations if needed
         * @return \FrontendForms\Button
         */
        public function getSubmitButton(): Button
        {
            return $this->button;
        }

        /**
         * Get the cancel link object
         * @return \FrontendForms\Link
         */
        public function getCancelLink(): Link
        {
            return $this->cancel;
        }

        /**
         * Round a given float number to half steps
         * Needed for star rating
         * @param $number
         * @return float
         */
        public static function roundToHalfStepNumber($number): float
        {
            $whole = floor($number);      //  fe 1
            $fraction = $number - $whole; //  fe.25
            // round fraction up or down
            if ($fraction < 0.25) {
                $secondPart = 0;
            } else if ($fraction >= 0.25 && $fraction < 0.75) {
                $secondPart = 0.5;
            } else {
                $secondPart = 1;
            }
            return $whole + $secondPart;
        }

        /**
         * Set the notification email receiver to the WireMail object depending on the input field settings
         * This will email the receiver which contains the values of the new added comment (text, email,
         * ip,…)
         * @param WireMail $mail
         *
         * @return void
         * @throws WireException
         * @throws \Exception
         */
        protected function setMailTo(WireMail $mail): void
        {
            if ((array_key_exists('input_fc_emailtype', $this->frontendCommentsConfig)) && ($this->frontendCommentsConfig['input_fc_emailtype'] === 'text')) {
                $receiver = $this->frontendCommentsConfig['input_fc_default_to'];
                $mail->to($receiver);
            } else {
                if (array_key_exists('input_fc_defaultPWField_to', $this->frontendCommentsConfig)) {
                    $receiver = $this->frontendCommentsConfig['input_fc_defaultPWField_to'];
                    $mail->to($receiver);
                } else {
                    throw new Exception('Please go to the field settings of this comment field and add a Processwire field which contains the the receiver mail address.');
                }
            }
        }

        /**
         * Set the status for the new comment depending on the input field settings to 0 or 1
         * Depending on the status, the comment is visible on the website or not
         * @param Comment $comment
         *
         * @return int
         */
        protected function setStatus(Comment $comment): int
        {
            switch ($this->frontendCommentsConfig['input_fc_moderate']) {
                case(FieldtypeFrontendComments::moderateNone);
                    $status = InputfieldFrontendComments::approved;
                    break;
                case(FieldtypeFrontendComments::moderateNew);
                    // count the number of approved comments for the given email address
                    $entries = $this->comments->find('email=' . $comment->email . ',status=1')->count;
                    if ($entries) {
                        $status = InputfieldFrontendComments::approved;
                    } else {
                        $status = InputfieldFrontendComments::pendingApproval;
                    }
                    break;
                default:
                    $status = InputfieldFrontendComments::pendingApproval;
            }
            return $status;
        }

        /**
         * Save the new status of a comment to the database via a remote link of an email
         * @return void
         * @throws \ProcessWire\WireException
         */
        protected function processRemoteLink(): void
        {

            // get the query string
            $queryString = $this->wire('input')->queryString();
            parse_str($queryString, $queryParams);

            // check if a code has been sent to the page via the remote email link
            if (array_key_exists('code', $queryParams)) {

                // sanitize the code to be a string and has a length of 120 characters
                $code = $this->wire('sanitizer')->string120($queryParams['code']);

                // get the comments table
                $table = $this->database->escapeTable($this->field->table);

                // check if the code exists inside the table
                $comment = $this->comments->get('code=' . $code);
                if ($comment) {
                    $oldStatus = $comment->status;
                    $notification = $comment->notification;

                    switch (true) {
                        // Status change via remote link of an email
                        case (array_key_exists('status', $queryParams)):

                            // check if the value is only '1' (approved) or '2' (spam)
                            $newStatus = in_array($queryParams['status'], ['1', '2']) ? $queryParams['status'] : null;

                            if (!is_null($newStatus)) {

                                // sanitize $newStatus to be integer
                                $newStatus = (int)($newStatus);

                                // check if comment has remote_flag = 0, which means the status has not been changed before
                                if ($comment->remote_flag === 0) {

                                    // if status is 2 - check if comment has children (replies)
                                    if (($newStatus === 2) && ($comment->getReplies()->count)) {
                                        // comment has replies -> set status to 3
                                        $newStatus = 3;
                                    }

                                    $sql = "UPDATE $table SET status=:status, remote_flag=:remote_flag WHERE id=:id";

                                    // try to save the data to the database
                                    try {

                                        $query = $this->database->prepare($sql);
                                        $query->bindValue(":status", $newStatus, PDO::PARAM_INT);
                                        $query->bindValue(":remote_flag", 1, PDO::PARAM_INT);
                                        $query->bindValue(":id", $comment->id, PDO::PARAM_INT);

                                        // execute the query to save the comment in the database
                                        if ($query->execute()) {

                                            // set the change values (status old, status new, comment id) to a session variable
                                            $this->wire('session')->set('remote', ['success' => '1', 'newstatus' => (string)$newStatus, 'oldstatus' => (string)$oldStatus, 'commentid' => (string)$comment->id]);

                                            // make a redirect to show or hide the comment after the status has been changed
                                            $this->wire('session')->redirect('.');
                                        }
                                    } catch (Exception $e) {
                                        $this->log($e->getMessage()); // log the error message
                                        $this->wire('session')->set('remote', ['success' => 0]);
                                    }

                                } else {
                                    $this->alert->setCSSClass('alert_dangerClass');
                                    $this->alert->setText($this->_('The status of the comment has already been changed. For security reasons, however, this is only allowed once via mail link. If you want to change the status once more, you have to login to the backend.'));
                                }
                            }
                            break;

                        // User do not want to get further mails about replies
                        case (array_key_exists('notification', $queryParams)):
                            if ($notification == 0) {
                                $this->wire('session')->set('notifystatuschange', '1');
                            } else {
                                // 2) change status of mail notification to 0, which means to not send notification mails in the future
                                $sql = "UPDATE $table SET notification=:notification WHERE code=:code";
                                // save the data to the database
                                try {
                                    $query = $this->database->prepare($sql);
                                    $query->bindValue(":notification", 0, PDO::PARAM_INT);
                                    $query->bindValue(":code", $code, PDO::PARAM_STR);
                                    // execute the query to save the comment in the database
                                    if ($query->execute()) {
                                        // set the status of the new comment to a session variable for later output of the success message
                                        $this->wire('session')->set('notifystatuschange', '0');
                                    } else {
                                        $this->wire('session')->set('notifystatuschange', '3');
                                    }
                                } catch (Exception $e) {
                                    $this->log($e->getMessage()); // log the error message
                                    $this->wire('session')->set('notifystatuschange', 3); // 3 stands for error
                                }
                            }
                            break;

                    }

                } else {
                    // code was not found
                    $this->wire('session')->set('nocodefound', 1);
                }
            }
        }

        /**
         * Render the comment form on the frontend
         * Includes the form markup and the isValid() method
         * @return string
         * @throws WireException
         */
        public function ___render(): string
        {

            // change some values inside the database according to the query strings inside the remote mail links
            $this->processRemoteLink();

            // add privacy notice if set
            $privacyType = 1;
            if (array_key_exists('input_privacy_show', $this->frontendCommentsConfig)) {
                $privacyType = (int)$this->frontendCommentsConfig['input_privacy_show'];
            }

            // create and add the privacy notice type
            switch ($privacyType) {
                case(1): // checkbox has been selected
                    // remove PrivacyText element
                    $this->remove($this->privacyText);
                    break;
                case(2): // text only has been selected
                    // remove Privacy element
                    $this->remove($this->privacy);
                    break;
                default: // show none of them has been selected
                    // remove both
                    $this->remove($this->privacyText);
                    $this->remove($this->privacy);
            }

            if ($this->___isValid()) {

                // get the name of the comment field
                $fieldName = $this->field->name;

                // create an array for saving the data to the database
                $values = [];

                foreach ($this->getValues() as $name => $value) {
                    $name = str_replace($this->getID() . '-', '', $name);
                    if ($name === "text") {
                        $database_name = "data";
                    } else {
                        $database_name = $name;
                    }

                    // check if a column with this name exists inside the database and add the name to the array
                    if ($this->database->columnExists('field_' . $fieldName, $database_name)) {
                        $values[$database_name] = $value;
                    }
                }

                // create the new comment instance
                $newComment = $this->wire(new Comment($values, $this->comments));

                // add some additional data to it, that do not come from the form, but also should be stored in the db
                $newComment->pages_id = $this->page->id; // set the page id
                $newComment->user_id = $this->wire('user')->id; // set the user id
                $newComment->ip = $this->wire('session')->getIP(); // get the IP address of the user
                $newComment->user_agent = $_SERVER['HTTP_USER_AGENT']; // get the user agent header
                $newComment->sort = count($this->comments) + 1; // increase the sort
                $newComment->created = time(); // set the current timestamp
                // create random codes for remote links inside emails
                $random = new WireRandom();
                $newComment->code = $random->alphanumeric(120);
                $newComment->status = $this->setStatus($newComment);

                // add the new comment to the existing Comment WireArray
                if ($this->comments->add($newComment)) {

                    $fieldtypeMulti = $this->wire('fieldtypes')->get('FrontendComments');

                    // save the whole CommentArray (including the new comment) to the database
                    if ($fieldtypeMulti->___savePageField($this->page, $this->field)) {

                        $this->wire('session')->set('comment', 'saved');
                        // set status session
                        $this->wire('session')->set('commentstatus', (string)$newComment->status);

                        // send the notification email to the moderators

                        $values = $this->getValues();

                        // overwrite notification status if it is present inside the array
                        if (array_key_exists($fieldName . '-notification', $values)) {
                            $notificationText = [
                                $this->_('No'),
                                $this->_('Yes')
                            ];
                            $notificationValue = $values[$fieldName . '-notification'];
                            $values[$fieldName . '-notification'] = $notificationText[$notificationValue];
                        }

                        // overwrite stars status if it is present inside the array
                        if (array_key_exists($fieldName . '-rating', $values)) {
                            $ratingValue = $values[$fieldName . '-notification'];
                            $values[$fieldName . '-notification'] = $ratingValue - ' ' . $this->_n('Star', 'Stars', $ratingValue);
                        }

                        if ($this->notifications->sendNotificationAboutNewComment($values, $newComment, $this)) {
                            $this->writeEntryInQueueTable($newComment);
                        } else {
                            $this->generateEmailSentErrorAlert();
                        }

                    }

                }

            }

            // output an info message if the code for change was not found
            if ($this->wire('session')->get('nocodefound')) {
                // there was an error
                $this->alert->setCSSClass('alert_dangerClass');
                $this->alert->setText($this->_('We are sorry, but there was no comment with this code found in the database.'));
                $this->wire('session')->remove('nocodefound');
            }

            // output an info message depending on the session array values set
            $remoteValues = $this->wire('session')->get('remote');
            if ($remoteValues) {

                // remove the session first
                $this->wire('session')->remove('remote');

                $success = (array_key_exists('success', $remoteValues)) ? (int)$remoteValues['success'] : null;
                $newStatus = (array_key_exists('newstatus', $remoteValues)) ? (int)$remoteValues['newstatus'] : null;
                $oldStatus = (array_key_exists('oldstatus', $remoteValues)) ? (int)$remoteValues['oldstatus'] : null;
                $commentId = (array_key_exists('commentid', $remoteValues)) ? (int)$remoteValues['commentid'] : null;
                $comment = $this->comments->find('id=' . $commentId)->first(); // get the comment object

                if ($success) {

                    // get the status names array
                    $statusTypes = InputfieldFrontendComments::statusTexts();

                    // send notification mail to the user, that the status has been changed
                    $this->notifications->sendStatusChangeEmail($comment, $this->field, $this->frontendFormsConfig);

                    // status is approved -> create the "to the comment" jump link
                    $jumpLink = '';
                    if ($newStatus === InputfieldFrontendComments::approved) {
                        $jumpLink = ' (<a href="#comment-' . $commentId . '" title="' . $this->_('Directly to the comment') . '">' .
                            $this->_('To the comment') . '</a>)';
                    }
                    // output success message that the status has been changed (either to approved or to SPAM)
                    $this->alert->setCSSClass('alert_successClass');
                    $this->alert->setText(sprintf($this->_('Comment status has been changed from "%s" to "%s". %s'),
                        $statusTypes[$oldStatus], $statusTypes[$newStatus], $jumpLink));
                } else {
                    // there was an error
                    $this->alert->setCSSClass('alert_dangerClass');
                    $this->alert->setText($this->_('An error occured during the saving of the new comment status.'));
                }
            }

            // output an info message if the notification email status has been changed via the mail link
            $notifyChange = $this->wire('session')->get('notifystatuschange');
            if ($notifyChange == '0') {

                // remove the session first
                $this->wire('session')->remove('notifystatuschange');

                // output success message that the notification status has been changed to 0 (no notification)
                $this->alert->setCSSClass('alert_successClass');
                $this->alert->setText($this->_('You have successfully unsubscribed from email notifications and you will no longer receive notifications of new replies.'));

            } else if ($notifyChange == '1') {
                // the notification status is 0
                $this->alert->setCSSClass('alert_warningClass');
                $this->alert->setText($this->_('You have tried to unsubscribe from getting notifications, but your notification status is already disabled.'));
            }

            // Output a success message if a comment has been submitted successfully
            if (($this->wire('session')->get('comment') == 'ready') && ($this->getID() == $this->field->name)) {
                $this->wire('session')->remove('comment');

                $this->alert->setCSSClass('alert_successClass');
                // output the success message if the comment has been submitted successfully depending on config settings
                $status = $this->wire('session')->get('commentstatus');
                if ($status == '1') {
                    // create the "to the comment" jump link
                    $jumpLink = '';
                    if (!is_null($this->comments->last())) {
                        $jumpLink = '(<a href="#comment-' . $this->comments->last()->id . '" title="' . $this->_('Directly to the comment') . '">' .
                            $this->_('To the comment') . '</a>)';
                    }
                    $this->alert->setText(sprintf($this->_('Thank you! Your comment has been submitted successfully. %s'), $jumpLink));
                } else {
                    $this->alert->setText($this->_('Thank you for your comment. Please be patient. Your comment has been submitted successfully and is waiting for approval.'));
                }
                $this->wire('session')->remove('commentstatus');
            }

            // output the form
            $out = '<div id="' . $this->getID() . '-form-wrapper">';
            $out .= parent::render();
            // add the cancel link only after a reply form
            if ($this->parent_id != '0') {
                $out .= $this->cancel->___render();
            }
            $out .= '</div>';
            return $out;

        }

        /**
         * If a new comment has been posted, write all notification emails in the database for queued sending
         * @param \FrontendComments\Comment $newComment
         * @return void
         * @throws \ProcessWire\WireException
         */
        protected function writeEntryInQueueTable(Comment $newComment)
        {
            if ((array_key_exists('input_fc_comment_notification', $this->frontendCommentsConfig)) && ($this->frontendCommentsConfig['input_fc_comment_notification'] > 0)) {
                //check if this is a reply or a new comment
                $reply = !$this->parent_id == 0; // true or false

                $notificationEmails = [];

                if ($reply) {

                    // get the id of the current saved comment inside the comment field table
                    $table = $this->database->escapeTable($this->field->table);
                    $statement = "SELECT max(id) AS `lastid`FROM $table";

                    try {
                        $query = $this->database->prepare($statement);
                        $query->execute();
                        $row = $query->fetchAll();
                        $last_comment_id = $row[0]['lastid'];
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }

                    // find all other users which have chosen to get informed about all replies
                    $commenters = $this->comments->find('notification=' . Comment::flagNotifyAll);
                    // get all email addresses to this comments
                    foreach ($commenters as $comments) {
                        $notificationEmails[$comments->id] = $comments->email;
                    }

                    // get the commenter which is the parent of this comment and check if notification is enabled
                    $parentcomment = $this->comments->find('id=' . $this->parent_id)->first();

                    if ($parentcomment->notification == Comment::flagNotifyReply) {
                        $notificationEmails[$parentcomment->id] = $parentcomment->email;

                    }

                }

                // remove the email address of the current commenter
                $notificationEmails = array_diff($notificationEmails, [$newComment->email]);

                // remove double entries if present
                $notificationEmails = array_unique($notificationEmails);

                if ((count($notificationEmails)) && (!is_null(($last_comment_id)))) {

                    // write all receivers into the queue table for later sending of emails
                    $table = 'field_' . $this->field->name . '_queues'; // table name

                    // create comment_id, email array
                    $sendingData = [];
                    foreach ($notificationEmails as $id => $email) {
                        $sendingData[] = '(' . $id . ',' . $last_comment_id . ',\'' . $email . '\', ' . $this->field->id . ', ' . $this->page->id . ')';
                    }
                    $valuesString = 'VALUES' . implode(',', $sendingData);

                    // create the SQL statement
                    $statement = "INSERT INTO $table (parent_id, comment_id, email, field_id, page_id) $valuesString";

                    $this->wire('database')->exec($statement);

                    // create a session to stop the simultaneous sending of notification via Lazy Cron during the saving process
                    $this->wire('session')->set('stopqueue', '1');

                }
            }
        }

        /**
         * @return string
         * @throws WireException
         */
        public function __toString(): string
        {
            return $this->___render();
        }

    }
