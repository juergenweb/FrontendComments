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
use FrontendForms\InputHidden;
use FrontendForms\TextElements;
use FrontendForms\Link;
use FrontendForms\Image;
use PDO;
use ProcessWire\FieldtypeFrontendComments;
use ProcessWire\Page;
use ProcessWire\Field;
use ProcessWire\WireData;
use ProcessWire\PageImage;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;
use function ProcessWire\wire;

class FrontendComment extends WireData
{

    const flagNotifyNone = 0; // Flag to indicate that the author of this comment does not want to be notified of replies
    const flagNotifyReply = 1; //Flag to indicate the author of this comment wants to be notified of replies to their comment
    const flagNotifyAll = 2; // Flag to indicate the author of this comment wants to be notified of all comments on the page

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
            if ($name === 'data') $name = 'text';
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

    }

    /**
     * Get the creation date of the comment depending on the settings
     * @param int|string $date
     * @param int|null $format
     * @return FrontendComment
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
        $this->commentAuthor->setContent($this->get('author'));
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
        if ($this->getCommentAvatar())
            $out = $this->getCommentAvatar()->render();
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

        if (!$this->get('created'))
            $this->set('created', time());

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

        if (!$show) return $out;

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
        $this->commentText->setContent($this->get('text'));
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
        $this->replyLink->setAttribute('data-ajax', $this->submitAjax ?? '0');
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
    public function numberOfReplies(int $status = null): int
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

    /** Check if the comment is published (true) or not (false)
     * @return bool
     */
    public function isPublished(): bool
    {
        if (($this->get('status') === FieldtypeFrontendComments::approved) || ($this->get('status') === FieldtypeFrontendComments::featured))
            return true;
        return false;
    }

    /**
     * Get the previous status of a comment if the status has changed now
     * @return int|null
     */
    public function getPreviousCommentStatus(): int|null
    {
        if (!$this->isChanged('status')) return null;
        return $this->getChanges(true)['status'][0];
    }

    /**
     * Get the id of a comment based on the code
     * This method is used for newly added comments, where the id is not present inside the WireArray
     * @return int|null
     * @throws WireException
     */
    public function getCommentIDFromDatabase(): int|null
    {
        $page = $this->get('page');
        $field = $this->get('field');
        $database = $this->wire('database');
        $table = $field->table; // set the comment table name

        $statement = "SELECT id FROM $table WHERE code=:code";
        $query = $database->prepare($statement);
        $query->bindValue(":code", $this->get('code'), PDO::PARAM_STR);

        try {
            $query->execute();
            $results = $query->fetchAll();
            if($results){
                return $results[0]['id'];
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
    protected function addCommentToQueueTable(): ?bool
    {

        $page = $this->get('page');
        $field = $this->get('field');
        $database = $this->wire('database');

        $commentsTable = $field->table; // set the comment table name
        $notificationEmails = []; // array containing all email addresses for replies

        // 1) Get the mail addresses of all users that have chosen to get informed about new comments
        $statement = "SELECT email FROM $commentsTable WHERE (pages_id=:page_id AND notification=:notification) OR (pages_id=:page_id AND id=:parent_id AND notification=:parent_notification)";
        $query = $database->prepare($statement);
        $query->bindValue(":page_id", $page->get('id'), PDO::PARAM_INT);
        $query->bindValue(":notification", self::flagNotifyAll, PDO::PARAM_INT);
        $query->bindValue(":parent_id", $this->get('parent_id'), PDO::PARAM_INT);
        $query->bindValue(":parent_notification", self::flagNotifyReply, PDO::PARAM_INT);

        try {
            $query->execute();
            $results = $query->fetchAll();

            if($results){
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
        if ($notificationEmails) {

            // write all receivers into the queue table for later sending of emails
            $table = FieldtypeFrontendComments::queueTable;

            // create the value string for the data
            $sendingData = [];
            foreach ($notificationEmails as $email) {

                //$commentID = $this->get('id') ?? $this->comments->getLastID($this);
                $commentID = $this->get('id') ?? $this->getCommentIDFromDatabase();
                $sendingData[] = '(' . $this->get('parent_id') . ',' . $commentID . ',\'' . $email . '\', ' . $field->get('id') . ', ' . $page->get('id') . ')';
            }
            $valuesString = 'VALUES' . implode(',', $sendingData);

            // check first if this comment is in the fc_comments_queue table
            $statement = "SELECT id FROM $table WHERE parent_id=:parent_id AND comment_id=:comment_id AND email=:email AND page_id=:page_id AND field_id=:field_id";

            $query = $database->prepare($statement);
            $query->bindValue(":parent_id", $this->get('parent_id'), PDO::PARAM_INT);
            $query->bindValue(":comment_id", $this->get('id'), PDO::PARAM_INT);
            $query->bindValue(":email", $email, PDO::PARAM_STR);
            $query->bindValue(":page_id", $page->get('id'), PDO::PARAM_INT);
            $query->bindValue(":field_id", $field->get('id'), PDO::PARAM_INT);

            $result = false;
            try {
                $query->execute();
                $result = $query->fetch();

            } catch (Exception $e) {
                $this->log('Message: ' . $e->getMessage());
            }

            if (!$result) {

                // create the SQL statement and save the entries to the database
                $statement = "INSERT INTO $table (parent_id, comment_id, email, field_id, page_id) $valuesString";

                $query = $database->prepare($statement);
                bd('new comment(s) have/has been added to the queue table');

                try {

                    $query->execute();
                } catch (Exception $e) {
                    $this->log('Message: ' . $e->getMessage());
                    return false;
                }

                return true;
            }
            return null;
        }

        return null;
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
     * Render the comment without using a framework markup
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

        $out = '<div class="fcm-comment-box ' . $statusClasses[$this->get('status')] . '"><div class="fcm-comment-head">';
        $out .= $this->renderNoVoteAlertbox();
        $out .= $this->renderCommentAvatar();
        $out .= $this->renderCommentAuthor();
        $out .= $this->renderCommentCreated();
        $out .= $this->renderRating();
        $out .= $this->renderReplyLink($level);
        $out .= $this->renderVotes();
        $out .= '</div>';
        $out .= $this->renderCommentText();
        $out .= $this->renderFeedbackText();
        $out .= $this->renderWebsiteLink();
        $out .= $this->renderReplyForm();
        $out .= '</div>';
        return $out;
    }

}