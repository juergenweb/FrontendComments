<?php
    declare(strict_types=1);

    /*
     * This file contains all methods to render and manipulate a single comment
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
     * @property protected int $created_user_id: the id of the user, who has created the comment
     * @property protected array $comment: contains all values of the comment
     * @property protected CommentArray $comments: contains all comments as a CommentArray
     * @property protected Page $page: the page object the comment field is part of
     * @property protected Field $field: the field object for the comment field
     * @property protected array $frontendFormsConfig: the configuration values as set in FrontendForms
     * @property protected array $frontendCommentsconfig: the configuration values as set in by this module
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

    use FrontendForms\Link;
    use ProcessWire\Field;
    use ProcessWire\Page;
    use ProcessWire\WireData;
    use FrontendForms\TextElements;
    use FrontendForms\Image;
    use ProcessWire\WireException;

    class Comment extends WireData
    {

        use configValues;

        // set default comment properties
        protected string|int $id = 0;
        protected string|int $parent_id = 0;
        protected string|int $sort = 0;
        protected string|int $status = 0;
        protected string|int $created = '';
        protected string $email = '';
        protected string $author = '';
        protected string $text = '';
        protected int $created_user_id = 40;
        protected array $comment = [];
        protected array $frontendFormsConfig = [];
        protected array $frontendCommentsConfig = [];
        protected CommentArray $comments; // the array containing all comments of this page
        protected Field $field;
        protected Page $page;
        protected TextElements $commentText; // the comment text object
        protected TextElements $commentCreated; // the date object of the comment
        protected TextElements $commentAuthor; // the comment author object
        protected Link $replyLink; // same as the reply button, but as a link
        protected Image $avatar; // image object for the user avatar image

        protected CommentForm $form; // the form object for replies

        /**
         * @throws WireException
         */
        public function __construct(array $comment, CommentArray $comments)
        {
            parent::__construct();

            // set defaults for the comment as fallback for non-provided values
            $this->created = time(); // current time of creation as default value

            $this->comments = $comments;
            $this->field = $comments->getField();
            $this->page = $comments->getPage();



            // grab configuration values from the FrontendForms module
            $this->frontendFormsConfig = $this->getFrontendFormsConfigValues();
            // grab configuration values from the FrontendComments input field
            $this->frontendCommentsConfig = $this->getFrontendCommentsInputfieldConfigValues();

            // set all comment values provided via the constructor as property
            foreach ($comment as $name => $value) {
                if ($name === 'data') $name = 'text';
                $this->set($name, $value);
                $this->$name = $value;
            }

            // Instantiate all text objects for the comment

            // avatar
            $this->avatar = new Image();

            // commentText
            $this->commentText = new TextElements();
            $this->commentText->setContent($this->getText());
            $this->commentText->setAttribute('class', 'fc-comment-text');

            // commentCreated
            $this->commentCreated = new TextElements();
            $this->commentCreated->setContent($this->getCreated());
            $this->commentCreated->setAttribute('class', 'fc-comment-created');

            // commentCreated
            $this->commentAuthor = new TextElements();
            $this->commentAuthor->setContent($this->getAuthor());
            $this->commentAuthor->setAttribute('class', 'fc-comment-author');

            // reply Link
            $this->replyLink = new Link('reply-' . $this->getId());
            $this->replyLink->setAttribute('class', 'fc-comment-reply');
            $this->replyLink->setAttribute('title', $this->_('Click to reply to this comment'));
            $this->replyLink->setLinkText($this->_('Reply'));
            $this->replyLink->setUrl('#');
            $this->replyLink->setAttribute('data-field', $this->field->name);
            $this->replyLink->setAttribute('data-parent_id', $this->parent_id);
            $this->replyLink->setAttribute('data-id', $this->id);

            // reply form
/*
            $this->form = new CommentForm($comments, 'reply-form-'.$this->getId(), $this->getId());

            // TODO: delete afterwards - only for dev purposes disabled
            $this->form->setMaxAttempts(0);
            $this->form->setMinTime(0);
            $this->form->setMaxTime(0);

            $this->form->prepend('<h3>'.$this->_('Write an answer to this comment').'</h3>');
            // get the submit button object and change the name attribute
            $submitButton = $this->form->getSubmitButton();
            $submitButton->setAttribute('name', 'reply-form-'.$this->getId().'-submit');
*/
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
         * Get the the author of the comment
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
         * Get the comment creation text object
         * @return TextElements
         */
        public function getCommentDate(): TextElements
        {
            return $this->commentCreated;
        }

        /**
         * Get the comment author object
         * @return TextElements
         */
        public function getCommentAuthor(): TextElements
        {
            return $this->commentAuthor;
        }

        /**
         * Get the reply link object
         * @return Link
         */
        public function getReplyLink(): Link
        {
            return $this->replyLink;
        }


        /**
         * Render the image tag for the avatar image
         * @return string - <img....> or nothing
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        public function ___renderAvatar(): string
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
         * Check if specific query string "formid" is equal to the form id
         * @param string|null $querystring
         * @param string $formid
         * @param string $queryValue
         * @return bool
         */
        protected function checkFormId(string|null $querystring, string $formid): bool
        {

            if($querystring){
                $queryString = explode('=', $querystring);
                $queryValue = $queryString[1];

                if($queryValue === $formid){
                    return true;
                } else {
                    return false;
                }
            }
            return false;
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
        public function renderDefault(Comment $comment, bool $levelStatus, int $level): string
        {

            // check if the query string for this form is present and add style attribute depending on the querystring
            $formQueryString = $this->wire('input')->queryStringClean(['validNames' => ['formid']]);

            /*
            $style = true;
            if($this->checkFormId($formQueryString, $this->form->getId())){
                $style = false;
            }*/



            $out = '';
            if ($level === 0) {
                $out .= '<div id="comment-wrapper-'.$comment->id.'" class="comment-main-level">';
            }
            $out .= '<div class="comment-avatar">' . $this->___renderAvatar() . '</div>';

            $out .= '<div class="comment-box">';

            $out .= '<div class="comment-head">';
            $out .= '<h6 class="comment-name by-author">' . $this->getCommentAuthor()->getContent() . '</h6>';
            $out .= '<span>' . $this->getCommentDate()->getContent() . '</span>';
            // show the reply link only if max level is not reached
            if ($levelStatus) {
                $this->getReplyLink()->setAttribute('data-field', $this->field->name);
                $this->getReplyLink()->setAttribute('id', $this->field->name . '-' . $this->getReplyLink()->getAttribute('id'));
                $this->getReplyLink()->setContent('<i class="fa fa-reply"></i>');
                $out .= $this->getReplyLink()->___render();

            }
            //$out .= '<i class="fa fa-reply"></i>';
            $out .= '<i class="fa fa-heart"></i>';

            // star rating
            if ((array_key_exists('input_fc_stars', $this->frontendCommentsConfig)) && $this->frontendCommentsConfig['input_fc_stars'] === 1) {
                if(is_null($this->stars)){
                    $this->stars = 0;
                }

                $out .= '<div class="star-rating-comment">' . CommentForm::___renderStarRating((float)$this->stars) . '</div>';
            }


            $out .= '</div>';

            $out .= '<div id="' . $this->getReplyLink()->getAttribute('id') . '-comment" class="comment-content">' . $this->getCommentText()->___render() . '</div>';



            //$out .= '<div id="' . $this->form->getID() . '-form-wrapper" data-id="'.$this->id.'" class="reply-form-wrapper"></div>';
            $out .= '<div id="reply-comment-form-'.$this->getReplyLink()->getAttribute('id').'" data-id="'.$this->id.'" class="reply-form-wrapper"></div>';


            $out .= '</div>';

            if ($level === 0) {
                $out .= '</div>';
            }
            return $out;
        }

        /**
         * Render method for creating Uikit3 output
         * @param \FrontendComments\Comment $comment
         * @param bool $levelStatus
         * @return string
         * @throws \ProcessWire\WireException
         */
        public function renderUikit3(Comment $comment, bool $levelStatus): string
        {
            $out = '<article id="comment-wrapper-'.$comment->id.'" class="uk-comment" role="comment">';
            $out .= '<header class="uk-comment-header">';
            $out .= '<div class="uk-grid-medium uk-flex-middle" data-uk-grid>';
            $out .= '<div class="uk-width-auto">';
            $out .= '<img class="uk-comment-avatar" src="images/avatar.jpg" width="80" height="80" alt="">';
            $out .= '</div>';
            $out .= '<div class="uk-width-expand">';
            $out .= '<h4 class="uk-comment-title uk-margin-remove"><a class="uk-link-reset" href="#">Author</a></h4>';
            $out .= '<ul class="uk-comment-meta uk-subnav uk-subnav-divider uk-margin-remove-top">';
            $out .= '<li><a href="#">' . $this->getCreated() . '</a></li>';
            // show the reply link only if max level is not reached
            if ($levelStatus) {
                $out .= '<li>' . $this->getReplyLink()->___render() . '</li>';
            }
            $out .= '</ul>';
            $out .= '</div>';
            $out .= '</div>';
            $out .= '</header>';
            $out .= '<div class="uk-comment-body">';
            $out .= '<p>' . $this->getCommentText()->___render() . '</p>';
            $out .= '</div>';
            $out .= '</article>';
            return $out;
        }

        /**
         * Render the markup of a comment
         * @param \FrontendComments\Comment $comment
         * @param bool $levelStatus
         * @param int $level
         * @return string
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        public function ___renderComment(Comment $comment, bool $levelStatus, int $level): string
        {
            $frameWork = ucfirst(pathinfo($this->frontendFormsConfig['input_framework'], PATHINFO_FILENAME));
            $frameWork = ucfirst(pathinfo($this->frontendFormsConfig['input_framework'], PATHINFO_FILENAME));
            $methodName = 'render' . $frameWork;
            if (method_exists($this, $methodName))
                return $this->$methodName($comment, $levelStatus, $level);
            return $this->renderDefault($comment, $levelStatus, $level);
        }


    }