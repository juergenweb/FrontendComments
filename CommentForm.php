<?php
    declare(strict_types=1);

    /*
     * File for creating, saving and rendering the comment form
     * and to change the status of a comment via link from the mail
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
     * @property protected InputNumber $stars: the field object for the star rating number field
     * @property protected InputRadioMultiple $notify: the field object for email notification about new comments
     * @property protected Privacy $privacy: the field object for the privacy field
     * @property protected InputHidden $pageid: the field object for the hidden pageid field
     * @property protected InputHidden $parentid: the field object for the hidden parentid field
     * @property protected Alert $alert: The alert object
     * @property protected Link $cancel: the link object to cancel the reply comment
     * @property protected string|int $parent_id: the id of the parent comment
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
    use FrontendForms\InputNumber;
    use FrontendForms\InputText;
    use FrontendForms\Textarea;
    use FrontendForms\InputRadioMultiple;
    use FrontendForms\Privacy;
    use FrontendForms\Button;
    use FrontendForms\Link;
    use FrontendForms\Alert;
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

        protected array $statusTexts = [];
        protected array $frontendFormsConfig = [];
        protected array $frontendCommentsConfig = [];
        protected string $redirectUrl = '';
        protected string|int $parent_id = 0;

        /** class objects */
        protected Email $email; // the email field object
        protected InputText $author; // the author field object
        protected Textarea $comment; // the comment text field object
        protected InputNumber $stars; // the number field for star rating
        protected InputRadioMultiple $notify; // the notify me about new comments field object
        protected Privacy $privacy; // the accept privacy checkbox object
        protected Button $button; // the submit button object
        protected InputHidden $pageId; // the hidden page id input object
        protected InputHidden $parentId; // the hidden parent page id input object
        protected CommentArray $comments; // the array containing all comments of this page
        protected Page $page; // the page where the form is embedded/displayed
        protected Field $field; // the field of the FrontendComments Fieldtype
        protected WireDatabasePDO $database; // the ProcessWire database object
        protected Alert $alert; // The alert object of the form for further manipulations
        protected Link $cancel; // The cancel link object for replies

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

            // generate statusTexts array
            $this->statusTexts = [
                $this->_('pending approval'),
                $this->_('approved'),
                $this->_('SPAM')
            ];

            // grab configuration values from the FrontendForms module
            $this->frontendFormsConfig = $this->getFrontendFormsConfigValues();

            // grab configuration values from the FrontendComments input field
            $this->frontendCommentsConfig = $this->getFrontendCommentsInputfieldConfigValues();

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
            if (!array_key_exists('input_fc_counter', $this->frontendCommentsConfig)) {
                $this->comment->useCharacterCounter();
            }
            $this->comment->setSanitizer('maxLength'); // limit the length of the comment
            $this->comment->setNotes($this->_('HTML is not allowed.'));
            $this->add($this->comment);

            // 4) star rating
            if (array_key_exists('input_fc_stars', $this->frontendCommentsConfig) && $this->frontendCommentsConfig['input_fc_stars'] === 1) {
                $this->stars = new InputNumber('stars');
                $this->stars->useInputWrapper(false);
                $this->stars->useFieldWrapper(false);
                $this->stars->setLabel($this->_('Rating'));
                $this->stars->setAttribute('max', 5);
                $this->stars->setAttribute('hidden');
                $this->stars->setAttribute('readonly'); // set read only by default, which means no vote
                $this->stars->setAttribute('class', 'rating-value');
                // add the post value of the star rating to the star rating render function after form submission
                $number = ($_POST) ? $_POST[$this->field->name . '-stars'] : '0';
                $this->stars->prepend(self:: ___renderStarRating((float)$number, true, $this->getID()));
                $this->add($this->stars);
            }

            // 5) email notification about new comments
            if (array_key_exists('input_fc_comment_notification', $this->frontendCommentsConfig) && ($this->frontendCommentsConfig['input_fc_comment_notification'] !== 0)) {
                $this->notify = new InputRadioMultiple('notification');
                $this->notify->setlabel($this->_('Notify me about new comments'));
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

            // 6) privacy checkbox
            $this->privacy = new Privacy('privacy');
            $this->add($this->privacy);

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
            $this->cancel->wrap();
            $this->cancel->setUrl($this->page->url);

            // set the alert object for further manipulations later on
            $this->alert = $this->getAlert();

            // CAPTCHA settings
            // add FrontendForms settings for the CAPTCHA if "inherit" has been chosen
            if ($this->frontendCommentsConfig['input_fc_captcha'] === 'inherit') {
                $this->setCaptchaType($this->frontendFormsConfig['input_captchaType']);
            } else {
                // add individual CAPTCHA settings from this module
                $this->setCaptchaType($this->frontendCommentsConfig['input_fc_captcha']);
            }

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
         * Render the star rating
         * Outputs a line of 5 stars including empty, half or full colored stars - depending on the value entered
         * @param float|null $number - the number of colored stars
         * @param string|null $id - add unique id depending on the comment or the form
         * @param bool $rating - if true, an additional class will be added which can be fetched via JavaScript
         * @return string
         */
        public static function ___renderStarRating(float|null $number = 0, bool $rating = false, string|null $id = null): string
        {
            // add classes depending on settings
            if ($rating) {
                $class = 'rating vote';
            } else {
                $class = 'rating';
            }

            // add id depending on settings
            $id_attr = $id_ratingtext = $id_ratingstar = '';
            if (!is_null($id)) {
                $id_attr = ' id="' . $id . '-rating"';
                $id_ratingtext = ' id="' . $id . '-ratingtext"';
                $id_ratingstar = $id . '-ratingstar';
            }
            $out = '<div' . $id_attr . ' class="' . $class . '">';

            // round to int down
            if (!is_null(($number))) {
                $number = self::roundToHalfStepNumber($number);
            } else {
                $number = 0;
            }

            $votingTextDefault = $votingText = _('n/a');
            if ($number > 0) {
                $votingText = $number . '/5';
            }
            if ($rating) {
                $out .= '<span' . $id_ratingtext . ' class="rating__result" data-unvoted="' . $votingTextDefault . '">' . $votingText . '</span>';
            } else {
                $out .= '<span' . $id_ratingtext . ' class="rating__result">' . $votingText . '</span>';
            }

            // no stars
            if ($number < 0.5) {
                // 5 empty stars
                for ($x = 1; $x <= 5; $x++) {
                    $ratingstarid = '';
                    if ($id_ratingstar) {
                        $ratingstarid = ' id=' . $id_ratingstar . '-' . $x . '"';
                    }
                    $out .= '<i' . $ratingstarid . ' class="rating__star fa fa-star-o" data-value="' . $x . '" data-form_id="' . $id . '"></i>';
                }
            } else if ($number > 4.5) {
                // 5 full stars
                for ($x = 1; $x <= 5; $x++) {
                    $ratingstarid = '';
                    if ($id_ratingstar) {
                        $ratingstarid = ' id=' . $id_ratingstar . '-' . $x . '"';
                    }
                    $out .= '<i' . $ratingstarid . ' class="rating__star fa fa-star"></i>';
                }
            } else {
                // empty, half and full stars
                $add = 0;
                $repeats = round($number, 0, PHP_ROUND_HALF_DOWN);

                for ($x = 1; $x <= $repeats; $x++) {
                    $ratingstarid = '';
                    if ($id_ratingstar) {
                        $ratingstarid = ' id=' . $id_ratingstar . '-' . $x . '"';
                    }
                    $out .= '<i' . $ratingstarid . ' class="rating__star fa fa-star" data-value="' . $x . '" data-form_id="' . $id . '"></i>';
                }
                // check if there is a half-step
                if ($number - $repeats != 0) {
                    //there is a half star
                    $ratingstarid = '';
                    if ($id_ratingstar) {
                        $ratingstarid = ' id=' . $id_ratingstar . '-' . $x . '"';
                    }
                    $out .= '<i' . $ratingstarid . ' class="rating__star fa fa-star-half-o" data-value="' . $x . '" data-form_id="' . $id . '"></i>';
                    $add = 1;
                }
                // add last start until the number of 5 is reached
                $sum = $repeats + $add;
                if ($sum < 5) {
                    for ($x = 1; $x <= 5 - $sum; $x++) {
                        $out .= '<i class="rating__star fa fa-star-o" data-value="' . $x . '" data-form_id="' . $id . '"></i>';
                    }
                }
            }
            $out .= '</div>';

            //show reset link only if $rating is true
            if ($rating) {
                $resetlink_id = '';
                if ($id) {
                    $resetlink_id = ' id="resetlink-' . $id . '"';
                }
                // style attribute
                $style = ' style="display:none"';
                // remove display none attribute if star rating value is present
                if ($number > 0) {
                    $style = '';
                }
                $out .= '<div class="reset-rating"><a' . $resetlink_id . ' href="#" class="fc-resetlink" data-form_id="' . $id . '"' . $style . '>' . _('Reset rating') . '</a></div>';
            }
            return $out;
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
         * Render a button for the email template
         * This button will be used as the "Mark as Spam" button inside the email
         *
         * @param string $linktext
         * @param string $url
         * @param string $bgColor
         * @param string $linkColor
         * @param string $borderColor
         *
         * @return string
         */
        public static function renderButton(
            string $linktext,
            string $url,
            string $bgColor,
            string $linkColor,
            string $borderColor
        ): string
        {
            $out = '<table style="padding-top:20px">';
            $out .= '<tr><td><table><tr><td style="border-radius: 2px;background-color:' . $bgColor . ';">';
            $out .= '<a href="' . $url . '" style="padding: 8px 12px; border: 1px solid ' . $borderColor . ';border-radius: 2px;sans-serif;font-size: 14px; color: ' . $linkColor . ';text-decoration: none;font-weight:bold;display: inline-block;">';
            $out .= $linktext;
            $out .= '</a></td></tr></table></td></tr></table>';
            return $out;
        }

        /**
         * Create the body text for the notification email
         * This method creates the content markup of the email
         * @param array $values
         * @param Comment $newComment
         * @return string
         */
        protected function renderNotificationBody(array $values, Comment $newComment): string
        {
            // create the body for the email
            $body = '<h1>' . $this->_('A new comment has been submitted') . '</h1>';
            $body .= '<table>';
            foreach ($values as $fieldName => $value) {
                $fieldName = str_replace($this->getID() . '-', '', $fieldName);
                $body .= '<tr style="padding: 10px 0;border-bottom: 1px solid #000000;"><td style="font-weight:bold;">[[' . strtoupper($fieldName) . 'LABEL]]:&nbsp;</td><td>' . $value . '</td></tr>';
                $body .= '<tr><td colspan="2"><hr style="margin:0;height:0;border-top: 1px solid #f6f6f6"/></td></tr><tr>';
            }

            if ($newComment->status == InputfieldFrontendComments::approved) {
                $color = '#7BA428';
            } else {
                $color = '#FD953A';
            }
            $body .= '<tr style="padding: 10px 0;"><td style="font-weight:bold;white-space: nowrap">' . $this->_('Comment status') . ':&nbsp;</td><td><span style="padding:3px;display:inline-block;background:' . $color . ';color:#fff;">&nbsp;' . $this->statusTexts[$newComment->status] . '&nbsp;</span></td></tr>';
            $body .= '<tr><td colspan="2"><hr style="margin:0;height:0;border-top: 1px solid #f6f6f6;"/></td></tr><tr>';
            $body .= '<tr style="padding: 10px 0;"><td style="font-weight:bold;">[[CURRENTURLLABEL]]:&nbsp;</td><td>[[CURRENTURLVALUE]]</td></tr>';
            $body .= '<tr><td colspan="2"><hr style="margin:0;height:0;border-top: 1px solid #f6f6f6"/></td></tr><tr>';
            $body .= '<tr style="padding: 10px 0;"><td style="font-weight:bold;">[[CURRENTDATETIMELABEL]]:&nbsp;</td><td>[[CURRENTDATETIMEVALUE]]</td></tr>';
            $body .= '<tr><td colspan="2"><hr style="margin:0;height:0;border-top: 1px solid #f6f6f6"/></td></tr><tr>';
            $body .= '<tr style="padding: 10px 0;"><td style="font-weight:bold;">[[IPLABEL]]:&nbsp;</td><td>[[IPVALUE]]</td></tr>';
            $body .= '<tr><td colspan="2"><hr style="margin:0;height:0;border-top: 1px solid #f6f6f6"/></td></tr><tr>';
            $body .= '<tr style="padding: 10px 0;"><td style="font-weight:bold;">[[BROWSERLABEL]]:&nbsp;</td><td>[[BROWSERVALUE]]</td></tr>';
            $body .= '</table>';

            // create a link for approving the comment if the status has been set to 0
            if ($newComment->status == InputfieldFrontendComments::pendingApproval) {
                $url = $this->page->httpUrl . '?code=' . $newComment->code . '&status=1#' . $this->getID() . '-form-wrapper';
                $body .= self::renderButton($this->_('Publish the comment'), $url, '#7BA428', '#ffffff',
                    '#7BA428');
            }

            // create button to mark comment as SPAM
            $spamUrl = $this->page->httpUrl . '?code=' . $newComment->code . '&status=2#' . $this->getID() . '-form-wrapper';
            $body .= self::renderButton($this->_('Mark this comment as SPAM'), $spamUrl, '#ED2939',
                '#ffffff', '#ED2939');

            return $body;
        }

        /**
         * Create the body text for the comment notification email
         * This method creates the content markup of the email
         * @param array $values
         * @param Comment $newComment
         * @return string
         */
        protected function renderCommentNotificationBody(array $values, Comment $newComment): string
        {
            // create the body for the email
            $body = '<h1>' . $this->_('A new reply has been submitted') . '</h1>';
            $body .= '<p>' . $this->_('You are receiving this email because, you have chosen to get informed if a new reply has been posted.') . '</p>';
            $body .= '<h2>' . $this->_('New comment') . '</h2>';
            $body .= '<table style="width:100%;background-color:#dddddd;"><tr style="width:100%;"><td style="width:100%;"><table style="width:100%;"><tr style="width:100%;"><td style="width:100%;"><p style="margin:12px;">[[TEXTVALUE]]</p></td></tr></table></td></tr></table>';
            $body .= '<p>' . $this->_('Link to the page of the comment') . ': ' . $this->page->httpUrl . '</p>';
            $body .= '<p>' . $this->_('If you do not want to receive further mails about new comments, please click the link below') . '</p>';
            // create a link for canceling the receiving of further notifications
            $url = $this->page->httpUrl . '?code=' . $newComment->notifycode . '&notification=0#' . $this->getID() . '-form-wrapper';
            $body .= self::renderButton($this->_('Stop sending me further notification mails about new comments'), $url, '#ED2939', '#ffffff',
                '#7BA428');
            return $body;
        }

        /**
         * Save the new status of the comment to the database if querystring is used via email
         * @return void
         * @throws \ProcessWire\WireException
         */
        protected function changeStatusViaMail(): void
        {
            $code = $newStatus = null; // set default values

            // check if a code has been sent to the page via the email link to change the status
            $queryString = $this->wire('input')->queryString();
            parse_str($queryString, $queryParams);

            if (array_key_exists('code', $queryParams)) {
                $code = $this->wire('sanitizer')->string($queryParams['code']);
            }
            if (array_key_exists('status', $queryParams)) {
                $newStatus = $this->wire('sanitizer')->string($queryParams['status']);
            }

            if (!is_null($code) && (!is_null($newStatus))) {

                // get the comment with the given code from the query string
                $comment = $this->comments->get('code=' . $code);

                // check if comment exists and remote_flag is 0
                if ((!is_null($comment)) && ($comment->id) && ($comment->remote_flag == InputfieldFrontendComments::pendingApproval)) {

                    // save the new comment status and the remote_flag
                    $database = $this->wire()->database;
                    $table = $database->escapeTable($this->field->table);

                    $sql = "UPDATE ";
                    $sql .= "`$table` SET status=:status,";
                    $binds['status'] = (int)$newStatus;
                    $sql .= " remote_flag=:remote_flag";
                    $binds['remote_flag'] = 1;
                    $sql .= " WHERE id=:id"; // . (int) $value['id'];
                    $binds['id'] = (int)$comment->id;

                    // save the data to the database
                    try {

                        $query = $database->prepare($sql); // create the query string

                        // bind all values to the query string
                        foreach ($binds as $k => $v) {
                            $query->bindValue(":$k", $v);
                        }

                        // execute the query to save the comment in the database
                        if ($query->execute()) {
                            // set the status of the new comment to a session variable for later on
                            $this->wire('session')->set('commentstatuschange', $newStatus);
                        }

                    } catch (Exception $e) {
                        $this->log($e->getMessage()); // log the error message
                        $this->wire('session')->set('commentstatuschange', 3); // 3 stands for error
                    }
                } else {
                    $this->alert->setCSSClass('alert_dangerClass');
                    $this->alert->setText($this->_('The status of the comment has already been changed. For security reasons, however, this is only allowed once per link. If you want to change the status once more, you have to login to the backend.'));
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

            // change the status of the comment depending on code inside querystring of the mail link
            // this could be approved(1) or spam(2) - depending on the settings
            $this->changeStatusViaMail();

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

                // check if email notification on new comments is enabled
                if (array_key_exists('input_fc_comment_notification', $this->frontendCommentsConfig)) {
                    $newComment->notifycode = $random->alphanumeric(120);
                }

                // add the new comment to the existing Comment WireArray
                if ($this->comments->add($newComment)) {

                    $fieldtypeMulti = $this->wire('fieldtypes')->get('FrontendComments');


                    // save the whole CommentArray (including the new comment) to the database
                    if ($fieldtypeMulti->___savePageField($this->page, $this->field)) {

                        $this->wire('session')->set('comment', 'saved');
                        // set status session
                        $this->wire('session')->set('commentstatus', (string)$newComment->status);

                        // Send a notification email to the moderators
                        $mail = new WireMail();
                        // set the sender email address
                        if (array_key_exists('input_fc_email', $this->frontendCommentsConfig)) {
                            $emailSender = $this->frontendCommentsConfig['input_fc_email'];
                        } else {
                            $emailSender = $this->_('comment-notification') . '@' . $_SERVER["SERVER_NAME"];
                        }
                        $mail->from($emailSender);
                        // set from name if present
                        if (array_key_exists('input_fc_sender', $this->frontendCommentsConfig)) {
                            $mail->fromName($this->frontendCommentsConfig['input_fc_sender']);
                        }
                        // set subject
                        if (array_key_exists('input_fc_subject', $this->frontendCommentsConfig)) {
                            $subject = $this->frontendCommentsConfig['input_fc_subject'];
                        } else {
                            $subject = $this->_('Comment notification');
                        }
                        $mail->subject($subject);
                        // set title
                        if (array_key_exists('input_fc_title', $this->frontendCommentsConfig)) {
                            $title = $this->frontendCommentsConfig['input_fc_title'];
                        } else {
                            $title = $this->_('New comment has been submitted');
                        }
                        $mail->title($title);

                        // grab all form values from $_POST array
                        $values = $this->getValues();

                        // remove unnecessary values, which should not be sent via the notification mail
                        unset($values[$this->getID() . '-privacy']);
                        unset($values[$this->getID() . '-parent_id']);

                        // render the body string for the notification mail
                        $body = $this->renderNotificationBody($values, $newComment);

                        // set email template depending on config settings
                        $template = $this->frontendCommentsConfig['input_fc_emailTemplate'];
                        if ($template === 'inherit') {
                            $template = $this->frontendFormsConfig['input_emailTemplate'];
                        }
                        $mail->mailTemplate($template);
                        $mail->bodyHTML($body);
                        // set all receivers
                        $this->setMailTo($mail);

                        if (!$mail->send()) {
                            // output an error message if the mail could not be sent
                            $this->generateEmailSentErrorAlert();
                        } else {


                            $this->writeEntryInQueueTable($newComment);
                        }

                    }

                }

            }

            // output an info message if the comment status has been changed via the mail link
            $statusChange = $this->wire('session')->get('commentstatuschange');

            if ($statusChange) {

                // remove the session first
                $this->wire('session')->remove('commentstatuschange');
                $statusTypes = ['0', '1', '2'];
                if (in_array($statusChange, $statusTypes)) {
                    // output success message that the status has been changed (either to approved or to SPAM)
                    $this->alert->setCSSClass('alert_successClass');
                    $this->alert->setText(sprintf($this->_('Comment status has been changed to "%s".'),
                        $this->statusTexts[$statusChange]));
                } else {
                    // there was an error
                    $this->alert->setCSSClass('alert_dangerClass');
                    $this->alert->setText($this->_('Error saving new status of comment.'));
                }

            }

            if (($this->wire('session')->get('comment') == 'ready') && ($this->getID() == $this->field->name)) {
                $this->wire('session')->remove('comment');

                $this->alert->setCSSClass('alert_successClass');
                // output the success message if the comment has been submitted successfully depending on config settings
                $status = $this->wire('session')->get('commentstatus');
                if ($status == '1') {
                    $jumpLink = '';
                    if (!is_null($this->comments->last())) {
                        $jumpLink = '(<a href="#comment-' . $this->comments->last()->id . '" title="' . $this->_('Directly to the comment') . '">' . $this->_('To the comment') . '</a>)';
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
                        $notificationEmails[] = $comments->email;
                    }

                    // get the commenter which is the parent of this comment and check if notification is enabled
                    $parentcomment = $this->comments->find('id=' . $this->parent_id)->first();
                    //$this->wire('session')->set('rec', $parentcomment->notification);
                    if ($parentcomment->notification == Comment::flagNotifyReply) {
                        $notificationEmails[] = $parentcomment->email;

                    }

                }

                // remove the email address of the current commenter
                $notificationEmails = array_diff($notificationEmails, [$newComment->email]);

                // remove double entries if present
                $notificationEmails = array_unique($notificationEmails);

                if ((count($notificationEmails)) && (!is_null(($last_comment_id)))) {

                    // write all receivers into the queue table for later sending of emails
                    $table = 'field_' . $this->field->name . '_queues'; // table name

                    // create the id of the new comment by incrementing the last id
                    // TODO
                    $newID = (int)($last_comment_id) + 1;

                    // create comment_id, email array
                    $sendingData = [];
                    foreach ($notificationEmails as $email) {
                        $sendingData[] = '(' . $newID . ',\'' . $email . '\', ' . $this->field->id . ', ' . $this->page->id . ')';
                    }
                    $valuesString = 'VALUES' . implode(',', $sendingData);

                    // create the SQL statement
                    $statement = "INSERT INTO $table (comment_id, email, field_id, page_id) $valuesString";

                    $this->wire('database')->exec($statement);


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
