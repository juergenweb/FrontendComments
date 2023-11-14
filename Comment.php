<?php
    declare(strict_types=1);

    /*
     * This file contains all methods to render and manipulate a single comment including the reply form
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: Comment.php
     * Created: 24.06.2023
     *
     * @property protected int $id:  the id of the comment inside the database
     * @property protected string $text: the comment text
     * @property protected int $sort:
     * @property protected int $status: the status of the comment
     * @property protected string|int $created: the time of creation
     * @property protected string $email: the email
     * @property protected string $author: the name of the author
     * @property protected array $comment: contains all values of the comment
     * @property protected CommentArray $comments: contains all comments as a CommentArray
     * @property protected Page $page: the page object the comment field is part of
     * @property protected Field $field: the field object for the comment field
     * @property protected array $frontendFormsConfig: the configuration values as set in FrontendForms
     * @property protected array $frontendCommentsconfig: the configuration values as set in by this module
     * @property protected int|null|bool $input_fc_stars: show star rating or not
     * @property protected int|null|bool $input_fc_voting: show voting options or not
     *
     * @method int getEmail(): Get the email of the commenter
     * @method int getAuthor(): Get the name of the commenter
     * @method int getText(): Get the text of the comment
     * @method int getId(): Get the id of the comment
     * @method int getUserId(): Get the id of the user, who has created this comment
     *
     * @method TextElements getCommentDate(): Get the comment created date object
     * @method TextElements getCommentText(): Get the comment text object
     * @method TextElements getCommentAuthor(): Get the comment author object
     * @method Link getReplyLink(): Get the link object for the reply link
     *
     */

    namespace FrontendComments;

    use FrontendForms\Link as Link;
    use ProcessWire\Field;
    use ProcessWire\Page;
    use ProcessWire\WireData;
    use FrontendForms\TextElements;
    use FrontendForms\Image;
    use ProcessWire\WireException;
    use FrontendComments\Bootstrap5Comment;


    class Comment extends WireData
    {

        use configValues;

        const flagNotifyReply = 1; //Flag to indicate the author of this comment wants to be notified of replies to their comment
        const flagNotifyAll = 2; // Flag to indicate the author of this comment wants to be notified of all comments on the page

        // set default comment properties
        protected string|int $id = 0;
        protected string|int $parent_id = 0;
        protected string|int $sort = 0;
        protected string|int $status = 0;
        protected string|int $created = '';
        protected string $email = '';
        protected string $author = '';
        protected string $text = '';
        protected array $comment = [];
        protected array $frontendFormsConfig = [];
        protected array $frontendCommentsConfig = [];
        protected int|bool|null $input_fc_stars = false;
        protected int|bool|null $input_fc_voting = false;

        protected CommentArray $comments; // the array containing all comments of this page
        protected Field $field;
        protected Page $page;
        protected Image $avatar; // image object for the user avatar image
        protected TextElements $commentAuthor; // the comment author object
        protected TextElements $commentCreated; // the date object of the comment
        protected Link $replyLink; // same as the reply button, but as a link
        protected Link $upvote; // link to like the comment
        protected Link $downvote; // link to dislike the comment
        protected TextElements $commentText; // the comment text object
        protected TextElements $commentRemoved; // the text if comment has been removed by a moderator
        protected CommentForm $form; // the form object for replies

        /**
         * @throws WireException
         */
        public function __construct(array $comment, CommentArray $comments)
        {
            parent::__construct();

            // set defaults for the comment as fallback for non-provided values
            $this->created = time(); // current time of creation as default value
            $this->comment = $comment;
            $this->comments = $comments; // the CommentArray object
            $this->field = $comments->getField(); // Processwire comment field object
            $this->page = $comments->getPage(); // the current page object, which contains the comment field

            // get configuration values from the FrontendForms module
            $this->frontendFormsConfig = $this->getFrontendFormsConfigValues();
            // get configuration values from the FrontendComments input field
            $this->frontendCommentsConfig = $this->getFrontendCommentsInputfieldConfigValues();

            // create properties of FrontendComments configuration values
            $properties = ['input_fc_stars', 'input_fc_voting'];
            $this->createPropertiesOfArray($this->frontendCommentsConfig, $properties);

            // set all comment values provided via the constructor as property
            foreach ($comment as $name => $value) {
                if ($name === 'data') $name = 'text';
                $this->set($name, $value);
                $this->$name = $value;
            }

            // Instantiate all the elements for the comment and set the properties

            // Image
            $this->avatar = new Image();
            $this->avatar->setAttribute('class', 'avatar');
            $this->avatar->wrap()->setAttribute('class', 'comment-avatar');

            // Author name
            $this->commentAuthor = new TextElements();
            $this->commentAuthor->setTag('h6');
            $this->commentAuthor->setContent($this->getAuthor());
            $this->commentAuthor->setAttribute('class', 'comment-name by-author');

            // Creation date
            $this->commentCreated = new TextElements();
            $this->commentCreated->setTag('span');
            $this->commentCreated->setContent($this->getCreated());
            $this->commentCreated->setAttribute('class', 'comment-date');

            // Reply Link
            $this->replyLink = new Link($this->field->name . '-reply-' . $this->getId());
            $this->replyLink->setUrl('#reply-comment-form-' . $this->field->name . '-reply-' . $this->getId());
            $this->replyLink->setAttribute('class', 'fc-comment-reply');
            $this->replyLink->setAttribute('title', $this->_('Reply to this comment'));
            $this->replyLink->setAttribute('data-field', $this->field->name);
            $this->replyLink->setAttribute('data-parent_id', $this->parent_id);
            $this->replyLink->setAttribute('data-id', $this->id);
            $this->replyLink->setLinkText('<i class="fa fa-reply head-fc-icon"></i><span>'. $this->_('Reply').'</span>');
            $this->replyLink->wrap('span')->setAttribute('class', 'icon-box');


            // Up-vote link
            $this->upvote = new Link();
            $this->upvote->setUrl($this->page->url . '?vote=up&votecommentid=' . $this->id);
            $this->upvote->setAttribute('class', 'fc-vote-link');
            $this->upvote->setAttribute('title', $this->_('Like the comment'));
            $this->upvote->setAttribute('data-field', $this->field->name);
            $this->upvote->setAttribute('data-commentid', $this->id);
            $this->upvote->setLinkText('<i class="fa fa-thumbs-up head-fc-icon"></i>');
            $this->upvote->prepend('<span class="icon-box"><span id="' . $this->field->name . '-' . $this->id . '-votebadge-up" class="votebadge">' . $this->upvotes . '</span>')->append('</span>');

            // Down-vote link
            $this->downvote = new Link();
            $this->downvote->setUrl($this->page->url . '?vote=down&votecommentid=' . $this->id);
            $this->downvote->setAttribute('class', 'fc-vote-link');
            $this->downvote->setAttribute('title', $this->_('Dislike the comment'));
            $this->downvote->setAttribute('data-field', $this->field->name);
            $this->downvote->setAttribute('data-commentid', $this->id);
            $this->downvote->setLinkText('<i class="fa fa-thumbs-down head-fc-icon"></i>');
            $this->downvote->prepend('<span class="icon-box"><span id="' . $this->field->name . '-' . $this->id . '-votebadge-up" class="votebadge">' . $this->downvotes . '</span>')->append('</span>');

            // Comment text
            $this->commentText = new TextElements();
            $this->commentText->setContent($this->getText());
            $this->commentText->setAttribute('class', 'fc-comment-text');

            // Comment removed
            $this->commentRemoved = new TextElements();
            $this->commentRemoved->setContent($this->_('This comment has been removed by a moderator because it does not comply with our comment guidelines.'));
            $this->commentRemoved->setAttribute('class', 'comment-removed');

            // Reply form
            $this->form = new CommentForm($comments, 'reply-form-' . $this->getId(), $this->getId());
            $this->form->setAttribute('class', 'reply-form');
            $this->form->setAttribute('action', $this->page->url . '?commentid=' . $this->getId() . '&formid=reply-form-' . $this->getId() . '#reply-comment-form-' . $this->field->name . '-reply-' . $this->getId());
            $this->form->setSubmitWithAjax();

            $this->form->prepend('<h3 class="reply-form-headline">' . $this->_('Write an answer to this comment') . '</h3>');

            // get the submit button object and change the name attribute
            $submitButton = $this->form->getSubmitButton();
            $submitButton->setAttribute('name', 'reply-form-' . $this->getId() . '-submit');

        }

        /**
         * Get the email of the author of the comment
         * @return string
         */
        public function getEmail(): string
        {
            return $this->email;
        }

        /**
         * Get the text of the comment
         * @return TextElements
         */
        public function getText(): string
        {
            return $this->text;
        }

        /**
         * Get the author of the comment
         * @return string
         */
        public function getAuthor(): string
        {
            return $this->author;
        }

        /**
         * Get the id of the comment
         * @return int
         */
        public function getId(): int
        {
            return $this->id;
        }

        /**
         * Get the creation date of the comment
         * Takes the format as defined in the FrontendForms module
         * @return string
         * @throws WireException
         */
        public function getCreated(): string
        {
            $date = $this->wire('datetime')->date($this->frontendFormsConfig['input_dateformat'], $this->created);
            $time = $this->wire('datetime')->date($this->frontendFormsConfig['input_timeformat'], $this->created);
            return $date . ' ' . $time;
        }

        /**
         * Get the id of the user, who has created this comment
         * @return int
         */
        public function getAuthorId(): int
        {
            return $this->user_id;
        }

        /**
         * Get the comment text object
         * @return TextElements
         */
        public function getCommentText(): TextElements
        {
            return $this->commentText;
        }

        /**
         * Get the image (avatar) object for further manipulations
         * @return \FrontendForms\Image
         */
        public function getImageElement(): Image
        {
            return $this->avatar;
        }

        public function getAuthorElement(): TextElements
        {
            return $this->commentAuthor;
        }

        /**
         * Get the comment creation text object
         * @return TextElements
         */
        public function getCreatedElement(): TextElements
        {
            return $this->commentCreated;
        }

        public function getReplyElement(): Link
        {
            return $this->replyLink;
        }

        public function getUpVoteElement(): TextElements
        {
            return $this->upvote;
        }

        public function getDownVoteElement(): TextElements
        {
            return $this->downvote;
        }

        public function getCommentRemovedTextElement(): TextElements
        {
            return $this->commentRemoved;
        }

        /**
         * Render methods
         */

        /**
         * Render the image tag for the avatar image
         * @return string - <img....> or nothing
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        public function ___renderImage(): string
        {
            $out = '';
            $userimageField = $this->frontendCommentsConfig['input_fc_userimage'];
            if ($userimageField !== 'none') {
                $imageFieldName = $this->wire('fields')->get($userimageField)->name;
                // get the user which has written this comment
                $user = $this->wire('users')->get($this->getAuthorId());
                // check if user image field exists for this user

                if (isset($user->$imageFieldName->name)) {
                    $thumb = $user->$imageFieldName->size((int)$this->frontendCommentsConfig['input_fc_imagesize'], (int)$this->frontendCommentsConfig['input_fc_imagesize']);
                    // set src attribute
                    $this->avatar->setAttribute('src', $thumb->url);
                    $out = $this->avatar->___render();
                }
            }
            return $out;
        }

        /**
         * Render the author string if present
         * @return string
         */
        public function ___renderAuthor(): string
        {
            $out = '';
            if ($this->getAuthor()) {
                $out = $this->getAuthorElement()->___render();
            }
            return $out;
        }

        public function ___renderCreated(): string
        {
            return $this->getCreatedElement()->___render();
        }

        public function ___renderReply(bool $levelStatus): string
        {
            $out = '';
            if ($levelStatus && $this->status != '3') {
                $out = $this->getReplyElement()->___render();
            }
            return $out;
        }

        public function ___renderVotes(): string
        {
            $out = '';
            // create the vote links with FontAwesome icons if enabled
            $showVoting = $this->field->input_fc_voting ?? $this->input_fc_voting;

            if ($showVoting && $this->status != '3') {
                $out .= $this->getUpVoteElement()->___render();
                $out .= $this->getDownVoteElement()->___render();
            }
            return $out;
        }

        public function ___renderRating(): string
        {
            $out = '';
            $showStarRating = $this->field->input_fc_stars ?? $this->input_fc_stars;
            if ($showStarRating) {
                $out = CommentArray::___renderStarsOnly($this->stars, true);
            }
            return $out;
        }

        public function ___renderText(): string
        {
            if ($this->status == '1') {
                $out = $this->getCommentText()->___render();
            } else {
                // comment is SPAM, but has replies
                $out = $this->getCommentRemovedTextElement()->___render();
            }
            return $out;
        }

        /**
         * Get the reply comments of the comment
         * @return \FrontendComments\CommentArray
         */
        public function getReplies(): CommentArray
        {
            return $this->comments->find('parent_id=' . $this->id);
        }

        /**
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        public function ___renderCommentMarkup(bool $levelStatus): string
        {
            $out = '<div class="comment-head">';
            $out .= $this->___renderImage();
            $out .= $this->___renderAuthor();
            $out .= $this->___renderCreated();

            $out .= '<div class="fc-icons">';
            $out .= $this->___renderReply($levelStatus);
            $out .= $this->___renderVotes();
            $out .= '</div>';

            // star rating
            $out .= $this->___renderRating();
            $out .= '</div>';

            $out .= '<div id="' . $this->getReplyElement()->getAttribute('id') . '-comment" class="comment-content">';
            $out .= $this->___renderText();
            $out .= '</div>';
            return $out;
        }

        /**
         * Default render method for a single comment
         * @param \FrontendComments\Comment $comment
         * @param bool $levelStatus
         * @param int $level
         * @return string
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        public function ___renderComment(Comment $comment, bool $levelStatus, int $level): string
        {

            $out = '<div id="' . $this->field->name . '-' . $comment->id . '-novote"></div>'; // wrapper for no vote alert box

            if ($level === 0) {
                $out .= '<div id="comment-wrapper-' . $comment->id . '" class="comment-main-level">';
            }

            // render the comment markup depending on CSS framework set in the configuration
            $frameWork = ucfirst(pathinfo($this->frontendFormsConfig['input_framework'], PATHINFO_FILENAME));
            $className = 'FrontendComments\\'.$frameWork.'Comment';
            if (class_exists($className)){
                $class = new $className($this->comment, $this->comments);
            } else {
                $class = $this;
            }

            // create outer wrapper container depending on framework
            switch($this->frontendFormsConfig['input_framework']){
                case('uikit3.json'):
                    $out .= '<article class="uk-comment uk-comment-primary" role="comment">';
                break;
                case('bootstrap5.json'):
                    $out .= '<div class="container card">';
                    break;
                default:
                    $out .= '<div class="comment-box">';
            }

            $out .= $class->___renderCommentMarkup($levelStatus);

            // Reply form
            $out .= '<div id="reply-comment-form-' . $this->getReplyElement()->getAttribute('id') . '" class="reply-form-wrapper" data-id="' . $this->id . '" >';
            if ($this->wire('config')->ajax) {

                // check if the form with this id was requested and render it below the comment
                $queryString = $this->wire('input')->queryString();
                parse_str($queryString, $queryParams);

                if (array_key_exists('commentid', $queryParams)) {
                    $id = $this->wire('sanitizer')->string($queryParams['commentid']);
                    if ($id == $this->id) {
                        $out .= $this->form->___render();
                    }
                }
            }
            $out .= '</div>';

            // outer wrapper container end
            switch($this->frontendFormsConfig['input_framework']){
                case('uikit3.json'):
                    $out .= '</article>';
                    break;
                case('bootstrap5.json'):
                    $out .= '</div>';
                    break;
                default:
                    $out .= '</div>';
            }


            //$out .= '</div>';

            if ($level === 0) {
                $out .= '</div>';
            }
            return $out;
        }

    }
