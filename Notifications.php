<?php

    namespace FrontendComments;

    /*
     * Class for creating and sending of the various notification emails
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: Comment.php
     * Created: 31.10.2023
     *
     * @property protected array $frontendCommentsConfig: array containing all module config values
     * @property protected array $statusTexts: array containing all status names
     *
     */

    use ProcessWire\Field;
    use ProcessWire\Page;
    use ProcessWire\InputfieldFrontendComments;
    use ProcessWire\FieldtypeFrontendComments;
    use ProcessWire\WireMail;
    use FrontendForms\Tag;

    class Notifications extends Tag
    {

        use configValues;

        protected array $statusTexts = [];
        protected array $frontendCommentsConfig = [];
        protected array $frontendFormsConfig = [];
        protected CommentArray $comments;
        protected Page $page;
        protected Field $field;

        public function __construct(CommentArray $comments)
        {

            parent::__construct();

            // set default values
            $this->comments = $comments; // the comment text object
            $this->page = $comments->getPage(); // the current page object, which contains the comment field
            $this->field = $comments->getField(); // Processwire comment field object

            // get the status names array
            $this->statusTexts = InputfieldFrontendComments::statusTexts();

            // grab configuration values from the FrontendComments input field
            $this->frontendCommentsConfig = $this->getFrontendCommentsInputfieldConfigValues();
            $this->frontendFormsConfig = $this->getFrontendFormsConfigValues();
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
            $out = '';
            if ($headline) {
                $out = '<h' . $level . '>' . $headline . '</h' . $level . '>';
            }
            return $out;
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
         * @param Comment $newComment
         * @param \FrontendComments\CommentForm $form
         * @return string
         */
        protected function renderNotificationAboutNewCommentBody(array $values, Comment $newComment, CommentForm $form): string
        {
            // create the body for the email
            $body = $this->renderMailHeadline($this->_('A new comment has been submitted'));
            $body .= '<table>';
            foreach ($values as $fieldName => $value) {
                $fieldName = str_replace($form->getID() . '-', '', $fieldName);
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
                $url = $this->page->httpUrl . '?code=' . $newComment->code . '&status=1#' . $form->getID() . '-form-wrapper';
                $body .= self::renderButton($this->_('Publish the comment'), '#7BA428', '#ffffff',
                    '#7BA428', $url);
            }

            // create button to mark comment as SPAM
            $spamUrl = $this->page->httpUrl . '?code=' . $newComment->code . '&status=2#' . $form->getID() . '-form-wrapper';
            $body .= self::renderButton($this->_('Mark this comment as SPAM'), '#ED2939',
                '#ffffff', '#ED2939', $spamUrl);

            return $body;
        }

        /**
         * Send notification mail to the moderators if a new comment has been posted
         * @param array $values
         * @param \FrontendComments\Comment $newComment
         * @param \FrontendComments\CommentForm $form
         * @return bool
         * @throws \ProcessWire\WireException
         */
        public function sendNotificationAboutNewComment(array $values, Comment $newComment, CommentForm $form): bool
        {

            // Send a notification email to the moderators
            $mail = new WireMail();

            // set the sender email address
            $senderEmail = array_key_exists('input_fc_email', $this->frontendCommentsConfig) ? $this->frontendCommentsConfig['input_fc_email'] : $this->_('comment-notification') . '@' . $_SERVER["SERVER_NAME"];
            $mail->from($senderEmail);

            // set from name if present
            if (array_key_exists('input_fc_sender', $this->frontendCommentsConfig)) {
                $mail->fromName($this->frontendCommentsConfig['input_fc_sender']);
            }

            $mail->subject($this->_('A new reply has been posted'));
            $mail->title($this->_('A new reply has been posted'));

            // remove unnecessary values, which should not be sent via the notification mail
            unset($values[$form->getID() . '-privacy']);
            unset($values[$form->getID() . '-privacy-text']);
            unset($values[$form->getID() . '-parent_id']);

            // render the body string for the mail
            $body = $this->renderNotificationAboutNewCommentBody($values, $newComment, $form);

            // set email template depending on config settings
            $template = $this->frontendCommentsConfig['input_fc_emailTemplate'];
            if ($template === 'inherit') {
                $template = $this->frontendFormsConfig['input_emailTemplate'];
            }
            $mail->mailTemplate($template);
            $mail->bodyHTML($body);
            // set all receivers
            $form->setMailTo($mail);
            // send the mail
            return $mail->send();
        }

        /**
         * Create and render the body text for the notification about new reply email
         * @param \FrontendComments\Comment $comment
         * @param \ProcessWire\Page $page
         * @param \ProcessWire\Field $field
         * @param string $code
         * @return string
         */
        protected function renderNotificationAboutNewReplyBody(Comment $comment, Page $page, Field $field, string $code): string
        {
            // create the body for the email
            $body = $this->renderMailHeadline($this->_('A new reply has been submitted'));
            $body .= '<p>' . $this->_('You are receiving this email because you have agreed to be notified when a new reply has been posted.') . '</p>';
            $body .= '<h2>' . $this->_('New comment') . '</h2>';
            if ($comment->author) {
                $body .= '<p>' . $this->_('Author') . ': ' . $comment->author . '</p>';
            }
            $body .= $this->renderMailText($comment->text);
            $body .= '<p>' . $this->_('Link to the page of the comment') . ': ' . $page->httpUrl . '</p>';
            $body .= '<p>' . $this->_('If you do not want to receive further mails about new replies, please click the link below') . '</p>';

            // create a link for canceling the receiving of further notifications
            $url = $page->httpUrl . '?code=' . $code . '&notification=0#' . $field->name . '-form-wrapper';
            $body .= $this->renderButton($this->_('Stop sending me further notification mails about new comments'), '#ED2939', '#ffffff',
                '#7BA428', $url);
            return $body;
        }

        /**
         * Send notification mail to a commenter if a new reply to his comment has been posted
         * @param \FrontendComments\Comment $comment
         * @param \ProcessWire\Page $page
         * @param \ProcessWire\Field $field
         * @param string $code
         * @param string $email
         * @return bool
         * @throws \ProcessWire\WireException
         */
        public function sendNotificationAboutNewReply(Comment $comment, Page $page, Field $field, string $code, string $email): bool
        {
            bd('start');
            // create WireMail instance
            $mail = new WireMail();

            // set the sender email address
            $emailSender = $field->input_fc_email ? $field->input_fc_email : $this->_('comment-notification') . '@' . $_SERVER["SERVER_NAME"];
            $mail->from($emailSender);

            // set from name if present
            if ($field->input_fc_sender) {
                $mail->fromName($this->input_fc_sender);
            }

            $mail->subject($this->_('New reply to a comment'));
            $mail->title($this->_('A new reply has been posted'));

            // set email template depending on config settings
            $template = $field->input_fc_emailTemplate === 'inherit' ? $this->frontendFormsConfig['input_emailTemplate'] : $field->input_fc_emailTemplate;
            $mail->mailTemplate($template);

            $body = $this->renderNotificationAboutNewReplyBody($comment, $page, $field, $code);

            $mail->bodyHTML($body);
            $mail->to($email);
            bd('send');
            return $mail->send();
        }

        /**
         * Create and return the body text for the "status has been changed" mail
         * This mail will be sent to the commenter, if the status has been changed via the remote link or in the backend
         * @param \FrontendComments\Comment $comment
         * @return string
         */
        public function renderStatusChangeBody(Comment $comment): string
        {

            // create the body for the email
            $body = '<h1>' . $this->_('The status of your comment has been changed by a moderator') . '</h1>';
            $body .= '<p>' . $this->_('We would like to inform you that the following comment you wrote has now been reviewed by a moderator:') . '</p>';
            $body .= $this->renderMailText($comment->text);
            $body .= '<p>' . $this->_('The status of the comment has been changed to:') . '</p>';

            if ($comment->status == '1') {
                $backgroundcolor = FieldtypeFrontendComments::$successBg;
            } else {
                $backgroundcolor = FieldtypeFrontendComments::$dangerBg;
            }

            $body .= '<table style="width:100%;background-color:' . $backgroundcolor . ';"><tr style="width:100%;"><td style="width:100%;"><table style="width:100%;"><tr style="width:100%;"><td style="width:100%;text-align:center;"><p style="margin:12px;color:#FFFFFF;">' . $this->statusTexts[$comment->status] . '</p></td></tr></table></td></tr></table>';

            if ($comment->status === '1') {
                $body .= '<p>' . $this->_('Your comment is now published and visible to all.') . '</p>';
                $body .= '<p>' . $this->_('Link to the comment page') . ':  <a href="' . $comment->page->httpUrl . '#comment-' . $comment->id . '">' . $comment->page->title . '</a></p>';
            }
            return $body;
        }

        /**
         * Send the status has changed email to a commenter
         * @param \FrontendComments\Comment $comment
         * @param $field
         * @param array $frontendFormsConfig
         * @return bool
         * @throws \ProcessWire\WireException
         */
        public function sendStatusChangeEmail(Comment $comment, $field, array $frontendFormsConfig): bool
        {

            $mail = new WireMail();

            // set the sender email address
            $emailSender = $field->input_fc_email ?: $this->_('comment-notification') . '@' . $_SERVER["SERVER_NAME"];
            $mail->from($emailSender);

            // set from name if present
            if ($field->input_fc_sender) {
                $mail->fromName($this->input_fc_sender);
            }

            $mail->subject($this->_('Comment status has been changed'));
            $mail->title(sprintf($this->_('Your comment status has been changed to %s'), $this->statusTexts[$comment->status]));

            // set email template depending on config settings
            $template = $field->input_fc_emailTemplate === 'inherit' ? $frontendFormsConfig['input_emailTemplate'] : $field->input_fc_emailTemplate;
            $mail->mailTemplate($template);

            $mail->bodyHTML($this->renderStatusChangeBody($comment));
            $mail->to($comment->email);

            return $mail->send();

        }

    }
