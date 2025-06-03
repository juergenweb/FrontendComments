<?php
    declare(strict_types=1);
    namespace FrontendComments;

    /*
     * Class for creating and rendering the comment form
     * and to change the status of a comment via the remote link from the mail
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: FrontendCommentForm.php
     * Created: 24.06.2023
     */

    use Exception;
    use FrontendForms\Email;
    use FrontendForms\Form;
    use FrontendForms\InputEmail;
    use FrontendForms\InputHidden;
    use FrontendForms\InputUrl;
    use FrontendForms\InputText;
    use FrontendForms\Select;
    use FrontendForms\Textarea;
    use FrontendForms\InputRadioMultiple;
    use FrontendForms\Privacy;
    use FrontendForms\PrivacyText;
    use FrontendForms\Button;
    use FrontendForms\ResetButton;
    use FrontendForms\Link;
    use FrontendForms\TextElements;
    use ProcessWire\FieldtypeFrontendComments;
    use ProcessWire\Page;
    use ProcessWire\Field;
    use ProcessWire\WireDatabasePDO;
    use ProcessWire\WireRandom;
    use PDO;

    class FrontendCommentForm extends Form
    {
        protected array $frontendFormsConfig = []; // array containing all FrontendForms config values
        protected int|null $privacyType = null; // the privacy setting of the FrontendCommentArray
        protected int $level = 0;

        /** class objects */
        protected Email $email; // the email field object
        protected InputText $author; // the author field object
        protected InputText $website; // the website of the author field object
        protected Textarea $comment; // the comment text field object
        protected Select $stars; // the number field for star rating
        protected InputRadioMultiple $notify; // "notify me about new comments" field object
        protected Privacy $privacy; // the accept privacy checkbox object
        protected PrivacyText $privacyText; // the accept privacy text object
        protected Button $button; // the submission button object
        protected ResetButton $resetButton; // the reset button object
        protected FrontendCommentArray $comments; // the array containing all comments of this page
        protected Page $page; // the page where the form is embedded/displayed
        protected Field $field; // the field of the FrontendComments Fieldtype
        protected Link $guidelines; // The guideline link object for the comments
        protected Notifications $notifications;
        protected WireDatabasePDO $database; // the ProcessWire database object
        public static array $ratingValues = [];

        /**
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         * @throws \Exception
         */
        public function __construct(FrontendCommentArray $comments, string $id = null, int $parentId = 0)
        {

            // set default values
            $this->comments = $comments; // the comment text object
            $this->page = $comments->getPage(); // the current page object, which contains the comment field
            $this->field = $comments->getField(); // Processwire comment field object
            $this->database = $this->wire('database'); // the database object

            self::$ratingValues = [
                1 => $this->_('Terrible'),
                2 => $this->_('Poor'),
                3 => $this->_('Average'),
                4 => $this->_('Very Good'),
                5 => $this->_('Excellent')
            ];

            // set the comment field name as id of the form if id is not present
            if ($id === null) {
                $id = $this->field->name;
            }

            parent::__construct($id);

            // TODO: set new values afterwards - only for dev purposes set to 0
            $this->setMaxAttempts(0);
            $this->setMinTime(0);
            $this->setMaxTime(0);

            // grab configuration values from the FrontendForms module
            $this->frontendFormsConfig = FieldtypeFrontendComments::getFrontendFormsConfigValues();

            // Set the form headline content
            $this->field->set('input_fc_form_headline', FieldtypeFrontendComments::getFieldConfigLangValue($this->field, 'input_fc_form_headline') ?? $this->_('Leave a comment'));

            // Create the form elements

            // 1) email field
            $this->email = new Email('email');
            $this->add($this->getEmailField());

            // 2) author field
            $this->author = new InputText('author');
            $this->add($this->getAuthorField());

            // 3) website field
            $this->website = new InputUrl('website');
            $this->add($this->getWebsiteField());

            // 4) comment field
            $this->comment = new Textarea('text');
            $this->add($this->getCommentField());

            // 5) star rating
            $this->stars = new Select('stars');
            $this->add($this->getStarRating());

            // 6) privacy hint (text or checkbox)
            // create all privacy objects and add them to the form object
            $this->privacy = new Privacy('privacy');
            $this->add($this->privacy);
            $this->privacyText = new PrivacyText('privacy-text');
            $this->add($this->privacyText);

            // 7) email notification field
            $this->notify = new InputRadioMultiple('notification');
            $this->add($this->getNotifyField());

            // 8) submit button
            $this->button = new Button('submit');
            $this->add($this->getSubmitButton());

            // 9) reset button
            $this->resetButton = new ResetButton();
            $this->add($this->getResetButton());

            // Set the CAPTCHA type
            $captcha = $this->field->get('input_fc_captcha');
            if ($captcha === 'inherit') {
                $captcha = $this->frontendFormsConfig['input_captchaType'];
            }
            $this->setCaptchaType($captcha);

            // Hidden field for parent id
            $hiddenparentId = new InputHidden('parent-id');
            $hiddenparentId->setAttribute('value', $parentId);
            $this->add($hiddenparentId);

            // instantiate the Notifications object for creating the mail body text
            require_once(__DIR__ . '/Notifications.php');
            $this->notifications = new Notifications($this->comments, $this->field, $this->page);

        }

        /**
         * Set the level where the form belongs to
         * Will be needed to distinguish between root form and reply form
         * @param int $level
         * @return self
         */
        public function setLevel(int $level): self
        {
            $this->level = $level;
            return $this;
        }

        /**
         * Get the headline object
         * @return \FrontendForms\TextElements
         */
        public function ___getHeadline(): TextElements
        {
            $headline = new TextElements();
            $headline->setContent($this->field->get('input_fc_form_headline'));
            $headline->setTag($this->field->get('input_fc_form_tag_headline'));
            return $headline;
        }

        /**
         * Get the email field object
         * @return \FrontendForms\InputEmail
         */
        public function ___getEmailField(): InputEmail
        {
            if ($this->user->isLoggedin()) {
                $this->email->setDefaultValue($this->user->email);
                $this->email->setAttribute('readonly');
            }
            $this->email->setRule('lengthMax', 255)->setCustomFieldName('The email address'); // DB storage maximum length
            return $this->email;
        }

        /**
         * Get the author field object
         * @return \FrontendForms\InputText
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        public function ___getAuthorField(): InputText
        {
            $this->author->setLabel($this->_('Name'));
            if ($this->user->isLoggedin()) {

                $authorFieldValue = '';

                if ($this->field->get('input_fc_author') !== 'none') {
                    $nameField = ($this->field->get('input_fc_author') === 'name') ? 'name' : $this->wire('fields')->get($this->field->get('input_fc_author'))->name;
                    $authorFieldValue = $this->user->$nameField;
                }

                if ($authorFieldValue) {
                    $this->author->setAttribute('readonly');
                    $this->author->setAttribute('value', $authorFieldValue);
                }

            }
            $this->author->setRule('required');
            $this->author->setRule('firstAndLastname');
            $this->author->setRule('lengthMax', 128)->setCustomFieldName('The name'); // DB storage maximum length
            return $this->author;
        }

        /**
         * Get the website field object
         * @return \FrontendForms\InputUrl
         */
        public function ___getWebsiteField(): InputUrl
        {
            $this->website->setLabel($this->_('Homepage'));
            $this->website->setRule('lengthMax', 255); // DB storage maximum length
            return $this->website;
        }

        /**
         * Get the comment field object
         * @return \FrontendForms\Textarea
         * @throws \Exception
         */
        public function ___getCommentField(): Textarea
        {
            $this->comment->setLabel($this->_('Comment'));
            $this->comment->setRule('required');
            $this->comment->setRule('lengthMax', 1024)->setCustomFieldName('The comment');
            $this->comment->setSanitizer('maxLength'); // limit the length of the comment
            $this->comment->setNotes($this->_('HTML is not allowed.'));
            return $this->comment;
        }

        /**
         * Get the star rating field object
         * @return \FrontendForms\Select
         */
        public function ___getStarRating(): Select
        {
            
            $this->stars->useInputWrapper(false);
            $this->stars->useFieldWrapper(false);
            $this->stars->setLabel($this->_('Rating'));
            $this->stars->addOption($this->_('Select a rating'), '');
            $this->stars->addOption(self::$ratingValues[5], '5');
            $this->stars->addOption(self::$ratingValues[4], '4');
            $this->stars->addOption(self::$ratingValues[3], '3');
            $this->stars->addOption(self::$ratingValues[2], '2');
            $this->stars->addOption(self::$ratingValues[1], '1');
            $this->stars->setAttribute('class', 'star-rating');

            // create data-options string
            $options = [];
            // add translatable string for the default "Select a rating" text
            $tooltip = $this->_('Select a rating');

            if ($this->field->get('input_fc_showtooltip')) {
                // disable tooltip
                $tooltip = false;
            }
            // set tooltip option depending on the settings
            $options[] = '&quot;tooltip&quot;:&quot;' . $tooltip . '&quot;';
            // set clear-able to true
            $options[] = '&quot;clearable&quot;:true';

            if ($options) {
                // create the data-options attribute
                $optionString = implode(',', $options);
                $this->stars->setAttribute('data-options', '{' . $optionString . '}');
            }
            return $this->stars;
        }

        /**
         * Get the privacy field object
         * @return \FrontendForms\Privacy
         */
        public function ___getPrivacy(): Privacy
        {
            return $this->privacy;
        }

        /**
         * Get the privacy text field object
         * @return \FrontendForms\PrivacyText
         */
        public function ___getPrivacyText(): PrivacyText
        {
            return $this->privacyText;
        }

        /**
         * Get the notification field object
         * @return \FrontendForms\InputRadioMultiple
         * @throws \Exception
         */
        public function ___getNotifyField(): InputRadioMultiple
        {
            $this->notify->setlabel($this->_('Notify me about new comments'));
            $this->notify->setRule('required');
            $this->notify->setRule('integer');
            $this->notify->setNotes($this->_('You can cancel the receiving of notification emails everytime by clicking the link inside the notification email.'));
            $this->notify->setDefaultValue('0');
            $this->notify->addOption($this->_('No notification'), (string)FrontendComment::flagNotifyNone);
            $this->notify->addOption($this->_('I want to get notified about replies to this comment only'), (string)FrontendComment::flagNotifyReply);
            $this->notify->addOption($this->_('I want to get notified about every new comment'), (string)FrontendComment::flagNotifyAll);
            $this->notify->alignVertical();
            return $this->notify;
        }

        /**
         * Get the submission button object
         * @return \FrontendForms\Button
         */
        public function ___getSubmitButton(): Button
        {
            $this->button->setAttribute('class', 'fc-comment-button');
            $this->button->setAttribute('data-formid', $this->getID());
            return $this->button;
        }

        /**
         * Get the reset button object
         * @return \FrontendForms\ResetButton
         */
        public function ___getResetButton(): ResetButton
        {
            $this->resetButton->setAttribute('class', 'fc-comment-resetbutton');
            return $this->resetButton;
        }

        /**
         * Render the top headline depending on the settings
         * @return string
         */
        public function ___renderHeadline(): string
        {
            $out = '';
            $text = $this->field->get('input_fc_form_headline');
            if ($text !== 'none') {
                $headline = $this->getHeadline();
                $out = $headline->render();
            }
            return $out;
        }

        /**
         * Set if the privacy text should be displayed or not
         * @param int $privacyType
         * @return void
         */
        public function setPrivacyType(int $privacyType): void
        {
            $this->privacyType = $privacyType;
        }

        /**
         * Add or remove the star rating field depending on the settings
         * @return void
         */
        protected function setStarRatingField(): void
        {
            if (!$this->field->get('input_fc_stars')) {
                $this->remove($this->stars);
            } else {
                if ($this->field->get('input_fc_stars') == 2) {
                    $this->stars->setRule('required');
                }
            }
        }

        /**
         * Add/set or remove the privacy field depending on the settings
         * @return void
         */
        protected function setPrivacyField(): void
        {

            // create and add the privacy notice type
            switch ($this->privacyType) {
                case(1): // a checkbox has been selected
                    // remove PrivacyText element
                    $this->remove($this->privacyText);
                    break;
                case(2): // text only has been selected
                    //  to remove the Privacy element;
                    $this->remove($this->privacy);
                    break;
                default: // show none of them has been selected
                    //  to remove both
                    $this->remove($this->privacyText);
                    $this->remove($this->privacy);
            }
        }

        /** Add a URL value to the website field if set in the config
         *
         * @return void
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function setWebsiteFieldValues(): void
        {
            if ($this->user->isLoggedin()) {
                $websiteFieldValue = '';
                // get the value of the website field if set
                if ($this->field->get('input_fc_website') && $this->field->get('input_fc_website') !== 'none') {
                    $urlField = $this->wire('fields')->get($this->field->get('input_fc_website'))->name;
                    $websiteFieldValue = $this->user->$urlField;
                }
                if ($websiteFieldValue) {
                    $this->website->setAttribute('readonly');
                    $this->website->setAttribute('value', $websiteFieldValue);
                }
            }
        }

        /**
         * Add or remove the website field
         *
         * @return void
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function setWebsiteField(): void
        {
            if ($this->field->get('input_fc_showWebsite')) {
                $this->setWebsiteFieldValues(); // show a website field and add pre-defined values
            } else {
                $this->remove($this->website);// remove the website field if set
            }
        }

        /**
         * Get the comment status depending on the setting
         * 0 = posted immediately
         * 1 = must be approved by a moderator
         * 2 = only comments of a new commenter needs to be approved by a moderator
         * @param string $email
         * @return int
         */
        protected function getCommentStatusForStorage(string $email): int
        {

            $status = 0; // default is not published
            switch ($this->field->get('input_fc_moderate')) {
                case 0:
                    $status = 1; // set status to publish
                    break;
                case 2:
                    // check if a user with the given mail address has posted a published comment in the past
                    $commenter = $this->comments->find('email=' . $email . ',status=1');
                    if ($commenter->count()) {
                        $status = 1; // set status to publish
                    }
                    break;
            }
            return $status;
        }

        /**
         * Remove options for the reply notification depending on the settings
         * @return array
         */
        protected function setCommentNotificationField(): array
        {

            $levels = $this->field->get('input_fc_reply_depth');
            $replyField = $this->getFormelementByName('notification');

            $allowedValues = [];
            switch ($this->field->get('input_fc_comment_notification')) {
                case 0:
                    // remove the reply fields completely
                    $this->remove($replyField);
                    break;
                case(1):
                    // remove field with value 2
                    if (!$levels) {
                        $this->remove($replyField);
                    } else {
                        $replyField->getOptionByValue(1)->getLabel()->setContent($this->_('Notify me about replies to this comment'));
                        $replyField->removeOptionByValue(2);
                    }
                    $allowedValues = ['0', '1'];
                    break;
                case (2):
                    // check if replies are activated (check for level)
                    if (!$levels) {
                        $replyField->removeOptionByValue(1);
                        $allowedValues = ['0', '2'];
                    } else {
                        $allowedValues = ['0', '1', '2'];
                    }
                    break;
            }
            return $allowedValues;
        }

        /**
         * Get the url to the community guidelines
         * @param \FrontendForms\Link $link
         * @return bool|string
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function getCommunityGuidelinesURL(Link $link): bool|string
        {

            $type = $this->field->get('input_guidelines_type');
            if ($type === 0)
                return false;
            if ($type === 1) { // internal page
                $page_id = $this->field->get('input_fc_internalPage')[0];
                $url = $this->wire('pages')->get($page_id)->url;
            } else { // external page
                $url = $this->field->get('input_fc_externalPage');
                $link->setAttribute('rel', 'nofollow');
            }
            return $url;
        }

        /**
         * Render the comment form
         * @return string
         * @throws \ProcessWire\WireException
         */
        public function render(): string
        {

            $this->setStarRatingField();
            $this->setPrivacyField();
            $this->setWebsiteField();

            // Guideline Link
            $this->guidelines = new Link('guidelines');
            $this->guidelines->setAttribute('class', 'fc-guidelines-link');
            $this->guidelines->setLinkText($this->_('community guidelines'));
            $this->guidelines->setAttribute('target', '_blank');

            // add the guideline link if set
            $guidelines_url = $this->getCommunityGuidelinesURL($this->guidelines);
            if ($guidelines_url) { // set the url of the guideline link
                $this->guidelines->setUrl($guidelines_url);
                $link = $this->guidelines->render();
                $notesText = $this->getFormelementByName('text')->getNotes()->getContent();
                $this->getFormelementByName('text')->getNotes()->setContent($notesText . ' ' . sprintf($this->_('Please take care about our %s.'), $link));
            }

            // add in array validator for email notification field -> checks for allowed values
            $allowedValues = $this->setCommentNotificationField();
            if ($allowedValues)
                $this->notify->setRule('in', $allowedValues);

            // set default form validation status
            $valid = false;

            if ($this->isValid()) {
                $valid = true;

                // get the name of the comment field
                $fieldName = $this->field->name;

                // create an array for saving the data to the database
                $values = [];

                foreach ($this->getValues() as $name => $value) {
                    $name = str_replace($this->getID() . '-', '', $name);

                    if ($name === "text") {
                        $database_name = "data";
                    } else if ($name === "parent-id") {
                        $database_name = "parent_id";
                    } else {
                        $database_name = $name;
                    }
                    if ($name === 'stars')
                        $value = ($value == '') ? NULL : $value;

                    // check if a column with this name exists inside the database and add the name to the array
                    if ($this->database->columnExists('field_' . $fieldName, $database_name)) {
                        $values[$database_name] = $value;
                    }
                }

                // create the new comment instance
                $newComment = $this->wire(new FrontendComment($this->comments, $values, $this->frontendFormsConfig));
                $newComment->page = $this->page;
                $newComment->field = $this->field;

                // add additional values to it, which are not part of the post-values
                $newComment->pages_id = $this->page->id; // set the page id
                $newComment->user_id = $this->wire('user')->id; // set the user id
                $newComment->ip = $this->wire('session')->getIP(); // get the IP address of the user
                $newComment->user_agent = $_SERVER['HTTP_USER_AGENT']; // get the user agent header
                $newComment->sort = count($this->comments) + 1; // increase the sort
                $newComment->created = time(); // set the current timestamp
                $newComment->status = $this->getCommentStatusForStorage($newComment->get('email')); // set the status
                // create random codes for remote links inside emails
                $random = new WireRandom();
                $newComment->code = $random->alphanumeric(120);

                // add the new comment to the existing Comment WireArray and save it to the database
                if ($this->comments->add($newComment)) {

                    $fieldtypeMulti = $this->wire('fieldtypes')->get('FrontendComments');

                    // save the whole CommentArray (including the new comment) to the database
                    if ($fieldtypeMulti->savePageField($this->page, $this->field)) {

                        // create a success message text depending on status
                        $successMsg = $this->_('Thank you for your comment!') . '<br>';

                        switch ($newComment->status) {
                            case 0:
                                $successMsg .= $this->_('Please be patient. Your comment must be approved by a moderator before it will be published on the page.');

                                if ($this->field->get('input_fc_status_change_notification')) {
                                    $successMsg .= '<br>' . $this->_('You will be notified by email as soon as your comment has been reviewed by a moderator.');
                                }
                                break;
                            case 1:
                                $reloadLink = new Link();
                                $commentID = $this->wire('database')->lastInsertId('field_' . $this->field->get('name'));
                                $reloadLink->setUrl($this->wire('input')->url);
                                $reloadLink->setQueryString('comment-redirect=' . $commentID);
                                $reloadLink->setAnchor($this->field->name . '-' . $this->page->id . '-redirect-alert');
                                $reloadLink->setLinkText($this->_('Reload the page'));
                                $link = $reloadLink->render();
                                $successMsg .= $this->_('To view your comment, you must reload the page by clicking on the link below:') . '<br>';
                                $successMsg .= $link;
                                break;

                        }
                        // set the message text to the alert
                        $this->getAlert()->setContent($successMsg);

                        //Send notification mail to all moderators if a new comment has been posted
                        $this->notifications->sendModerationNotificationMail($values, $newComment, $this);

                        // write entries in the queue table if the status is 1 (approved)
                        if ($newComment->status === FieldtypeFrontendComments::approved) {

                            // get the id of the current saved comment inside the comment field table
                            $table = $this->database->escapeTable($this->field->get('table'));
                            $statement = "SELECT max(id) AS `lastid`FROM $table";

                            try {
                                $query = $this->database->prepare($statement);
                                $query->execute();
                                $row = $query->fetchAll();
                                $last_comment_id = $row[0]['lastid'];
                                $newComment->id = $last_comment_id;

                                FieldtypeFrontendComments::writeEntryInQueueTable($newComment, $this->page, $this->field);
                                $this->wire('session')->set('stopqueue', '1'); // this is to prevent long execution times (sending mails and writing in the database at the same time)
                            } catch (Exception) {
                                // not used at the moment
                            }

                        }

                        // update the "pages" table (modified_users_id, modified) if "quiet save" is not enabled
                        if(!$this->field->get('input_fc_quiet_save')){

                            $statement = "UPDATE pages SET modified_users_id=:userid, modified=CURRENT_TIMESTAMP() WHERE id=:id";
                            $query = $this->wire('database')->prepare($statement);
                            $query->bindValue(":userid", $this->wire('user')->id, PDO::PARAM_INT);
                            $query->bindValue(":id", $this->page->id, PDO::PARAM_INT);

                            try {
                                $query->execute();
                            } catch (Exception) {
                                // not used at the moment
                            }

                        }
                    }

                }

            }

            // output the form
            $out = $this->renderHeadline();
            $out .= '<div id="' . $this->getID() . '-form-wrapper" class="fc-comment-form-wrapper">';
            $out .= parent::render();
            $out .= '</div>';

            // remove and set sessions to display/hide the reply form
            if ($valid) {
                $this->wire('session')->remove($this->getID());
                $this->wire('session')->set('disable-reply', '1');
            }

            return $out;
        }

    }
