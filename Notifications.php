<?php
    declare(strict_types=1);

    namespace FrontendComments;

    /*
     * Class for creating and sending of the various notification emails
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: Notifications.php
     * Created: 28.03.2025
     *
     */

    use FrontendForms\Link;
    use ProcessWire\Field;
    use ProcessWire\Page;
    use ProcessWire\InputfieldFrontendComments;
    use ProcessWire\FieldtypeFrontendComments;
    use ProcessWire\WireMail;
    use FrontendForms\Tag;
    use function ProcessWire\wire;

    class Notifications extends Tag
    {

        // Declare all properties'
        protected array $frontendFormsConfig = []; // array containing the configuration values of the FrontendForms module
        protected FrontendCommentArray|array $comments; // The FrontendCommentArray containing the comments
        protected Field $field; // the field of the FrontendComments Fieldtype
        protected Page $page; // the page where the form is embedded/displayed
        protected FrontendCommentForm $form;

        protected string $emailTemplate = ''; // the email template that should be used for sending
        protected string $senderEmail = ''; // the sender's email address
        protected string $senderName = ''; // the sender's name

        public function __construct(FrontendCommentArray|array $comments, Field $field, Page $page)
        {

            parent::__construct();

            // set default values
            $this->comments = $comments; // the comment text object
            $this->field = $field;
            $this->page = $page;

            // grab configuration values from the FrontendForms module
            $this->frontendFormsConfig = FieldtypeFrontendComments::getFrontendFormsConfigValues();

            // set the mail values
            $this->emailTemplate = $this->field->get('input_fc_emailTemplate');
            $this->senderEmail = $this->getSenderEmail();
            $this->senderName = $this->getSenderName();

        }

        /**
         * Get the email template that should be used for sending
         * @return string
         */
        protected function getMailTemplate(): string
        {
            // get value from configuration settings
            return $this->field->get('input_fc_emailTemplate');
        }

        /**
         * Create the link to the community guidelines depending on the settings
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function getCommunityGuidelinesURL(): string|null
        {
            $url = null;

            $type = $this->field->get('input_guidelines_type');
            if ($type == 0) return null;

            if ($type == 1) {
                // internal page
                $pageID = $this->field->get('input_fc_internalPage')[0];
                $url = $this->wire('pages')->get($pageID)->httpUrl;
            } else if ($type == 2) {
                // check if multilanguage
                if (count(wire('languages')) > 1) {

                    if (!wire('user')->get('language')->isDefault()) {
                        $langID = wire('user')->get('language')->id;
                        $propLangName = 'input_fc_externalPage' . $langID;
                        $url = $this->field->get($propLangName) ?? $this->field->get('input_fc_externalPage');
                    } else {
                        $url = $this->field->get('input_fc_externalPage');
                    }
                } else {
                    $url = $this->field->get('input_fc_externalURL');
                }
            }
            return $url;
        }

        /**
         * Get the sender address of the mails
         * @return string
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function getSenderEmail(): string
        {
            $senderEmail = $this->_('comment-notification') . '@' . $_SERVER["SERVER_NAME"];

            $semail = FieldtypeFrontendComments::getFieldConfigLangValue($this->field, 'input_fc_from');
            // get Value from global config
            if ($semail)
                $senderEmail = $semail;

            return $senderEmail;
        }

        /**
         * Get the sender's name
         * @return string
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function getSenderName(): string
        {
            $senderName = '';

            // get Value from global config
            $sname = FieldtypeFrontendComments::getFieldConfigLangValue($this->field, 'input_fc_from_name');
            if ($sname)
                $senderName = $sname;

            return $senderName;
        }

        /**
         * Helper funciton to rename a key
         * @param $arr
         * @param $oldkey
         * @param $newkey
         * @return array
         */
        protected function replaceKey($arr, $oldkey, $newkey): array
        {
            if (array_key_exists($oldkey, $arr)) {
                $keys = array_keys($arr);
                $keys[array_search($oldkey, $keys)] = $newkey;
                return array_combine($keys, $arr);
            }
            return $arr;
        }

        /**
         * Helper funciton to replace a form value with a new one
         * @param $arr
         * @param $key
         * @param $newValue
         * @return array
         */
        protected function replaceValue($arr, $key, $newValue): array
        {
            $arr[$key] = $newValue;
            return $arr;
        }

        /**
         * Replace the integer value with a text value for the reply notification
         * @param array $arr
         * @return string
         */
        protected function getReplyNotification(array $arr): string
        {
            $value = '';
            if (array_key_exists('notification', $arr)) {
                switch ($arr['notification']) {
                    case 1:
                        $value = $this->_('Notification only on replies');
                        break;
                    case 2:
                        $value = $this->_('Notification on all replies');
                        break;
                }
            }
            return $value;
        }

        /**
         * Send notification mail to the moderators if a new comment has been posted
         * @param array $values
         * @param \FrontendComments\FrontendComment $newComment
         * @param \FrontendComments\FrontendCommentForm $form
         * @return bool|null
         * @throws \ProcessWire\WireException
         */
        public function sendModerationNotificationMail(array $values, FrontendComment $newComment, FrontendCommentForm $form): bool|null
        {
            $sent = null;
            // check if moderation emails addresses are set
            $moderationEmails = $this->comments->getModerationEmail();
            if ($moderationEmails) {

                // Send a notification email to the moderator(s)
                $mail = new WireMail();
                $mail->from($this->senderEmail);
                $mail->fromName($this->senderName);
                $mail->subject($this->_('A new comment has been posted'));
                $mail->title($this->_('Please check the new comment'));
                $mail->mailTemplate($this->emailTemplate);

                // overwrite some keys to display the correct label
                $values = $this->replaceKey($values, 'data', 'text');

                // overwrite some values

                // 1) star rating
                if (array_key_exists('stars', $values)) {
                    if (is_null($values['stars'])) {
                        $values = $this->replaceValue($values, 'stars', $this->_('not rated'));
                    } else {
                        $values = $this->replaceValue($values, 'stars', str_replace($values['stars'], $values['stars'].'/5 ('.FrontendCommentForm::$ratingValues[$values['stars']].')',$values['stars']));
                    }
                }

                // remove unnecessary form values, which should not be sent via the notification mail
                unset($values['privacy']);
                unset($values['privacy-text']);
                unset($values['parent_id']);
                unset($values['notification']);

                // set all receivers
                foreach ($moderationEmails as $email) {
                    // render the body string for the mail
                    $body = $this->renderNotificationAboutNewCommentBody($values, $newComment, $form);
                    $mail->bodyHTML($body);
                    $mail->to($email);
                }

                // finally, send the mail
                $sent = $mail->send();
            }
            return (bool)$sent;
        }

        /**
         * Render a button for the email template
         * This button will be used as the "Mark as Spam" button inside the email
         *
         * @param string $text
         * @param string $bgColor
         * @param string $textColor
         * @param string $borderColor
         *
         * @param string|null $url
         * @return string
         */
        public static function renderButton(
            string $text,
            string $bgColor,
            string $textColor,
            string $borderColor,
            string $url = null,
        ): string
        {
            $out = '<table style="padding-top:20px">';
            $out .= '<tr><td><table><tr><td style="border-radius: 2px;background-color:' . $bgColor . ';">';
            if (!is_null($url)) {
                $out .= '<a href="' . $url . '" style="padding: 8px 12px; border: 1px solid ' . $borderColor . ';border-radius: 2px;sans-serif;font-size: 14px; color: ' . $textColor . ';text-decoration: none;font-weight:bold;display: inline-block;">' . $text . '</a>';
            } else {
                $out .= '<span style="padding: 8px 12px; border: 1px solid ' . $borderColor . ';border-radius: 2px;sans-serif;font-size: 14px; color: ' . $textColor . ';font-weight:bold;display: inline-block;">' . $text . '</span>';
            }
            $out .= '</td></tr></table></td></tr></table>';
            return $out;
        }

        /**
         * Render the headline of the email
         * @param string|null $headline
         * @param int $level
         * @return string
         */
        protected function renderMailHeadline(string|null $headline = null, int $level = 1): string
        {
            return ($headline) ? '<h' . $level . '>' . $headline . '</h' . $level . '>' : '';
        }

        /**
         * Render the main text of the comment
         * Will be displayed inside a table
         * @param string|null $text
         * @return string
         */
        protected function renderMailText(string|null $text = null): string
        {
            $out = '';
            if ($text) {
                $out = '
                    <table style="width:100%;background-color:#dddddd;">
                    <tr style="width:100%;">
                    <td style="width:100%;">
                    <table style="width:100%;">
                    <tr style="width:100%;">
                    <td style="width:100%;">
                    <p style="margin:12px;">' . $text . '</p>
                    </td>
                    </tr>
                    </table>
                    </td>
                    </tr>
                    </table>';
            }
            return $out;
        }

        /**
         * Create the body text for the notification email
         * This method creates the content markup of the email
         * @param array $values
         * @param FrontendComment $newComment
         * @param FrontendCommentForm $form
         * @return string
         * @throws \ProcessWire\WireException
         */
        protected function renderNotificationAboutNewCommentBody(array $values, FrontendComment $newComment, FrontendCommentForm $form): string
        {
            // create the body for the email
            $body = $this->renderMailHeadline($this->_('A new comment has been submitted'));
            $body .= '<table>';

            foreach ($values as $fieldName => $value) {
                $fieldName = str_replace($form->getID() . '-', '', $fieldName);
                $body .= '<tr style="padding: 10px 0;border-bottom: 1px solid #000000;"><td style="font-weight:bold;">[[' . strtoupper($fieldName) . 'LABEL]]:&nbsp;</td><td>' . $value . '</td></tr>';
                $body .= '<tr><td colspan="2"><hr style="margin:0;height:0;border-top: 1px solid #f6f6f6"/></td></tr><tr>';
            }

            if ($newComment->get('status') == FieldtypeFrontendComments::approved) {
                $color = '#7BA428';
            } else {
                $color = '#FD953A';
            }
            $body .= '<tr style="padding: 10px 0;"><td style="font-weight:bold;white-space: nowrap">' . $this->_('Comment status') . ':&nbsp;</td><td><span style="padding:3px;display:inline-block;background:' . $color . ';color:#fff;">&nbsp;' . InputfieldFrontendComments::statusTexts()[$newComment->get('status')] . '&nbsp;</span></td></tr>';
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
            if ($newComment->get('status') == FieldtypeFrontendComments::pendingApproval) {
                $url = $this->comments->getPage()->httpUrl . '?code=' . $newComment->get('code') . '&status=1#remote-change';
                $body .= self::renderButton($this->_('Publish the comment'), '#7BA428', '#ffffff',
                    '#7BA428', $url);
            }

            // create button to mark comment as SPAM
            $spamUrl = $this->comments->getPage()->httpUrl . '?code=' . $newComment->get('code') . '&status=2#remote-change';
            $body .= self::renderButton($this->_('Mark this comment as SPAM'), '#ED2939',
                '#ffffff', '#ED2939', $spamUrl);

            return $body;
        }

        /**
         * Create and render the body text for the notification about a new reply email
         * @param array $comment
         * @param string $code
         * @return string
         */
        protected function renderNotificationAboutNewReplyBody(array $comment): string
        {
            // create the body for the email
            $body = $this->renderMailHeadline($this->_('A new reply has been submitted'));
            $body .= '<p>' . $this->_('You are receiving this email because you have agreed to be notified when a new reply has been posted.') . '</p>';
            $body .= '<h2>' . $this->_('New comment') . '</h2>';
            if ($comment['author']) {
                $body .= '<p>' . $this->_('Author') . ': ' . $comment['author'] . '</p>';
            }
            $body .= $this->renderMailText($comment['data']);

            // create a link to the comment
            $commentLink = new Link();
            $commentLink->setUrl($this->page->httpUrl);
            $commentLink->setQueryString('comment-redirect=' . $comment['id']);
            $commentLink->setAnchor($this->field->name . '-' . $this->page->id . '-redirect-alert');
            $commentLink->setLinkText($this->_('To the comment'));

            $body .= '<p>' . $this->_('Link to the comment') . ': ' . $commentLink->render() . '</p>';
            $body .= '<p>' . $this->_('If you do not want to receive further mails about new replies, please click the link below') . '</p>';

            // create a link for canceling the receiving of further notifications
            $url = $this->page->httpUrl . '?code=' . $comment['code'] . '&notification=0#remote-change';
            $body .= $this->renderButton($this->_('Stop sending me further notification mails about new comments'), '#ED2939', '#ffffff',
                '#7BA428', $url);
            return $body;
        }

        /**
         * Send notification mail to a commenter if a new reply to his comment has been posted
         * @param \FrontendComments\FrontendComment $comment
         * @param Page $page
         * @return int
         * @throws \ProcessWire\WireException
         */
        public function sendNotificationAboutNewReply(array $comment): int
        {

            // create WireMail instance
            $mail = new WireMail();
            $mail->from($this->senderEmail);
            $mail->fromName($this->senderName);
            $mail->subject($this->_('New reply to a comment'));
            $mail->title($this->_('A new reply has been posted'));
            $mail->mailTemplate($this->emailTemplate);

            // create body content
            $body = $this->renderNotificationAboutNewReplyBody($comment);
            $mail->bodyHTML($body);

            $mail->to($comment['email']);;
            return $mail->send();
        }

        /**
         * Create and return the body text for the "status has been changed" mail
         * This mail will be sent to the commenter, if the status has been changed via the remote link or in the backend
         * @param \FrontendComments\FrontendComment $comment
         * @param int $status
         * @return string
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        public function renderStatusChangeBody(FrontendComment $comment, int $status): string
        {

            // create the body for the email
            $body = '<h1>' . $this->_('The status of your comment has been changed by a moderator') . '</h1>';
            $body .= '<p>' . $this->_('We would like to inform you that the following comment, which you wrote, has now been reviewed by a moderator:') . '</p>';
            $body .= $this->renderMailText($comment->get('text'));
            $body .= '<p>' . $this->_('The status of the comment has been changed to:') . '</p>';
            $statusColor = $status === FieldtypeFrontendComments::approved ? '#7BA428' : '#ED2939';
            $body .= '<table style="width:100%;"><tr style="width:100%;"><td style="width:100%;"><table style="width:100%;"><tr style="width:100%;"><td style="width:100%;text-align:center;background-color:' . $statusColor . ';"><p style="margin:12px;color:#ffffff;"><strong>' . InputfieldFrontendComments::statusTexts()[$status] . '</strong></p></td></tr></table></td></tr></table>';

            if ($status === 1) {
                $body .= '<p>' . $this->_('Your comment is now published and visible to everyone.') . '</p>';
                $body .= '<p>' . $this->_('Link to your comment');
                $commentPage = $comment->get('page');
                $body .= ':  <a href="' . $commentPage->httpUrl . '?comment-redirect=' . $comment->get('id') . '">' . $this->_('View the comment') . '</a></p>';
            } else {
                $body .= '<p>' . $this->_('We are sorry, but your comment contains content that violates our policies.') . '<br>';
                $body .= $this->_('For this reason, your comment cannot be published.') . '</p>';
                $guidelineUrl = $this->getCommunityGuidelinesURL();
                if ($guidelineUrl) {
                    $guidelineLink = '<a href="' . $guidelineUrl . '">' . $this->_('Community Guidelines') . '</a>';
                    $body .= '<p>' . sprintf($this->_('You will find our Community guidelines for posting comments here:  %s'), $guidelineLink) . '</p>';
                }
            }
            return $body;
        }

        /**
         * Email the commenter that the status of the comment has been changed
         * @param \FrontendComments\FrontendComment $comment
         * @param \ProcessWire\Field $field
         * @param array $frontendFormsConfig
         * @param int $status
         * @return bool
         * @throws \ProcessWire\WireException
         */
        public function sendStatusChangeEmail(FrontendComment $comment, Field $field, array $frontendFormsConfig, int $status): bool
        {

            // check if sending email is enabled inside the configuration

            $mail = new WireMail();

            // set the sender email address
            $emailSender = FieldtypeFrontendComments::getFieldConfigLangValue($field, 'input_fc_from') ?? $this->_('comment-notification') . '@' . $_SERVER["SERVER_NAME"];
            $mail->from($emailSender);

            // set from name if present
            if (FieldtypeFrontendComments::getFieldConfigLangValue($field, 'input_fc_from_name')) {
                $mail->fromName(FieldtypeFrontendComments::getFieldConfigLangValue($field, 'input_fc_from_name'));
            }

            $mail->subject($this->_('Comment status has been changed'));
            $mail->title(sprintf($this->_('Your comment status has been changed to %s'), InputfieldFrontendComments::statusTexts()[$status]));

            // set email template depending on config settings
            $template = $field->get('input_fc_emailTemplate') === 'inherit' ? $frontendFormsConfig['input_emailTemplate'] : $field->get('input_fc_emailTemplate');
            if ($template !== 'text') {
                $mail->mailTemplate($template);
            }

            $mail->bodyHTML($this->renderStatusChangeBody($comment, $status));
            $mail->to($comment->get('email'));

            return (bool)$mail->send();

        }

    }
