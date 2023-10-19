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
     * @property array $userdata: array that hold various data about the user visiting the comments
     *
     * @method setPage(): set the page the comment field is part of
     * @method Page getPage(): set the page the comment field is part of
     * @method setField(): set the field object for the comments field
     * @method Page getField(): get the field object for the comment field
     * @method CommentArray makeNew(): create a new CommentArray
     */

    namespace FrontendComments;

    use FrontendForms\Alert;
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
        protected array $userdata = [];


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

            // put user data inside an array for later usage by votes
            $this->userdata = [
                'user_id' => $this->wire('user')->id,
                'ip' => $this->wire('session')->getIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ];

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

            // check if rating is enabled;
            $field = $this->field;
            if (!is_null($field->input_fc_voting)) {

            if ($this->wire('config')->ajax) {
                // check if the querystring votecommentid is present for adding a vote to a comment
                $queryString = $this->wire('input')->queryString();
                parse_str($queryString, $queryParams);

                if (array_key_exists('votecommentid', $queryParams)) {
                    if (array_key_exists('vote', $queryParams)) {

                        $vote = $queryParams['vote'];
                        $database = $this->wire('database');
                        $fieldTableName = 'field_' . $this->field->name;

                        $votesTableName = 'field_' . $this->field->name . '_votes';
                        $comment = $this->find('id=' . $queryParams['votecommentid'])->first();

                        // 1)check first if the user has not voted for this comment within a certain amount of days


                        // check the database if user has voted for this comment
                        $statement = "SELECT id 
		                        FROM $votesTableName
                                WHERE comment_id = :comment_id
                                AND user_id = :user_id
                                AND user_agent = :user_agent
                                AND ip = :ip";

                        $query = $database->prepare($statement);

                        $query->bindValue(':comment_id', $queryParams['votecommentid'], \PDO::PARAM_INT);
                        $query->bindValue(':user_id', $this->userdata['user_id'], \PDO::PARAM_INT);
                        $query->bindValue(':user_agent', $this->userdata['user_agent'], \PDO::PARAM_STR);
                        $query->bindValue(':ip', $this->userdata['ip'], \PDO::PARAM_STR);

                        try {
                            $query->execute();
                            $rowsnumber = $query->rowCount();
                            $query->closeCursor();
                            $result = true;
                        } catch (\Exception $e) {

                            $result = false;
                        }

                        if ($result && ($rowsnumber === 0)) {


                            //2) save data to the votes table first
                            $statement = "INSERT INTO $votesTableName (comment_id, user_id, user_agent, ip, vote)" .
                                " VALUES (:comment_id, :user_id, :user_agent, :ip, :vote)";

                            $query = $database->prepare($statement);

                            $query->bindValue(':comment_id', $queryParams['votecommentid'], \PDO::PARAM_INT);
                            $query->bindValue(':ip', $this->userdata['ip'], \PDO::PARAM_STR);
                            $query->bindValue(':user_id', $this->userdata['user_id'], \PDO::PARAM_INT);
                            $query->bindValue(':user_agent', $this->userdata['user_agent'], \PDO::PARAM_STR);

                            $value = ($vote === 'up') ? 1 : -1;

                            $query->bindValue(':vote', $value, \PDO::PARAM_INT);

                            try {
                                $query->execute();
                                $result = $query->rowCount();
                                $query->closeCursor();
                            } catch (\Exception $e) {
                                $result = 0;
                            }

                            if ($result) {

                                // 3) increase the upvotes or downvotes in field table
                                $commentId = $queryParams['votecommentid'];
                                $pageId = $this->wire('page')->id;

                                // update the field table by incrementing up or downloads
                                $updateCol = ($value === 1) ? 'upvotes' : 'downvotes';

                                $statement = "UPDATE $fieldTableName 
                                SET $updateCol = :$updateCol
                                WHERE  pages_id=$pageId AND id=$commentId
                                ";

                                $query = $database->prepare($statement);

                                $newValue = $comment->{$updateCol} + 1;
                                $query->bindValue(':' . $updateCol, $newValue, \PDO::PARAM_INT);

                                try {
                                    $query->execute();
                                    $result = $query->rowCount();
                                    $query->closeCursor();
                                } catch (\Exception $e) {
                                    $result = 0;
                                }

                                // finally add the new value to the result div
                                echo '<div id="fc-ajax-vote-result" data-votetype="' . $vote . '">' . $newValue . '</div>';
                            }
                        } else {
                            // not allowed to vote

                            // create the alert box
                            $alert = new Alert();
                            $timePeriod = '3 days';
                            $alert->setContent(sprintf($this->_('It seems that you have rated this comment within the last %s. In this case you are not allowed to vote again.'), $timePeriod));
                            $alert->setCSSClass('alert_dangerClass');

                            echo '<div id="fc-ajax-noVote">' . $alert->render() . '</div>';
                        }
                    }
                }
            }
        }

            // grab configuration values from the FrontendComments input field
            $frontendCommentsConfig = $this->getFrontendCommentsInputfieldConfigValues();

            // show the form on top only if id=0 or id is different, but query string with code is present
            $form = '';
            if (($this->commentId === 0) || (($this->code))) {
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
