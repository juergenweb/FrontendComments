<?php
    declare(strict_types=1);

    /*
     * File for rendering the list of comments as an unordered list
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: Comments.php
     * Created: 24.06.2023
     *
     * @property protected array $frontendFormsConfig:  array containing all configuration settings of the FrontendForms module
     * @property protected array frontendCommentsConfig: array containing all configuration settings of the FrontendComments input field
     * @property protected CommentArray $comments: contains all comments as a CommentArray
     * @property protected Page $page: the page object the comment field is part of
     * @property protected Field $field: the field object for the comment field
     * @property protected Link $cancel: the link object to cancel the reply comment
     *
     * @method int numberOfChildren(): Get the number of children of a comment with a certain id
     * @method string renderComments(): Output all comments as an unordered list including sub-levels if set
     */

    namespace FrontendComments;

    use ProcessWire\InputfieldFrontendComments;
    use ProcessWire\Wire;
    use ProcessWire\Field;
    use ProcessWire\Page;

    class Comments extends Wire
    {
        use configValues;

        protected array $frontendFormsConfig = [];
        protected array $frontendCommentsConfig = [];
        protected CommentArray $comments;
        protected Field $field;
        protected Page $page;

        public function __construct(CommentArray $comments)
        {
            parent::__construct();



            $this->comments = $comments; // the CommentArray object
            $this->field = $comments->getField(); // Processwire comment field object
            $this->page = $comments->getPage(); // the current page object, which contains the comment field

            // get configuration values from the FrontendForms module
            $this->frontendFormsConfig = $this->getFrontendFormsConfigValues();
            // get configuration values from the FrontendComments input field
            $this->frontendCommentsConfig = $this->getFrontendCommentsInputfieldConfigValues();

        }

        /**
         * Returns the number of children of a comment with a given id
         * @param Comment $comment
         * @return int
         */
        public function numberOfChildren(Comment $comment): int
        {
            return $this->comments->find('parent_id=' . $comment->id)->count;
        }

        /**
         * Returns the markup of a single comment
         * @param Comment $comment
         * @param bool $levelStatus
         * @param int $level
         * @return string
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        protected function renderSingleComment(Comment $comment, bool $levelStatus, int $level): string
        {
            return $comment->___renderComment($comment, $levelStatus, $level);
        }

        /**
         * Render the list of comments (including different depths) as an unordered list
         * @param int|string|null $parent_id
         * @param int|null $queryId
         * @param int $level
         * @return string
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        public function ___renderComments(int|string|null $parent_id, int|null $queryId, int $level = 0): string
        {

            $out = '';

            // convert $queryId to int (could be null too)
            $queryId = (int)$queryId;

            $levelStatus = true;

            if ($parent_id !== 0) {
                // check if the level is not higher than the max level, otherwise set the max level
                if ($level >= (int)$this->frontendCommentsConfig['input_fc_depth']) {
                    $level = (int)$this->frontendCommentsConfig['input_fc_depth'];
                    $levelStatus = false;
                }
            }

            if ($levelStatus) {
                $levelClass = ($level == 0) ? ' comments-list' : ' comments-list reply-list'; // add additional class for sublevels
                $out .= '<ul id="' . $this->comments->getField()->name . '-list-' . $parent_id . '" class="fc-list level-' . $level . $levelClass . '">';
            }
            // get all comments with status approved (=1)
            if (!is_null($parent_id)) {

                foreach ($this->comments->find('parent_id=' . $parent_id . ',status=' . InputfieldFrontendComments::approved) as $data) {
                    if ($data instanceof Comment) {

                        $out .= '<li id="comment-' . $data->id . '" class="fc-listitem">' . $this->renderSingleComment($data,
                                $levelStatus, $level);

                        if ($this->numberOfChildren($data)) {
                            // comment has at least 1 child
                            $out .= $this->___renderComments($data->id, $queryId, $level + 1);
                        }

                        $out .= '</li>';
                    }
                }
            }
            if ($levelStatus) {
                $out .= '</ul>';
            }

            return $out;
        }
    }
