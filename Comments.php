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
     * @property protected Textelements $commentsHeadline: the headline object above the comment list
     *
     * @method int numberOfChildren(): Get the number of children of a comment with a certain id
     * @method string renderComments(): Output all comments as an unordered list including sub-levels if set
     */

    namespace FrontendComments;

    use FrontendForms\TextElements;
    use ProcessWire\FieldtypeFrontendComments;
    use ProcessWire\Wire;
    use ProcessWire\Field;
    use ProcessWire\Page;

    class Comments extends Wire
    {
        use configValues;

        // Declare all properties
        protected array $frontendFormsConfig = [];
        protected bool|int|null $input_fc_sort = false;
        protected TextElements $commentsHeadline;
        protected array $frontendCommentsConfig = [];
        protected CommentArray $comments;
        protected Field $field;
        protected Page $page;

        /**
         * @throws \ProcessWire\WireException
         * @throws \ProcessWire\WirePermissionException
         */
        public function __construct(CommentArray $comments)
        {
            parent::__construct();

            $this->comments = $comments; // the CommentArray object
            $this->field = $comments->getField(); // Processwire comment field object
            $this->page = $comments->getPage(); // the current page object, which contains the comment field

            // get configuration values from the FrontendForms module
            $this->frontendFormsConfig = $this->getFrontendFormsConfigValues();
            // get configuration values from the FrontendComments input field
            $this->frontendCommentsConfig = $this->getFrontendCommentsInputfieldConfigValues($this->field);

            $this->commentsHeadline = new TextElements();
            $commentsHeadlineType = array_key_exists('input_fc_commentsheadtype', $this->frontendCommentsConfig) ? $this->frontendCommentsConfig['input_fc_commentsheadtype'] : 'h3';
            $this->commentsHeadline->setTag($commentsHeadlineType);
            $this->commentsHeadline->setAttribute('class', 'fc-comments-headline');
            $this->commentsHeadline->setText($this->_('Comments'));

            // add or remove the headline for the comments depending on the settings
            $headConfig = (array_key_exists('input_fc_commentsheadline', $this->frontendCommentsConfig)) ? $this->frontendCommentsConfig['input_fc_commentsheadline'] : '';
            $this->addHeadline($headConfig);

            // create properties of FrontendComments configuration values
            $properties = ['input_fc_sort'];
            $this->createPropertiesOfArray($this->frontendCommentsConfig, $properties);

        }

        /**
         * Get the comments headline object for further manipulations
         * @return \FrontendForms\TextElements
         */
        public function getCommentsHeadline(): TextElements
        {
            return $this->commentsHeadline;
        }

        /**
         * Add or remove a headline above the comments
         * @param string $headline
         * @return void
         */
        public function addHeadline(string|null $headline): void
        {
            if (!is_null($headline)) {
                if ($headline === 'none') {
                    // remove the headline
                    $this->getCommentsHeadline()->setText('');
                } else {
                    if ($headline != '')
                        $this->getCommentsHeadline()->setText($headline);
                }
            }
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
         * Render a pagination for the comments
         * @param \FrontendComments\CommentArray $comments
         * @param $options
         * @return string
         * @throws \ProcessWire\WireException
         */
        function renderPagination(CommentArray $comments, $options = array())
        {

            if (!$comments->count) {
                if ($this->wire('input')->pageNum > 1) {
                    // redirect to first pagination if accessed at an out-of-bounds pagination
                    $this->wire('session')->redirect($this->wire('page')->url);
                }
                return '';
            }

            $defaults = array(
                'id' => 'comments',
                'paginate' => false,
                'limit' => 2,
            );

            $options = array_merge($defaults, $options);

            $language = $this->wire('user')->language->id;
            $comments = $comments->find("language=$language");

            if ($options['paginate']) {
                $limit = $options['limit'];
                $start = ($this->wire('input')->pageNum - 1) * $limit;
                $total = $comments->count();
                $comments = $comments->slice($start, $limit);
                $comments->setLimit($limit);
                $comments->setStart($start);
                $comments->setTotal($total);
            } //>>>>>NEW-end

            $out = "<ul id='$options[id]' class='uk-comment-list'>";

            foreach ($comments as $comment) {
                $out .= "<li class='uk-margin'>" . ukComment($comment) . "</li>";
            }

            $out .= "</ul>";

            if ($options['paginate'] && $comments->getTotal() > $comments->count()) { //>>>>>NEW-start
                $out .= ukPagination($comments);
            }

            return $out;
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
            if ($this->comments->count === 0)
                return $out; //output nothing

            // convert $queryId to int (could be null too)
            $queryId = (int)$queryId;

            $ulLevelStatus = true;
            $levelStatus = true;

            if ($parent_id !== 0) {


                // check if the level is not higher than the max level, otherwise set the max level
                if ($level >= (int)$this->frontendCommentsConfig['input_fc_depth']) {
                    $level = (int)$this->frontendCommentsConfig['input_fc_depth'];
                    $levelStatus = false;
                }

                if ($level >= (int)$this->frontendCommentsConfig['input_fc_depth'] + 1) {
                    $ulLevelStatus = true;
                }
            }

            if ($ulLevelStatus) {

                if ($level == 0) {
                    $out .= '<div class="fc-comments-wrapper">';
                    // render the comments headline if present
                    $out .= $this->getCommentsHeadline()->___render();
                }


                $levelClass = ($level == 0) ? ' fc-comments-list uk-comment-list' : ' fc-comments-list fc-reply-list'; // add additional class for sublevels
                $out .= '<ul id="' . $this->comments->getField()->name . '-list-' . $parent_id . '" class="fc-list level-' . $level . $levelClass . '">';
            }

            // change the sort order if set
            $comments = $this->comments;
            if ($this->input_fc_sort) {
                $comments = $this->comments->reverse();
            }

            // get all comments with status approved (=1)
            if (!is_null($parent_id)) {

                foreach ($comments->find('parent_id=' . $parent_id . ',status=' . FieldtypeFrontendComments::approved . '|' . FieldtypeFrontendComments::spamReplies) as $data) {
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

            if ($ulLevelStatus) {

                $out .= '</ul>';
                if ($level == 0) {
                    $out .= '</div>';
                }
            }

            return $out;
        }
    }
