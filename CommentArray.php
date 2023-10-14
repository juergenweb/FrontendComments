<?php
    declare(strict_types=1);

    /*
     * Class to create the comment Array, which contains all comments and can be manipulated in several ways
     *
     * Created by Jürgen K.
     * https://github.com/juergenweb
     * File name: CommentArray.php
     * Created: 24.06.2023
     *
     * @property protected Page $page: the page object the comment field is part of
     * @property protected Field $field: the field object for the comment field
     * @property protected int $commentId: the value (id) of the query string "commentid"
     * @property string $code: the code for updating the comment status via mail link
     *
     * @method setPage(): set the page the comment field is part of
     * @method Page getPage(): set the page the comment field is part of
     * @method setField(): set the field object for the comments field
     * @method Page getField(): get the field object for the comment field
     * @method CommentArray makeNew(): create a new CommentArray
     */

    namespace FrontendComments;

    use ProcessWire\Field;
    use ProcessWire\Page;
    use ProcessWire\PaginatedArray;
    use ProcessWire\WireArray;
    use ProcessWire\WireException;
    use ProcessWire\WirePaginatable;

    class CommentArray extends PaginatedArray implements WirePaginatable
    {

        use configValues;

        protected Page|null $page = null;
        protected Field|null $field = null;
        protected int $commentId = 0;
        protected string|null $code = null;

        /**
         * @throws \ProcessWire\WireException
         */
        public function __construct()
        {
            parent::__construct();

            // set the value if query string 'code' is present or not (true/false)
            $this->code = $this->getQueryStringValue('code');

            // set the value of the query string 'commentid' as an integer
            $this->commentId = (int)$this->getQueryStringValue('commentid');

        }

        /**
         * Create a new blank CommentArray and add a page and field to it
         * @return \ProcessWire\WireArray
         */
        public function makeNew(): WireArray
        {
            $a = parent::makeNew();
            if ($this->page) $a->setPage($this->page);
            if ($this->field) $a->setField($this->field);
            return $a;
        }

        /**
         * Set the page that these comments are on
         * @param \ProcessWire\Page $page
         * @return void
         */
        public function setPage(Page $page): void
        {
            $this->page = $page;
        }

        /**
         * Get the page object for the comments
         * @return \ProcessWire\Page
         */
        public function getPage(): Page
        {
            return $this->page;
        }

        /**
         * Set the Field that these comments are on
         * @param \ProcessWire\Field $field
         * @return void
         */
        public function setField(Field $field): void
        {
            $this->field = $field;
        }

        /**
         * Get the field object of containing the comments
         * @return \ProcessWire\Field
         */
        public function getField(): Field
        {
            return $this->field;
        }

        /**
         * Get the value of a specific query string if present
         * @param string $queryName
         * @return string|null
         * @throws \ProcessWire\WireException
         */
        protected function getQueryStringValue(string $queryName): string|null
        {
            $queryName = trim($queryName);
            $value = $this->wire('input')->queryStringClean(['validNames' => [$queryName]]);
            if ($value != '') {
                $value = $this->wire('sanitizer')->text($value);
                return preg_replace('~\D~', '', $value);
            }
            return null;
        }

        /**
         * Get the comment form object
         * @return \FrontendComments\CommentForm
         * @throws \ProcessWire\WireException
         */
        public function getCommentForm(): CommentForm
        {
            include_once('CommentForm.php');
            return $this->wire(new CommentForm($this));
        }

        /** Get all comments
         * @return Comments
         * @throws WireException
         */
        public function getComments(): Comments
        {
            include_once('Comments.php');
            return $this->wire(new Comments($this));
        }

        /**
         * Render the comments only
         * @return string
         * @throws WireException
         */
        public function renderComments(): string
        {
            $comments = $this->getComments();
            return $comments->___renderComments(0, $this->commentId);
        }

        /**
         * Render the comment form only
         * @return string
         * @throws WireException
         */
        public function ___render(): string
        {
            $form = $this->getCommentForm();
            return $form->___render();
        }

        /**
         * Render the comment form and the comments as an unordered list
         * @return string
         * @throws WireException
         */
        public function render(): string
        {

            // grab configuration values from the FrontendComments input field
            $frontendCommentsConfig = $this->getFrontendCommentsInputfieldConfigValues();

            // show the form on top only if id=0 or id is different, but query string with code is present
            $form = '';
            if (($this->commentId === 0) || (($this->code))) {
                // get session after redirect of successful form submission
                $validCommentStatus = $this->wire('session')->get('status');
                // TODO: output an alert box
                $this->wire('session')->remove('status');
                $form = $this->___render();
            }
            $content = array_filter([$form, $this->renderComments()]);
            // reverse the order depending on the config settings
            if (array_key_exists('input_fc_outputorder', $frontendCommentsConfig)) {
                $content = array_reverse($content);
            }
            return implode('', $content);
        }

    }
