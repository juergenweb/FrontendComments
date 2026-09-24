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

use Exception;
use FrontendForms\Button;
use FrontendForms\Form;
use FrontendForms\Image;
use FrontendForms\InputHidden;
use FrontendForms\Link;
use FrontendForms\TextElements;
use PDO;
use ProcessWire\Field;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\Page;
use ProcessWire\PageImage;
use ProcessWire\TemplateFile;
use ProcessWire\WireData;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

use function ProcessWire\wire;

class FrontendComment extends WireData
{
    public const flagNotifyNone = 0; // Flag to indicate that the author of this comment does not want to be notified of replies
    public const flagNotifyReply = 1; //Flag to indicate the author of this comment wants to be notified of replies to their comment
    public const flagNotifyAll = 2; // Flag to indicate the author of this comment wants to be notified of all comments on the page

    protected Page $page; // the page object the comment lives on
    protected Field $field; // the field object the comment is part of
    protected array $frontendFormsConfig = []; // array containing all FrontendForms config values
    protected FrontendCommentArray $comments;
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
     * Absolute path to a theme-specific template file used to compose the markup of a single comment.
     * Set this in a theme subclass' constructor (e.g. __DIR__ . '/templates/comment.php') to switch
     * ___renderComment() from PHP string concatenation to a plain HTML template with placeholders.
     * Left empty, renderCommentTemplate() falls through to the caller-supplied $fallback markup.
     */
    protected string $commentTemplateFile = '';

    /**
     * Create a new comment object
     * The construction contains as parameters the FrontendCommentsArray with all comments and the properties
     * the comment itself as an array
     * @param FrontendCommentArray $comments
     * @param array $comment
     * @param array $frontendFormsConfig
     * @throws WireException
     */
    public function __construct(FrontendCommentArray $comments, array $comment, array $frontendFormsConfig)
    {

        parent::__construct();

        $this->field = $comments->getField(); // Processwire comment field object
        $this->page = $comments->getPage(); // the current page object, which contains the comment field
        $this->comments = $comments;
        $this->frontendFormsConfig = $frontendFormsConfig;

        foreach ($comment as $name => $value) {
            if ($name === 'data') {
                $name = 'text';
            }
            // add "Guest" as the name if no name is entered
            if ($name === 'author') {
                $value = ($value == '') ? $this->_('Guest') : $value;
            }
            $this->set('page', $comments->getPage());
            $this->set('field', $comments->getField());
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

        // Markup of a single comment lives in templates/comment.php (module root, next to this file) -
        // this is the "None"/no-framework default, the same mechanism the theme subclasses use. A theme
        // subclass (FrontendCommentBootstrap5 etc.) calls parent::__construct() first and then overwrites
        // this with its own template path right after, so this default only takes effect when no theme
        // subclass is involved at all - see FieldtypeFrontendComments::resolveTemplateFile().
        $this->commentTemplateFile = FieldtypeFrontendComments::resolveTemplateFile(
            'comment.php',
            __DIR__ . '/templates/comment.php'
        );
    }

    /**
     * Get the creation date of the comment depending on the settings
     * @param int|string $date
     * @param int|null $format
     * @return string
     * @throws WireException
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
     * @return PageImage|null
     * @throws WireException
     * @throws WirePermissionException
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
     * @return Link
     * @throws WireException
     */
    protected function createUpVoteElement(): Link
    {
        $this->upvote = $this->wire(new Link());
        $this->upvote->setUrl($this->page->url . '?vote=up&votecommentid=' . $this->get('id'));
        $this->upvote->setAttribute('class', ['fc-vote-link', 'fc-upvote']);
        $this->upvote->setAttribute('title', $this->_('Like the comment'));
        $this->upvote->setAttribute('data-field', $this->field->name);
        $this->upvote->setAttribute('data-commentid', $this->get('id'));
        $this->upvote->setLinkText('<span id="' . $this->field->name . '-' . $this->get('id') . '-votebadge-up" class="fc-votebadge upvote">↑ ' . $this->get('upvotes') . '</span>');
        $this->upvote->append('</div>');
        return $this->upvote;
    }

    /**
     * Get the upvote element
     * @return Link
     */
    public function ___getUpVoteElement(): Link
    {
        return $this->upvote;
    }

    /**
     * Get the downvote text object
     * @return Link
     * @throws WireException
     */
    protected function createDownVoteElement(): Link
    {
        // Down-vote link
        $this->downvote = $this->wire(new Link());
        $this->downvote->setUrl($this->page->url . '?vote=down&votecommentid=' . $this->get('id'));
        $this->downvote->setAttribute('class', ['fc-vote-link', 'fc-downvote']);
        $this->downvote->setAttribute('title', $this->_('Dislike the comment'));
        $this->downvote->setAttribute('data-field', $this->field->name);
        $this->downvote->setAttribute('data-commentid', $this->get('id'));
        $this->downvote->setLinkText('<span id="' . $this->field->name . '-' . $this->get('id') . '-votebadge-down" class="fc-votebadge downvote">↓ ' . $this->get('downvotes') . '</span>');
        $this->downvote->prepend('<div class="votes">');
        return $this->downvote;
    }

    /**
     * Get the downvote element
     * @return Link
     */
    public function ___getDownVoteElement(): Link
    {
        return $this->downvote;
    }

    /**
     * Get all replies as an array of FrontendComment objects
     * Enter a specific status as parameter if you want to get only replies with a certain status value
     * @param int|null $status
     * @return FrontendCommentArray|null
     */
    public function getReplies(int|null $status = null): null|FrontendCommentArray
    {
        if (is_null($status)) {
            return $this->comments->find('parent_id=' . $this->get('id'));
        } else {
            return $this->comments->find('parent_id=' . $this->get('id') . ',status=' . $status);
        }
    }

    /**
     * Get the no-vote alert object
     * @return TextElements
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
     * @return TextElements
     * @throws WireException
     */
    protected function createCommentAuthor(): TextElements
    {
        $this->commentAuthor = $this->wire(new TextElements());
        $this->commentAuthor->setTag('h6');
        // FrontendForms\Tag::setContent()/renderNonSelfclosingTag() does not escape its content at
        // all (only attribute values go through htmlspecialchars() there) - "author" is a free-text
        // value submitted by anonymous visitors via the public comment form, so it must be escaped
        // here before it reaches setContent(), otherwise a crafted author name is a stored XSS
        // against every visitor of this page.
        $this->commentAuthor->setContent($this->wire('sanitizer')->entities($this->get('author')));
        $this->commentAuthor->setAttribute('class', 'fcm-comment-name fcm-by-author');
        return $this->commentAuthor;
    }

    /**
     * Get the comment author name object
     * @return TextElements
     */
    public function ___getCommentAuthor(): TextElements
    {
        return $this->commentAuthor;
    }

    /**
     * Output the comment author name markup
     * @throws WireException
     */
    protected function ___renderCommentAuthor(): string
    {
        return $this->getCommentAuthor()->render();
    }

    /**
     * Get the avatar image object of the user if an image exists
     * @return Image|null
     * @throws WireException
     * @throws WirePermissionException
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
     * @return Image|null
     */
    public function ___getCommentAvatar(): Image|null
    {
        return $this->avatar;
    }

    /**
     * Render the avatar image
     * @return string
     * @throws WireException
     */
    protected function ___renderCommentAvatar(): string
    {
        $out = '';
        if ($this->getCommentAvatar()) {
            $out = $this->getCommentAvatar()->render();
        }
        return $out;
    }

    /**
     * Get the creation date object
     * @return TextElements
     * @throws WireException
     */
    protected function createCommentCreated(): TextElements
    {
        $this->commentCreated = $this->wire(new TextElements());
        $this->commentCreated->setTag('span')->setAttribute('class', 'fcm-comment-created');

        if (!$this->get('created')) {
            $this->set('created', time());
        }

        $this->commentCreated->setContent($this->getFormattedCommentCreationDate($this->get('created'), $this->field->get('input_fc_dateformat')));

        return $this->commentCreated;
    }

    /**
     * Get the creation date object
     * @return TextElements
     */
    public function ___getCommentCreated(): TextElements
    {
        return $this->commentCreated;
    }

    /**
     * Render the creation date markup
     * @return string
     * @throws WireException
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

        if (!$show) {
            return $out;
        }

        if ($showNull && $stars == null) {
            $stars = 0;
        }
        if (!is_null($stars)) {
            $stars = (float)$stars;
            $out = '<div class="fcm-star-rating-result">';

            $fullStars = round($stars, 0, PHP_ROUND_HALF_DOWN);

            $halfStars = (($stars - $fullStars) === 0.0) ? 0 : 1;
            $emptyStars = 5 - $fullStars - $halfStars;
            // full stars
            if ($fullStars) {
                for ($x = 1; $x <= $fullStars; $x++) {
                    $out .= '<span class="fcm-star on"></span>';
                }
            }
            if ($halfStars) {
                $out .= '<span class="fcm-star half"></span>';
            }
            if ($emptyStars) {
                for ($x = 1; $x <= $emptyStars; $x++) {
                    $out .= '<span class="fcm-star"></span>';
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
     * @throws WireException
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
     * @throws WireException
     */
    protected function createCommentText(): TextElements
    {
        $this->commentText = $this->wire(new TextElements());
        // see createCommentAuthor() above - setContent() never escapes, and "text" is the visitor's
        // own free-text comment body, so it must be escaped here (this is the actual stored-XSS
        // vector: the whole point of this module is to display this value to every site visitor).
        $this->commentText->setContent($this->wire('sanitizer')->entities($this->get('text')));
        $this->commentText->setAttribute('class', 'fcm-comment-content');
        return $this->commentText;
    }

    /**
     * Get the comment text object
     * @return TextElements
     */
    public function ___getCommentText(): TextElements
    {
        return $this->commentText;
    }

    /**
     * Render the comment text markup
     * @return string
     * @throws WireException
     */
    public function renderCommentText(): string
    {
        return $this->getCommentText()->render();
    }

    /**
     * Create the object for the feedback text
     * @return TextElements
     * @throws WireException
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
     * @return TextElements
     */
    public function ___getCommentFeedback(): TextElements
    {
        return $this->feedbackText;
    }

    /**
     * Render the feedback text markup
     * @return string
     * @throws WireException
     */
    public function ___renderFeedbackText(): string
    {
        return $this->getCommentFeedback()->render();
    }

    /**
     * Get the reply-link object
     * @return Link
     * @throws WireException
     * @throws WirePermissionException
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
        $this->replyLink->setLinkText($this->_('Reply'));
        return $this->replyLink;
    }

    /**
     * Get the reply link object
     * @return Link
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
     * @return Link
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function createWebsiteLink(): Link
    {
        // Website Link
        $this->websiteLink = $this->wire(new Link($this->field->name . '-website-' . $this->get('id')));
        $this->websiteLink->setUrl($this->get('website'));
        $this->websiteLink->setAttribute('class', 'fc-comment-website');
        $this->websiteLink->setAttribute('title', $this->_('To the homepage of the author'));
        $this->websiteLink->setAttribute('rel', 'nofollow');
        $this->websiteLink->setAttribute('target', '_blank');
        $this->websiteLink->prepend('<span class="author-homepagelink-label">' . $this->_('Homepage of the author:') . '</span> ');
        $this->websiteLink->wrap()->setAttribute('class', 'fc-website-link');
        return $this->websiteLink;
    }

    /**
     * Get the website link object
     * @return Link
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
     * @throws WireException
     * @throws WirePermissionException
     */
    protected function formIsAjaxLoaded(int $commentID): bool
    {
        $id = (int)$this->wire('input')->get('commentid');
        return (($this->wire('config')->ajax) && ($commentID === $id));
    }

    /**
     * Get the cancel button object
     * @return Button
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
     * @return InputHidden
     * @throws Exception
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
     * @return Form
     * @throws WireException
     * @throws Exception
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
     * @throws WireException
     */
    public function ___renderReplyForm(): string
    {
        $out = '<div id="reply-comment-form-' . $this->field->name . '-reply-' . $this->get('id') . '" class="fc-reply-form-wrapper" data-id="' . $this->get('id') . '">';

        // load the reply form only if it is called via Ajax or submitted via POST
        if (($this->get('id') && $this->formIsAjaxLoaded($this->get('id'))) || $this->formIsSubmitted()) {
            $out .= '<div id="reply-comment-form-' . $this->field->name . '-' . $this->get('id') . '" class="fc-reply-form">';
            $out .= $this->getReplyForm()->render();
            $out .= '</div>';
        }
        $out .= '</div>';
        return $out;
    }

    /**
     * Get the number of replies
     * @param int|null $status
     * @return int
     */
    public function numberOfReplies(int|null $status = null): int
    {
        return $this->getReplies($status)->count();
    }

    /**
     * Check if comment has replies and return true or false
     * You can add a specific status as a parameter to only take a look at comments containing this status
     * @param $status
     * @return bool
     */
    public function hasReplies($status = null): bool
    {
        return (bool)$this->numberOfReplies($status);
    }

    /**
     * Check if this comment has at least one reply that would actually be rendered on the
     * frontend (status approved or featured - anything else never reaches display anyway).
     * Used to decide whether a SPAM-marked comment must still remain reachable in the rendered
     * tree instead of being fully hidden, see FrontendComments::getCommentListArray() and
     * isSpamPlaceholder() below.
     * @return bool
     */
    public function hasVisibleReplies(): bool
    {
        return $this->numberOfReplies(FieldtypeFrontendComments::approved) > 0
            || $this->numberOfReplies(FieldtypeFrontendComments::featured) > 0;
    }

    /** Check if the comment is published (true) or not (false)
     * @return bool
     */
    public function isPublished(): bool
    {
        if (($this->get('status') === FieldtypeFrontendComments::approved) || ($this->get('status') === FieldtypeFrontendComments::featured)) {
            return true;
        }
        return false;
    }

    /**
     * Get the previous status of a comment if the status has changed now
     *
     * NOTE: this used to read $this->getChanges(true)['status'][0], which never actually worked -
     * ProcessWire only returns real previous values from getChanges(true) when
     * Wire::trackChangesValues has been explicitly enabled (see Wire::setTrackChanges()); this
     * module only ever calls setTrackChanges(true)/setTrackChanges(), which enables mere
     * "did it change" tracking, not value history. The rest of the codebase already works around
     * this by manually storing the original value in a plain 'old_status' property right after
     * construction (see FieldtypeFrontendComments::init(), where 'old_status' is set from the
     * freshly loaded 'status') - this method now reads that same, already-working value instead.
     * Like the rest of the codebase, it only returns a meaningful value for comment objects that
     * went through that code path; for any others it returns null.
     * @return int|null
     */
    public function getPreviousCommentStatus(): int|null
    {
        $oldStatus = $this->get('old_status');
        return is_null($oldStatus) ? null : (int)$oldStatus;
    }

    /**
     * Get the id of a comment based on the code
     * This method is used for newly added comments, where the id is not present inside the WireArray
     * @return int|null
     * @throws WireException
     */
    public function getCommentIDFromDatabase(): int|null
    {
        $field = $this->get('field');
        $database = $this->wire('database');
        $table = $field->table; // set the comment table name

        $statement = "SELECT id FROM $table WHERE code=:code";
        $query = $database->prepare($statement);
        $query->bindValue(":code", $this->get('code'), PDO::PARAM_STR);

        try {
            $query->execute();
            $results = $query->fetchAll();
            if ($results) {
                // PDO (emulated prepares, ProcessWire's own default - see WireDatabasePDO) returns
                // numeric columns as strings, not ints - under this file's declare(strict_types=1),
                // returning that raw string from a method typed ": int|null" throws a TypeError, so
                // it must be cast explicitly here (see FrontendCommentArray::getLastID() for the
                // same reasoning).
                return (int)$results[0]['id'];
            } else {
                return null;
            }
        } catch (Exception $e) {
            $this->log('Message: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Add a comment to the fc_comments_queues table if it has a published status
     * @return bool|null -> bool if comment has been tried to add to the database, null if comment does not fullfill requirements
     * @throws WireException
     */
    public function addCommentToQueueTable(): ?bool
    {

        $page = $this->get('page');
        $field = $this->get('field');
        $database = $this->wire('database');

        $commentsTable = $field->table; // set the comment table name
        $notificationEmails = []; // array containing all email addresses for replies

        // 1) Get the mail addresses of all users that have chosen to get informed about new comments
        // AND have confirmed (double opt-in, see notification_confirmed in getDatabaseSchema()) that
        // they actually own that email address - otherwise anyone could tick "notify me" while
        // typing in someone else's address and have that person receive emails they never asked for.
        $statement = "SELECT email FROM $commentsTable WHERE (pages_id=:page_id AND notification=:notification AND notification_confirmed=1) OR (pages_id=:page_id AND id=:parent_id AND notification=:parent_notification AND notification_confirmed=1)";
        $query = $database->prepare($statement);
        $query->bindValue(":page_id", $page->get('id'), PDO::PARAM_INT);
        $query->bindValue(":notification", self::flagNotifyAll, PDO::PARAM_INT);
        $query->bindValue(":parent_id", $this->get('parent_id'), PDO::PARAM_INT);
        $query->bindValue(":parent_notification", self::flagNotifyReply, PDO::PARAM_INT);

        try {
            $query->execute();
            $results = $query->fetchAll();

            if ($results) {
                foreach ($results as $row) {
                    $notificationEmails[] = $row['email'];
                }
            }
        } catch (Exception $e) {
            $this->log('Message: ' . $e->getMessage());
            return false;
        }


        // 2) remove the email address of the current commenter from the array
        $notificationEmails = array_diff($notificationEmails, [$this->get('email')]);

        // 3) remove double entries if present
        $notificationEmails = array_unique($notificationEmails);

        // 4) write all mail addresses into the queue table
        if (!$notificationEmails) {
            return null;
        }

        // write all receivers into the queue table for later sending of emails
        $table = FieldtypeFrontendComments::queueTable;
        $commentID = $this->get('id') ?? $this->getCommentIDFromDatabase();

        $insertedAny = false;
        $hadError = false;

        // Check + insert PER recipient. Previously, the "is this recipient already queued?"
        // check ran only once, AFTER the loop that built the list of recipients - by then
        // $email (the loop variable) still held only the LAST recipient's address, so every
        // other recipient's "already queued?" state was never actually checked, while the
        // batched, string-concatenated INSERT below inserted rows for ALL recipients
        // regardless. That duplicated earlier recipients on repeated runs, or - if the last
        // recipient happened to already be queued - silently skipped inserting anyone at all,
        // including brand-new recipients. Checking and inserting per recipient here fixes both
        // issues, and every value is bound as a parameter instead of being concatenated
        // straight into the SQL string.
        foreach ($notificationEmails as $email) {
            $checkStatement = "SELECT id FROM $table WHERE parent_id=:parent_id AND comment_id=:comment_id AND email=:email AND page_id=:page_id AND field_id=:field_id";
            $checkQuery = $database->prepare($checkStatement);
            $checkQuery->bindValue(":parent_id", $this->get('parent_id'), PDO::PARAM_INT);
            $checkQuery->bindValue(":comment_id", $commentID, PDO::PARAM_INT);
            $checkQuery->bindValue(":email", $email, PDO::PARAM_STR);
            $checkQuery->bindValue(":page_id", $page->get('id'), PDO::PARAM_INT);
            $checkQuery->bindValue(":field_id", $field->get('id'), PDO::PARAM_INT);

            $result = false;
            try {
                $checkQuery->execute();
                $result = $checkQuery->fetch();
            } catch (Exception $e) {
                $this->log('Message: ' . $e->getMessage());
                $hadError = true;
                continue;
            }

            if ($result) {
                // this recipient is already queued for this comment - nothing to do
                continue;
            }

            $insertStatement = "INSERT INTO $table (parent_id, comment_id, email, field_id, page_id) VALUES (:parent_id, :comment_id, :email, :field_id, :page_id)";
            $insertQuery = $database->prepare($insertStatement);
            $insertQuery->bindValue(":parent_id", $this->get('parent_id'), PDO::PARAM_INT);
            $insertQuery->bindValue(":comment_id", $commentID, PDO::PARAM_INT);
            $insertQuery->bindValue(":email", $email, PDO::PARAM_STR);
            $insertQuery->bindValue(":field_id", $field->get('id'), PDO::PARAM_INT);
            $insertQuery->bindValue(":page_id", $page->get('id'), PDO::PARAM_INT);

            try {
                $insertQuery->execute();
                $insertedAny = true;
            } catch (Exception $e) {
                $this->log('Message: ' . $e->getMessage());
                $hadError = true;
            }
        }

        if ($hadError && !$insertedAny) {
            return false;
        }

        return $insertedAny ? true : null;
    }

    /**
     * Delete all entries of a specific comment inside the queue table
     * @return void
     * @throws WireException
     */
    public function deleteEntriesInQueueTable(): void
    {
        $table = FieldtypeFrontendComments::queueTable;

        // delete the entry from the queue table
        $statement = "DELETE FROM $table WHERE comment_id=:id AND field_id=:field_id AND page_id=:page_id";

        $query = $this->wire('database')->prepare($statement);
        $query->bindValue(":id", $this->get('id'), PDO::PARAM_INT);
        $query->bindValue(":field_id", $this->field->get('id'), PDO::PARAM_INT);
        $query->bindValue(":page_id", $this->page->get('id'), PDO::PARAM_INT);

        try {
            $query->execute();
        } catch (Exception $e) {
            $this->log('Message: ' . $e->getMessage());
        }
    }

    /**
     * Remove all entries inside the queue table with the given email address
     * This prevents sending further emails if notification has been stopped
     * @return void
     * @throws WireException
     */
    public function deleteEmailsInQueueTable(): void
    {
        $table = FieldtypeFrontendComments::queueTable;

        // Scoped to the page and field this comment belongs to. This method is called when a
        // user cancels notifications via the remote unsubscribe link, which itself is scoped to
        // one page (see FrontendCommentArray::saveReplyNotificationRemote(), matched by email +
        // page id). Previously this DELETE matched only the email address, so unsubscribing on
        // one page also silently deleted this person's still-pending, unrelated queue entries for
        // every other page/field where they remain legitimately subscribed.
        $statement = "DELETE FROM $table WHERE email=:email AND page_id=:page_id AND field_id=:field_id";

        $query = $this->wire('database')->prepare($statement);
        $query->bindValue(":email", $this->get('email'), PDO::PARAM_STR);
        $query->bindValue(":page_id", $this->page->get('id'), PDO::PARAM_INT);
        $query->bindValue(":field_id", $this->field->get('id'), PDO::PARAM_INT);

        try {
            $query->execute();
        } catch (Exception $e) {
            $this->log('Message: ' . $e->getMessage());
        }
    }

    /**
     * Delete all entries of a given comment inside the votes table
     * @return void
     * @throws WireException
     */
    public function deleteEntriesInVotesTable(): void
    {
        $table = $this->field->getTable() . '_votes';

        // delete the entry from the queue table
        $statement = "DELETE FROM $table WHERE comment_id=:id AND page_id=:page_id";

        $query = $this->wire('database')->prepare($statement);
        $query->bindValue(":id", $this->get('id'), PDO::PARAM_INT);
        $query->bindValue(":page_id", $this->page->get('id'), PDO::PARAM_INT);

        try {
            $query->execute();
        } catch (Exception $e) {
            $this->log('Message: ' . $e->getMessage());
        }
    }

    /**
     * General method to update multiple comment values inside the database
     * @param array $values
     * @return bool|null
     * @throws WireException
     */
    public function updateComment(array $values): ?bool
    {

        $page = $this->get('page');
        $field = $this->get('field');
        $table = $field->get('table');
        $saveQuiet = $field->get('input_fc_quiet_save');
        $user = $this->wire('user');

        if (!$saveQuiet) {
            // update modification time and user of the page where the comment belongs to
            $statement = "UPDATE pages SET modified=:modified, modified_users_id=:userid WHERE id=:id";
            $query = $this->wire('database')->prepare($statement);
            $query->bindValue(":modified", wire('datetime')->date('Y-m-d H:i:s', time()));
            $query->bindValue(":userid", $user->get('id'), PDO::PARAM_INT);
            $query->bindValue(":id", $page->get('id'), PDO::PARAM_INT);
            $query->execute();
        }

        // update comment inside the database
        $valuesArray = [];

        foreach ($values as $key => $data) {
            $valuesArray[] = $key . '=:' . $key;
        }
        $valuesString = implode(', ', $valuesArray);

        $statement = "UPDATE $table SET $valuesString WHERE id=:id AND pages_id=:pages_id;";
        $query = $this->wire('database')->prepare($statement);
        foreach ($values as $key => $data) {
            // sanitize value first
            $sanitizer = $data['sanitizer'];
            if (!is_null($sanitizer)) {
                $value = $this->wire()->sanitizer->$sanitizer($data['value']);
            } else {
                $value = $data['value'];
            }

            $type = $data['type'];
            $query->bindValue(':' . $key, $value, $type);
        }

        $query->bindValue(":id", $this->get('id'), PDO::PARAM_INT);
        $query->bindValue(":pages_id", $page->get('id'), PDO::PARAM_INT);

        try {
            if ($query->execute()) {
                return true;
            }
        } catch (Exception) {
            return false;
        }
        return null;
    }

    /**
     * True when this comment's real content must be hidden and replaced by a short "marked as spam"
     * placeholder (see buildSpamPlaceholderVars() below), instead of showing its actual author/text/etc.
     *
     * A SPAM comment normally never reaches render at all - FrontendComments::getCommentsForDisplay()
     * and getCommentListArray() only keep it in the tree in the first place when it still has approved
     * or featured replies underneath it (hasVisibleReplies() above), so those replies stay reachable.
     * This method is what turns that "kept in the tree" decision into "shown as a placeholder" here.
     * @return bool
     */
    public function isSpamPlaceholder(): bool
    {
        return $this->get('status') === FieldtypeFrontendComments::spam;
    }

    /**
     * Replace the identity- and interaction-revealing template variables (author, avatar, rating,
     * votes, website link, reply link/form, moderation feedback) with an empty string, and the comment
     * text itself with a neutral "marked as spam" notice. The timestamp, level/position and status
     * class are left as they were, so the reply thread underneath still renders in the right place.
     * @param array $vars
     * @return array
     */
    protected function buildSpamPlaceholderVars(array $vars): array
    {
        $suppressed = ['author', 'avatar', 'rating', 'votes', 'websiteLink', 'replyLink', 'replyForm', 'feedbackText', 'noVoteAlert'];
        foreach ($suppressed as $key) {
            if (array_key_exists($key, $vars)) {
                $vars[$key] = '';
            }
        }

        if (array_key_exists('commentText', $vars)) {
            $placeholder = $this->wire(new TextElements());
            $placeholder->setContent($this->_('This comment has been marked as spam. Its content is no longer shown, but replies to it remain visible below.'));
            // fcm-comment-spam already exists in frontendcommentsmain.css (background: #eee) - it
            // was defined there but never actually applied anywhere in the markup until now
            $placeholder->setAttribute('class', 'fcm-comment-content fcm-comment-spam');
            $vars['commentText'] = $placeholder->render();
        }

        if (array_key_exists('statusClass', $vars)) {
            $vars['statusClass'] = trim($vars['statusClass'] . ' fcm-comment-spam');
        }

        return $vars;
    }

    /**
     * Render a single comment via $this->commentTemplateFile instead of building the markup with string
     * concatenation in PHP. Every array key in $vars becomes a variable of the same name inside the
     * template file (e.g. $vars['commentText'] becomes $commentText there).
     *
     * Themes with completely different markup (different wrapper tags, different nesting - not just
     * different CSS classes) can therefore keep their HTML in a real, readable template file instead of
     * a chain of $out .= '...' lines.
     *
     * $commentTemplateFile === '' (never set) is the ONLY case that silently returns $fallback - that is
     * the legacy/"none" theme, which intentionally does not use a template file. If a theme DID set
     * $commentTemplateFile but the resulting path does not exist (wrong folder, typo, file not deployed,
     * broken site-level override path in resolveTemplateFile()), that is a misconfiguration, not a valid
     * fallback case - it throws instead of silently rendering an empty comment, which is much easier to
     * miss (no error, just an empty <div class="fc-listitem"> in the markup).
     *
     * @param array $vars associative array of pre-rendered markup snippets / scalars for the template
     * @param string $fallback markup to return when $commentTemplateFile was never set at all
     * @return string
     * @throws WireException
     */
    protected function renderCommentTemplate(array $vars, string $fallback = ''): string
    {
        // Every theme (the "None" default and every framework subclass) funnels its ___renderComment()
        // through this one method with the same set of $vars keys, so intercepting the spam placeholder
        // here - instead of in each ___renderComment() override - applies it everywhere at once.
        if ($this->isSpamPlaceholder()) {
            $vars = $this->buildSpamPlaceholderVars($vars);
        }

        if ($this->commentTemplateFile === '') {
            return $fallback;
        }

        if (!is_file($this->commentTemplateFile)) {
            throw new WireException(sprintf(
                'Comment template file not found: "%s". The theme class (%s) set $commentTemplateFile to this path, but no such file exists - check that it was deployed to the right location, or that resolveTemplateFile() is not pointing at a broken site-level override path.',
                $this->commentTemplateFile,
                static::class
            ));
        }

        $t = new TemplateFile($this->commentTemplateFile);
        foreach ($vars as $name => $value) {
            $t->set($name, $value);
        }

        return $t->render();
    }

    /**
     * Render the comment without using a framework markup.
     * The actual HTML lives in templates/comment.php (module root) - this method only supplies the
     * pre-rendered building blocks (avatar, author, votes, ...) as template variables, exactly like
     * the theme subclasses (FrontendCommentBootstrap5 etc.) do for their own templates.
     * @param string $levelnumber
     * @param int $level
     * @return string
     * @throws WireException
     */
    public function ___renderComment(string $levelnumber, int $level = 0): string
    {
        $statusClasses = [
            FieldtypeFrontendComments::featured => 'fcm-featured',
            FieldtypeFrontendComments::approved => 'fcm-approved',
        ];

        return $this->renderCommentTemplate([
            'levelnumber' => $levelnumber,
            'level' => $level,
            'statusClass' => $statusClasses[$this->get('status')] ?? '',
            'noVoteAlert' => $this->renderNoVoteAlertbox(),
            'avatar' => $this->renderCommentAvatar(),
            'author' => $this->renderCommentAuthor(),
            'created' => $this->renderCommentCreated(),
            'rating' => $this->renderRating(),
            'replyLink' => $this->renderReplyLink($level),
            'votes' => $this->renderVotes(),
            'commentText' => $this->renderCommentText(),
            'feedbackText' => $this->renderFeedbackText(),
            'websiteLink' => $this->renderWebsiteLink(),
            'replyForm' => $this->renderReplyForm(),
        ]);
    }
}
