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
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\WireException;
use ProcessWire\WireMail;
use FrontendForms\Tag;
use ProcessWire\WirePermissionException;

use function ProcessWire\wire;

class Notifications extends Tag
{
    // Declare all properties'
    protected array $frontendFormsConfig = [];
    // array containing the configuration values of the FrontendForms module
    protected FrontendCommentArray|array $comments;
    // The FrontendCommentArray containing the comments
    protected Field $field;
    // the field of the FrontendComments Fieldtype
    protected Page $page;
    // the page where the form is embedded/displayed
    protected FrontendCommentForm $form;
    protected string $emailTemplate = '';
    // the email template that should be used for sending
    protected string $senderEmail = '';
    // the sender's email address
    protected string $senderName = '';
    // the sender's name

    /**
     * Constructor: store the comments, field and page this instance sends notifications for,
     * and pre-resolve the sender name/email plus the email template to be used for all mails
     * sent through this instance
     * @param FrontendCommentArray|array $comments
     * @param Field $field
     * @param Page $page
     */
    public function __construct(FrontendCommentArray|array $comments, Field $field, Page $page)
    {

        parent::__construct();

        // set default values
        $this->comments = $comments;
        // the comment text object
        $this->field = $field;
        $this->page = $page;

        // grab configuration values from the FrontendForms module
        $this->frontendFormsConfig = FieldtypeFrontendComments::getFrontendFormsConfigValues();

        // set the mail values
        // A field that has never had "input_fc_emailTemplate" explicitly saved (e.g. created before
        // this setting existed) returns null here, not the configured default of 'inherit' (see
        // FieldtypeFrontendComments::getDefaultData()) - that default only applies inside
        // getConfigValue(), used to render the admin config form, not by this direct read.
        // $emailTemplate is a non-nullable string property, so assigning null directly used to throw
        // a TypeError right here in the constructor - on every single notification email attempt
        // (new comment, new reply, status change) for such a field.
        $this->emailTemplate = $this->field->get('input_fc_emailTemplate') ?? FieldtypeFrontendComments::getDefaultData()['input_fc_emailTemplate'];
        $this->senderName = $this->getSenderName();
        $host = $this->wire('config')->httpHost;
        if ($host === 'localhost') {
            $host = 'localhost.com';
        }
        $this->senderEmail = 'comment-notification@' . $host;
    }

    /**
     * Get the email template that should be used for sending
     * @return string
     */
    protected function getMailTemplate(): string
    {
        // get value from configuration settings
        // Same "never explicitly saved" null-safety as the constructor's own read of this setting
        // above - this method's non-nullable string return type would otherwise throw a TypeError
        // for the same reason.
        return $this->field->get('input_fc_emailTemplate') ?? FieldtypeFrontendComments::getDefaultData()['input_fc_emailTemplate'];
    }

    /**
     * Create the link to the community guidelines depending on the settings
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function getCommunityGuidelinesURL(): string|null
    {
        $url = null;

        $type = $this->field->get('input_guidelines_type');
        if ($type == 0) {
            return null;
        }

        if ($type == 1) {
            // internal page
            $pageID = $this->field->get('input_fc_internalPage')[0];
            $url = $this->wire('pages')->get($pageID)->httpUrl;
        } elseif ($type == 2) {
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
                // same field as used for the "no multi-language" and the default-language case
                // above - "input_fc_externalURL" does not exist as a config field and always
                // returned null here, silently dropping the configured guidelines link
                $url = $this->field->get('input_fc_externalPage');
            }
        }
        return $url;
    }

    /**
     * Get the sender's name
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function getSenderName(): string
    {
        $senderName = '';

        // get Value from global config
        $sname = FieldtypeFrontendComments::getFieldConfigLangValue($this->field, 'input_fc_from_name');
        if ($sname) {
            $senderName = $sname;
        }

        return $senderName;
    }

    /**
     * Helper function to rename a key
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
     * Helper function to replace a form value with a new one
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
    /*
    protected function getReplyNotification(array $arr): string
    {
        $value = '';
        if (array_key_exists('notification', $arr)) {
            switch ($arr['notification']) {
                case 1:
                    $value = $this->_('Notification only on replies to this comment');
                    break;
                case 2:
                    $value = $this->_('Notification on all replies');
                    break;
            }
        }
        return $value;
    }
    */

    /**
     * Send notification mail to the moderators if a new comment has been posted
     * @param array $values
     * @param FrontendComment $newComment
     * @param FrontendCommentForm $form
     * @return bool|null
     * @throws WireException
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
                    $values = $this->replaceValue($values, 'stars', str_replace($values['stars'], $values['stars'] . '/5 (' . FrontendCommentForm::$ratingValues[$values['stars']] . ')', $values['stars']));
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
     * Send a reminder email to the moderator(s) about a comment that is still waiting for approval
     * after the configured number of days (see FieldtypeFrontendComments::sendPendingReminders(),
     * hooked to LazyCron::everyDay).
     *
     * Unlike sendModerationNotificationMail() above, this is not sent right after a form
     * submission - it runs later, from a LazyCron job that only has the comment's own stored
     * database row to work with, not the original form submission's $values array or the
     * FrontendCommentForm instance that rendered it. The email body is therefore built directly
     * from the FrontendComment object's own properties instead of reusing
     * renderNotificationAboutNewCommentBody().
     * @param array $data the raw comment database row (as fetched by sendPendingReminders(), not a
     *   fully-constructed FrontendComment - avatar/link/vote sub-objects etc. are never needed here,
     *   so building the full, heavy FrontendComment object for a one-line reminder mail is avoided)
     * @param array $moderationEmails the moderator email address(es) to notify
     * @param int $days the configured number of days a comment may stay pending before this reminder is sent
     * @return bool
     * @throws WireException
     */
    public function sendPendingReminderMail(array $data, array $moderationEmails, int $days): bool
    {
        $mail = new WireMail();
        $mail->from($this->senderEmail);
        $mail->fromName($this->senderName);
        $mail->subject($this->_('Reminder: a comment is still waiting for approval'));
        $mail->title($this->_('Please check this pending comment'));
        $mail->mailTemplate($this->emailTemplate);
        $mail->bodyHTML($this->renderPendingReminderBody($data, $days));

        foreach ($moderationEmails as $email) {
            $mail->to($email);
        }

        return (bool)$mail->send();
    }

    /**
     * Build the body of the pending-comment reminder email
     * @param array $data the raw comment database row, see sendPendingReminderMail()
     * @param int $days
     * @return string
     * @throws WireException
     */
    protected function renderPendingReminderBody(array $data, int $days): string
    {
        $sanitizer = $this->wire('sanitizer');

        $body = $this->renderMailHeadline($this->_('A comment is still waiting for approval'));
        $body .= '<p>' . sprintf($this->_n('This comment has been waiting for approval for at least %s day. Please review it.', 'This comment has been waiting for approval for at least %s days. Please review it.', $days), $days) . '</p>';
        $created = (int)($data['created'] ?? 0);
        $dateString = $this->wire('datetime')->date($this->frontendFormsConfig['input_dateformat'], $created);
        $timeString = $this->wire('datetime')->date($this->frontendFormsConfig['input_timeformat'], $created);

        // Same escaping reasoning as renderNotificationAboutNewCommentBody() above: author/text/
        // email are values an anonymous visitor originally submitted, embedded here directly into
        // the HTML body of the moderator's mail client, so they must be entity-encoded.
        // Note: the raw database row uses the column name "data" for the comment text, not "text"
        // ("text" is only an alias FrontendComment::__construct() applies for its own properties).
        $rows = [
            $this->_('Name') => (string)($data['author'] ?? ''),
            $this->_('Email') => (string)($data['email'] ?? ''),
            $this->_('Comment') => (string)($data['data'] ?? ''),
            $this->_('Submitted on') => $dateString . ' ' . $timeString,
        ];

        $body .= '<table>';
        foreach ($rows as $label => $value) {
            $safeValue = $sanitizer->entities($value);
            $body .= '<tr><td style="padding: 14px 0;font-weight:bold;">' . $sanitizer->entities($label) . ':&nbsp;</td><td style="padding: 14px 0;">' . $safeValue . '</td></tr>';
            $body .= '<tr><td colspan="2"><hr style="margin:0;height:0;border-top: 1px solid #f6f6f6"/></td></tr>';
        }
        $body .= '</table>';

        // Same remote-link buttons (and the same "?code=...&status=..." link scheme) as the
        // original new-comment moderation email in renderNotificationAboutNewCommentBody() above.
        $code = (string)($data['code'] ?? '');
        $url = $this->page->httpUrl . '?code=' . $code . '&status=1#remote-change';
        $body .= self::renderButton($this->_('Publish the comment'), '#7BA428', '#ffffff', '#7BA428', $url);

        $spamUrl = $this->page->httpUrl . '?code=' . $code . '&status=2#remote-change';
        $body .= self::renderButton($this->_('Mark this comment as SPAM'), '#ED2939', '#ffffff', '#ED2939', $spamUrl);

        return $body;
    }

    /**
     * Render a button for the email template
     * This button will be used for several remote changes
     *
     * @param string $text
     * @param string $bgColor
     * @param string $textColor
     * @param string $borderColor
     *
     * @param string|null $url
     * @return string
     */
    public static function renderButton(string $text, string $bgColor, string $textColor, string $borderColor, string|null $url = null,): string
    {
        // Spacing note: padding on a <table> element itself is unreliable in email clients (most
        // notably Outlook's Word rendering engine ignores it), which is why the gap between
        // consecutive buttons rendered this way could look smaller in practice than the padding
        // value below suggests. The padding is applied to the <td> instead, which every mail client
        // honors, so two buttons in a row (e.g. "Publish"/"Mark as SPAM") sit visibly apart.
        $out = '<table role="presentation">';
        $out .= '<tr><td style="padding-top:15px;"><table><tr><td style="border-radius: 2px;background-color:' . $bgColor . ';">';
        if (!is_null($url)) {
            // $url can contain caller-supplied, unencoded values (e.g. the unsubscribe link below
            // embeds the commenter's raw email address) - escape it for the href attribute context
            // so a crafted value cannot break out of the attribute.
            $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $out .= '<a href="' . $safeUrl . '" style="padding: 8px 12px; border: 1px solid ' . $borderColor . ';border-radius: 2px;sans-serif;font-size: 14px; color: ' . $textColor . ';text-decoration: none;font-weight:bold;display: inline-block;">' . $text . '</a>';
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
     * @throws WireException
     */
    protected function renderNotificationAboutNewCommentBody(array $values, FrontendComment $newComment, FrontendCommentForm $form): string
    {
        // create the body for the email
        $body = $this->renderMailHeadline($this->_('A new comment has been submitted'));
        $body .= '<table>';

        foreach ($values as $fieldName => $value) {
            $fieldName = str_replace($form->getID() . '-', '', $fieldName);
            // $values are the raw, just-submitted form values (author, text, email, website, ...)
            // from an anonymous visitor - embedded here directly into the HTML body of the
            // moderation email, so they must be escaped, otherwise a crafted comment is HTML/script
            // injection into the moderator's mail client.
            $safeValue = $this->wire('sanitizer')->entities((string)$value);
            // Spacing note (same reasoning as renderButton() above): padding on a <tr> is just as
            // unreliable in email clients as on a <table> - Outlook's Word rendering engine ignores
            // it too. Applying it to both <td>s instead, which every mail client honors, makes each
            // row visibly taller.
            // Real bug found and fixed: these <td>s used to also carry their own
            // "border-bottom: 1px solid #000000" - on top of the separate <hr> row added right below
            // (same one every other row in this table already uses as its sole separator), that
            // produced two visible lines stacked directly under each other for every row in this
            // loop (Email/Name/Comment), while the other rows (Comment status, URL, date/time, IP,
            // browser) only ever had the single <hr> line. Removed the extra border so every row in
            // this table is separated the same, single way.
            $body .= '<tr><td style="padding: 14px 0;font-weight:bold;">[[' . strtoupper($fieldName) . 'LABEL]]:&nbsp;</td><td style="padding: 14px 0;">' . $safeValue . '</td></tr>';
            $body .= '<tr><td colspan="2"><hr style="margin:0;height:0;border-top: 1px solid #f6f6f6"/></td></tr>';
        }

        if ($newComment->get('status') == FieldtypeFrontendComments::approved) {
            $color = '#7BA428';
        } else {
            $color = '#FD953A';
        }
        // Spacing note (same reasoning as renderButton() above, confirmed by the user - the button's
        // padding DOES render correctly there): padding on a <span>, even with display:inline-block,
        // is unreliable in email clients (Outlook's Word rendering engine in particular tends to
        // ignore it). renderButton() works around this by putting the background color and padding
        // on a <td> instead of on the inline <a>/<span> - every mail client honors padding on a
        // table cell. Rebuilt the status badge the same way: a small nested table whose single <td>
        // carries both the background color and the padding.
        $body .= '<tr><td style="padding: 14px 0;font-weight:bold;white-space: nowrap">' . $this->_('Comment status') . ':&nbsp;</td><td style="padding: 14px 0;"><table role="presentation"><tr><td style="padding:6px 12px;background-color:' . $color . ';color:#fff;">' . FieldtypeFrontendComments::statusTexts()[$newComment->get('status')] . '</td></tr></table></td></tr>';
        $body .= '<tr><td colspan="2"><hr style="margin:0;height:0;border-top: 1px solid #f6f6f6;"/></td></tr>';
        $body .= '<tr><td style="padding: 14px 0;font-weight:bold;">[[CURRENTURLLABEL]]:&nbsp;</td><td style="padding: 14px 0;">[[CURRENTURLVALUE]]</td></tr>';
        $body .= '<tr><td colspan="2"><hr style="margin:0;height:0;border-top: 1px solid #f6f6f6"/></td></tr>';
        $body .= '<tr><td style="padding: 14px 0;font-weight:bold;">[[CURRENTDATETIMELABEL]]:&nbsp;</td><td style="padding: 14px 0;">[[CURRENTDATETIMEVALUE]]</td></tr>';
        $body .= '<tr><td colspan="2"><hr style="margin:0;height:0;border-top: 1px solid #f6f6f6"/></td></tr>';
        $body .= '<tr><td style="padding: 14px 0;font-weight:bold;">[[IPLABEL]]:&nbsp;</td><td style="padding: 14px 0;">[[IPVALUE]]</td></tr>';
        $body .= '<tr><td colspan="2"><hr style="margin:0;height:0;border-top: 1px solid #f6f6f6"/></td></tr>';
        $body .= '<tr><td style="padding: 14px 0;font-weight:bold;">[[BROWSERLABEL]]:&nbsp;</td><td style="padding: 14px 0;">[[BROWSERVALUE]]</td></tr>';
        $body .= '</table>';

        // create a link for approving the comment if the status has been set to 0
        if ($newComment->get('status') == FieldtypeFrontendComments::pendingApproval) {
            $url = $this->comments->getPage()->httpUrl . '?code=' . $newComment->get('code') . '&status=1#remote-change';
            $body .= self::renderButton(
                $this->_('Publish the comment'),
                '#7BA428',
                '#ffffff',
                '#7BA428',
                $url
            );
        }

        // create button to mark comment as SPAM
        $spamUrl = $this->comments->getPage()->httpUrl . '?code=' . $newComment->get('code') . '&status=2#remote-change';
        $body .= self::renderButton(
            $this->_('Mark this comment as SPAM'),
            '#ED2939',
            '#ffffff',
            '#ED2939',
            $spamUrl
        );
        return $body;
    }

    /**
     * Currently unused placeholder - not called anywhere in the module (dead code), kept
     * as a reserved extension point for a future comment-specific notification helper
     */
    protected function getNotificationComment()
    {
    }

    /**
     * Create and render the body text for the notification about a new reply email
     * @param FrontendComment $comment
     * @return string
     */
    protected function renderNotificationAboutNewReplyBody(FrontendComment $comment): string
    {


        // create the body for the email
        $body = $this->renderMailHeadline($this->_('A new reply has been submitted'));
        $body .= '<p>' . $this->_('You are receiving this email because you have agreed to be notified when a new reply has been posted.') . '</p>';
        $body .= '<h2>' . $this->_('New comment') . '</h2>';
        if ($comment['author']) {
            // "author"/"text" are the reply's own free-text fields, submitted by whoever wrote the
            // reply - escape before embedding into the HTML mail sent to the original commenter.
            $body .= '<p>' . $this->_('Author') . ': ' . $this->wire('sanitizer')->entities($comment->get('author')) . '</p>';
        }
        $body .= $this->renderMailText($this->wire('sanitizer')->entities($comment->get('text')));

        // create a link to the comment
        $commentLink = new Link();
        $commentLink->setUrl($comment->get('page')->httpUrl);
        $commentLink->setQueryString('comment-redirect=' . $comment->get('id'));
        $commentLink->setAnchor($comment->get('field')->name . '-' . $comment->get('page')->id . '-redirect-alert');
        $commentLink->setLinkText($this->_('To the comment'));

        $body .= '<p>' . $this->_('Link to the comment') . ': ' . $commentLink->render() . '</p>';
        $body .= '<p>' . $this->_('If you do not want to receive further mails about new comments, please click the link below.') . '</p>';

        // create a link for canceling the receiving of further notifications
        // email must be urlencode()'d - it can contain "&", "+", "%" etc. which would otherwise
        // corrupt the query string (or, combined with the missing escaping in the old renderButton(),
        // allow breaking out of the href attribute entirely)
        $url = $this->page->httpUrl . '?email=' . urlencode($comment->get('email')) . '&page=' . $comment->get('page')->id . '&notification=0#remote-change';
        $body .= $this->renderButton(
            $this->_('Stop sending me further notification mails about new comments'),
            '#ED2939',
            '#ffffff',
            '#7BA428',
            $url
        );
        return $body;
    }

    /**
     * Send notification mail to a commenter if a new reply to his comment has been posted
     * @param FrontendComment $comment
     * @return int
     * @throws WireException
     */
    public function sendNotificationAboutNewReply(FrontendComment $comment): int
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

        $mail->to($comment->get('email'));
        return $mail->send();
    }

    /**
     * Create and render the body text for the notification-confirmation (double opt-in) email
     *
     * This mail is sent to the email address a commenter entered whenever that commenter has
     * chosen to be notified about replies/new comments (notification !== flagNotifyNone). It does
     * NOT quote the comment's own text/author back, and it does not name the page or say anything
     * about what was written - if the address does not actually belong to the commenter, the
     * person who receives this mail should learn as little as possible about a comment they never
     * wrote. Confirming (or ignoring) the link is the only way to tell them apart from the real
     * commenter.
     * @param FrontendComment $comment
     * @return string
     */
    protected function renderNotificationConfirmationBody(FrontendComment $comment): string
    {
        // create the body for the email
        $body = $this->renderMailHeadline($this->_('Please confirm your email address'));
        $body .= '<p>' . $this->_('Someone used this email address to request notifications about new comments on a website. If this was you, please confirm this request by clicking the button below.') . '</p>';
        $body .= '<p>' . $this->_('If you did not request this, you can simply ignore this email - no further mails will be sent to you unless this request is confirmed.') . '</p>';

        $url = $comment->get('page')->httpUrl . '?code=' . $comment->get('code') . '&confirmnotification=1#remote-change';
        $body .= self::renderButton($this->_('Confirm notification request'), '#7BA428', '#ffffff', '#7BA428', $url);

        return $body;
    }

    /**
     * Send the notification-confirmation (double opt-in) mail to the address entered for a comment
     * @param FrontendComment $comment
     * @return int
     * @throws WireException
     */
    public function sendNotificationConfirmationMail(FrontendComment $comment): int
    {
        // create WireMail instance
        $mail = new WireMail();
        $mail->from($this->senderEmail);
        $mail->fromName($this->senderName);
        $mail->subject($this->_('Please confirm your email address'));
        $mail->title($this->_('Please confirm your notification request'));
        $mail->mailTemplate($this->emailTemplate);

        // create body content
        $body = $this->renderNotificationConfirmationBody($comment);
        $mail->bodyHTML($body);

        $mail->to($comment->get('email'));
        return $mail->send();
    }

    /**
     * Create and return the body text for the "status has been changed" mail
     * This mail will be sent to the commenter, if the status has been changed via the remote link or in the backend
     * @param FrontendComment $comment
     * @param int $status
     * @return string
     * @throws WireException
     * @throws WirePermissionException
     */
    public function renderStatusChangeBody(FrontendComment $comment, int $status): string
    {

        // create the body for the email
        $body = '<h1>' . $this->_('The status of your comment has been changed by a moderator') . '</h1>';
        $body .= '<p>' . $this->_('We would like to inform you that the following comment, which you wrote, has now been reviewed by a moderator:') . '</p>';
        // see renderNotificationAboutNewReplyBody() above - "text" is the visitor's own free-text
        // comment body, so it must be escaped before being embedded into this HTML mail.
        $body .= $this->renderMailText($this->wire('sanitizer')->entities($comment->get('text')));
        $body .= '<p>' . $this->_('The status of the comment has been changed to:') . '</p>';
        $statusColor = $status === FieldtypeFrontendComments::approved ? '#7BA428' : '#ED2939';
        $body .= '<table style="width:100%;"><tr style="width:100%;"><td style="width:100%;"><table style="width:100%;"><tr style="width:100%;"><td style="width:100%;text-align:center;background-color:' . $statusColor . ';"><p style="margin:12px;color:#ffffff;"><strong>' . FieldtypeFrontendComments::statusTexts()[$status] . '</strong></p></td></tr></table></td></tr></table>';

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
     * @param FrontendComment $comment
     * @param Field $field
     * @param array $frontendFormsConfig
     * @param int $status
     * @return bool
     * @throws WireException
     */
    public function sendStatusChangeEmail(FrontendComment $comment, Field $field, array $frontendFormsConfig, int $status): bool
    {

        // check if sending email is enabled inside the configuration

        $mail = new WireMail();

        // set the sender email address
        $host = $this->wire('config')->httpHost;
        if ($host === 'localhost') {
            $host = 'localhost.com';
        }
        $mail->from('comment-notification@' . $host);

        // set from name if present
        if (FieldtypeFrontendComments::getFieldConfigLangValue($field, 'input_fc_from_name')) {
            $mail->fromName(FieldtypeFrontendComments::getFieldConfigLangValue($field, 'input_fc_from_name'));
        }

        $mail->subject($this->_('Comment status has been changed'));
        $mail->title(sprintf($this->_('Your comment status has been changed to %s'), FieldtypeFrontendComments::statusTexts()[$status]));

        // set email template depending on config settings
        // Same "never explicitly saved" null-safety as the constructor's own read of this setting -
        // a field that has never had "input_fc_emailTemplate" explicitly saved returns null here,
        // not the documented default of 'inherit'. Unlike the constructor's case, this used to fail
        // silently rather than with an immediate TypeError: null !== 'inherit', so the ternary below
        // always took its "else" branch and left $template as null, which was then passed straight
        // into $mail->mailTemplate(null) - a non-nullable string parameter in real ProcessWire,
        // fataling there instead. Defaulting to 'inherit' here makes an unconfigured field behave
        // exactly like one explicitly set to "inherit from FrontendForms", as documented.
        $emailTemplate = $field->get('input_fc_emailTemplate') ?? FieldtypeFrontendComments::getDefaultData()['input_fc_emailTemplate'];
        $template = $emailTemplate === 'inherit' ? $frontendFormsConfig['input_emailTemplate'] : $emailTemplate;
        if ($template !== 'text') {
            $mail->mailTemplate($template);
        }

        $mail->bodyHTML($this->renderStatusChangeBody($comment, $status));
        $mail->to($comment->get('email'));

        return (bool)$mail->send();
    }
}
